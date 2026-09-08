<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Facade\Facade;
use ReflectionMethod;
use ReflectionProperty;

/** A model declaring `protected $table`, which is the first thing anyone writes. */
class ProtectedTableWidget extends Model
{
    protected $table = 'atomtest_protected_table';
    public $softdeletes = false;

    protected const fillable = ['id', 'name'];
    protected const guarded  = [];
}

/** …and one declaring it public, which must keep working. */
class PublicTableWidget extends Model
{
    public $table = 'atomtest_public_table';
    public $softdeletes = false;

    protected const fillable = ['id', 'name'];
    protected const guarded  = [];
}

/**
 * Three issues reported by a downstream consumer while wiring up atom-reverb.
 *
 *  1. `app()` took NO arguments, so `app('some.binding')` silently returned the Application —
 *     PHP discards extra arguments to a non-variadic function. The next `->method()` then failed
 *     with "Call to undefined method Application::x()", and inside the try/catch a careful caller
 *     puts around a resolution, that became a plausible EMPTY result rather than an error. In
 *     their case every private-channel subscription would have been refused the moment realtime
 *     was switched on, with one log line as the whole trace.
 *  2. `Model::$table` was `public`, so the Laravel-default `protected $table` fatalled at class
 *     load — while `fillable`/`guarded`/`casts` beside it are `protected const`, giving a reader
 *     no way to infer which is which.
 *  3. Provider auto-discovery called `new $provider($this)` with no `class_exists()` check, so a
 *     vendor/ directory out of step with installed.json fatalled the whole application at boot —
 *     including the `artisan` command you would reach for to repair it.
 */
class ContainerHelperAndBootTest extends IntegrationTestCase
{
    // ---------------------------------------------------------------- 1. app()

    public function test_app_with_no_argument_returns_the_application(): void
    {
        $this->assertInstanceOf(Application::class, app());
    }

    /** The reported defect: this used to hand back the Application. */
    public function test_app_with_a_key_resolves_that_binding(): void
    {
        $sentinel = new \stdClass();
        $sentinel->marker = 'resolved';
        $this->app->instance('probe.binding', $sentinel);

        $resolved = app('probe.binding');

        $this->assertSame($sentinel, $resolved);
        $this->assertNotInstanceOf(Application::class, $resolved, 'app($key) returned the Application');
    }

    public function test_app_with_a_key_matches_the_facade_resolver(): void
    {
        $sentinel = new \stdClass();
        $this->app->instance('probe.binding', $sentinel);

        $this->assertSame(\Eyika\Atom\Framework\Support\Facade\App::make('probe.binding'), app('probe.binding'));
    }

    /** Resolving before an application exists must say so rather than return something plausible. */
    public function test_app_with_a_key_and_no_application_throws(): void
    {
        $previous = Facade::getFacadeApplication();
        Facade::setFacadeApplication(null);

        try {
            $this->expectException(\RuntimeException::class);
            app('probe.binding');
        } finally {
            Facade::setFacadeApplication($previous);
        }
    }

    // ---------------------------------------------------------------- 2. $table visibility

    /** The trap: `protected $table` fatalled at class load with "Access level must be public". */
    public function test_a_model_may_declare_table_protected(): void
    {
        $property = new ReflectionProperty(ProtectedTableWidget::class, 'table');

        $this->assertTrue($property->isProtected());
        $this->assertSame('atomtest_protected_table', $this->tableOf(new ProtectedTableWidget()));
    }

    /** Widening is still allowed, so models already written with `public $table` keep working. */
    public function test_a_model_may_still_declare_table_public(): void
    {
        $property = new ReflectionProperty(PublicTableWidget::class, 'table');

        $this->assertTrue($property->isPublic());
        $this->assertSame('atomtest_public_table', $this->tableOf(new PublicTableWidget()));
    }

    private function tableOf(Model $model): string
    {
        $property = new ReflectionProperty($model, 'table');
        $property->setAccessible(true);

        return (string) $property->getValue($model);
    }

    // ---------------------------------------------------------------- 3. missing provider

    /**
     * A provider the manifest names but the filesystem does not have must be skipped, not fatal.
     * The application has to survive a half-installed package, because otherwise the command that
     * repairs the install is dead too.
     */
    public function test_a_missing_provider_is_skipped_rather_than_fatal(): void
    {
        $method = new ReflectionMethod(Application::class, 'warnMissingProvider');
        $method->setAccessible(true);

        $original = ini_get('error_log');
        $log = sys_get_temp_dir() . '/atom_missing_provider_' . uniqid() . '.log';
        ini_set('error_log', $log);

        try {
            $method->invoke($this->app, 'Vendor\\Gone\\ServiceProvider');
        } finally {
            ini_set('error_log', $original === false ? '' : $original);
        }

        $this->assertTrue(true, 'reached here → reporting a missing provider did not throw');

        $contents = (string) @file_get_contents($log);
        @unlink($log);

        if ($contents !== '') {
            $this->assertStringContainsString('Vendor\\Gone\\ServiceProvider', $contents);
            $this->assertStringContainsString('composer install', $contents, 'the message must say how to fix it');
            $this->assertStringContainsString('installed.json', $contents, 'and name the manifest, not just the package');
        }
    }

    /** registerProviders() must not throw when the configured list names a class that is gone. */
    public function test_boot_survives_a_provider_that_does_not_exist(): void
    {
        $snapshot = \Eyika\Atom\Framework\Support\Config::snapshot();

        try {
            \Eyika\Atom\Framework\Support\Config::set('app.providers', ['Vendor\\Gone\\ServiceProvider']);

            $app = new Application($GLOBALS['base_path'], true);
            $app->registerProviders();

            $this->assertTrue(true, 'reached here → a missing provider did not brick the boot');
        } finally {
            \Eyika\Atom\Framework\Support\Config::restore($snapshot);
        }
    }
}
