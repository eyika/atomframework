<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class Application implements ApplicationInterface
{
    use ServiceContainer;

    protected Arrayable $loadedProviders;
    protected bool $isRunningInTestEnv;

    public const GLOBAL_VARS = [ 
        'base_path' => 'base_path',
        'framework_namespace' => 'framework_namespace',
        'project_namespace' => 'project_namespace',
        'database_namespace' => 'database_namespace',
        'test_namespace' => 'test_namespace'
    ];

    public function __construct(string $basepath, bool $isRunningInTestEnv = false)
    {
        // print "02 basepath is ". $basepath.PHP_EOL;
        $GLOBALS[self::GLOBAL_VARS['base_path']] = $basepath;
        $GLOBALS[self::GLOBAL_VARS['framework_namespace']] = NamespaceHelper::getBaseNamespace();
        $GLOBALS[self::GLOBAL_VARS['project_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", $isRunningInTestEnv ? "src" : "app");
        $GLOBALS[self::GLOBAL_VARS['test_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "test");

        if (!$isRunningInTestEnv) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
            $dotenv->load();
        }

        $this->pushDefaultAliases();
        $this->loadedProviders = new Arrayable();
        $this->isRunningInTestEnv = $isRunningInTestEnv;
        // $dotenv->required(['DB_USERNAME'])->notEmpty(); ///TODO: get required env keys from config if set
        // print_r($_ENV);
    }

    private function pushDefaultAliases()
    {
        // Facade::pushDefaultAliases([
            
        // ]);
    }

    public function isRunningInTestEnvironment()
    {
        return $this->isRunningInTestEnv;
    }

    public function isRunningInFullAppEnvironment()
    {
        return !$this->isRunningInTestEnvironment();
    }

    public function loadedProviders(): Arrayable
    {
        return $this->loadedProviders;
    }

    public function loadProvider(string $alias, ServiceProvider $provider){
        $this->loadedProviders->push([$alias => $provider]);
    }

    public function registerProviders(): void
    {
        $providers = config()->get('app.providers', []);

        foreach ($providers as $provider) {
            if (!$this->loadedProviders()->keyExists($provider)) {
                $instance = new $provider($this);
                $instance->register();
                $this->loadProvider($provider, $instance);

                // Automatically register facades
                foreach ($instance->getFacades() as $alias => $class) {
                    $this->instance($alias, new $class);
                }
            }
        }

        $this->bootProviders();
    }

    protected function bootProviders(): void
    {
        $this->loadedProviders()->each(function (&$alias, &$instance) {
            $instance->boot();
        });
    }
}
