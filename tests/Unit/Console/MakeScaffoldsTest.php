<?php

namespace Eyika\Atom\Framework\Tests\Unit\Console;

use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Cast;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\ConsoleCommand;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Event;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Job;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Listener;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Mail;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Middleware;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Observer;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Policy;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Provider;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Request;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Resource;
use Eyika\Atom\Framework\Foundation\Console\Commands\Make\Rule;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers PKG-08: every make: scaffold renders to a syntactically valid, DECLARABLE
 * class (requiring the rendered stub also proves its base class / interface / trait
 * resolves).
 */
class MakeScaffoldsTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function render(string $commandClass, string $name): string
    {
        $command = new $commandClass();
        $prop = new ReflectionProperty($commandClass, 'stub');
        $prop->setAccessible(true);

        return str_replace('{{name}}', $name, $prop->getValue($command));
    }

    public function test_all_scaffolds_render_declarable_classes(): void
    {
        $cases = [
            [Provider::class,       'ScafProvider',   'App\\Providers\\ScafProvider'],
            [ConsoleCommand::class, 'ScafCommand',    'App\\Console\\Commands\\ScafCommand'],
            [Middleware::class,     'ScafMiddleware', 'App\\Http\\Middlewares\\ScafMiddleware'],
            [Request::class,        'ScafRequest',    'App\\Http\\Requests\\ScafRequest'],
            [Rule::class,           'ScafRule',       'App\\Rules\\ScafRule'],
            [Job::class,            'ScafJob',        'App\\Jobs\\ScafJob'],
            [Event::class,          'ScafEvent',      'App\\Events\\ScafEvent'],
            [Listener::class,       'ScafListener',   'App\\Listeners\\ScafListener'],
            [Mail::class,           'ScafMail',       'App\\Mail\\ScafMail'],
            [Policy::class,         'ScafPolicy',     'App\\Policies\\ScafPolicy'],
            [Resource::class,       'ScafResource',   'App\\Http\\Resources\\ScafResource'],
            [Cast::class,           'ScafCast',       'App\\Casts\\ScafCast'],
            [Observer::class,       'ScafObserver',   'App\\Observers\\ScafObserver'],
        ];

        foreach ($cases as [$commandClass, $name, $fqcn]) {
            $stub = $this->render($commandClass, $name);

            $file = sys_get_temp_dir() . '/atomtest_scaffold_' . uniqid('', true) . '.php';
            file_put_contents($file, $stub);
            $this->tmpFiles[] = $file;

            require $file;

            $this->assertTrue(
                class_exists($fqcn),
                "$commandClass should render a declarable class ($fqcn)"
            );
        }
    }
}
