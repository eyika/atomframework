<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Config;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\View\Blade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * BladeOne's showError() defaults to throwOnError = false: on an error it ob_get_clean()s the
 * half-rendered page and ECHOES `BladeOne Error [...]` with the raw text straight into the
 * response. For the compile-write failure (BladeOne.php: `showError('Compiling', "Unable to save
 * the file [$compiled]...")`, called with no critic/alwaysThrow) that text is an ABSOLUTE server
 * path, and because it is echoed it bypasses `app.debug`, the framework ExceptionHandler, and any
 * controller try/catch — leaking the engine name and a filesystem path to the client whenever the
 * compile dir is unwritable.
 *
 * The framework Blade wrapper now sets throwOnError outside `local`, so such a failure throws a
 * catchable RuntimeException that the ExceptionHandler renders generically (message hidden when
 * `app.debug` is off). In `local` the inline error is kept for debugging.
 */
class BladeThrowOnErrorTest extends TestCase
{
    private Application $app;
    private mixed $priorApp;
    private string $tmpl;
    private string $compiled;

    protected function setUp(): void
    {
        $this->priorApp = Facade::getFacadeApplication();
        $this->app = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($this->app);

        $base = sys_get_temp_dir() . '/atomtest_blade_' . uniqid();
        $this->tmpl = $base . '/views';
        $this->compiled = $base . '/compiles';
        @mkdir($this->tmpl, 0777, true); // the compiled dir is created by the Blade constructor
    }

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing'); // restore the fixture default for later tests
        Facade::setFacadeApplication($this->priorApp);

        @array_map('unlink', glob($this->compiled . '/*') ?: []);
        @rmdir($this->compiled);
        @rmdir($this->tmpl);
        @rmdir(dirname($this->tmpl));

        parent::tearDown();
    }

    private function make(): Blade
    {
        return new Blade($this->tmpl, $this->compiled);
    }

    public function test_throw_on_error_is_enabled_outside_local(): void
    {
        Config::set('app.env', 'production');
        $this->assertTrue($this->make()->throwOnError);
    }

    public function test_throw_on_error_is_disabled_in_local(): void
    {
        Config::set('app.env', 'local');
        $this->assertFalse($this->make()->throwOnError);
    }

    public function test_defaults_to_throwing_when_env_is_unset(): void
    {
        Config::set('app.env', null);
        $this->assertTrue($this->make()->throwOnError, 'an unset env must fail safe to throwing');
    }

    /** Outside local, the compile-write error path throws a catchable exception rather than echoing the path. */
    public function test_compile_error_throws_instead_of_leaking_the_path(): void
    {
        Config::set('app.env', 'production');
        $blade = $this->make();
        $leak = 'Unable to save the file [/srv/app/storage/compiles/9f.bladec]. Check the compile folder';

        $threw = false;
        $base = ob_get_level();
        ob_start();
        try {
            $blade->showError('Compiling', $leak);
        } catch (RuntimeException $e) {
            $threw = true;
            // The message carries the detail (gated by app.debug in the handler), but nothing is
            // echoed to the response.
            $this->assertStringContainsString('Compiling', $e->getMessage());
        } finally {
            while (ob_get_level() > $base) {
                ob_end_clean();
            }
        }

        $this->assertTrue($threw, 'a compile failure must throw outside local, not echo the absolute path');
    }

    /** In local, the inline BladeOne error is preserved (returned/echoed) for the developer. */
    public function test_inline_error_is_preserved_in_local(): void
    {
        Config::set('app.env', 'local');
        $blade = $this->make();

        // showError() ob_get_clean()s one buffer level and then ECHOES, so we start two: the inner
        // one is consumed by showError, the outer one absorbs its echo so nothing escapes to the
        // test output.
        $base = ob_get_level();
        ob_start();
        ob_start();
        $returned = $blade->showError('Compiling', 'Unable to save the file [/abs/path]');
        while (ob_get_level() > $base) {
            ob_end_clean();
        }

        $this->assertStringContainsString('BladeOne Error', $returned);
    }
}
