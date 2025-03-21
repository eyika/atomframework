<?php

namespace Eyika\Atom\Framework\Foundation;

use Dotenv\Dotenv;
use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class Application implements ApplicationInterface
{
    use ServiceContainer;

    protected Arrayable $loadedProviders;

    public const GLOBAL_VARS = [ 
        'base_path' => 'base_path',
        'framework_namespace' => 'framework_namespace',
        'project_namespace' => 'project_namespace',
        'database_namespace' => 'database_namespace',
        'test_namespace' => 'test_namespace'
    ];

    public function __construct(string $basepath)
    {
        $GLOBALS[self::GLOBAL_VARS['base_path']] = $basepath;
        $GLOBALS[self::GLOBAL_VARS['framework_namespace']] = NamespaceHelper::getBaseNamespace();
        $GLOBALS[self::GLOBAL_VARS['project_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "app");
        $GLOBALS[self::GLOBAL_VARS['database_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "database");
        $GLOBALS[self::GLOBAL_VARS['test_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "test");

        // $dotenv = strtolower(PHP_OS_FAMILY) === 'windows' ? Dotenv::createImmutable(base_path()."\\") : Dotenv::createImmutable(base_path()."/");
        $dotenv = Dotenv::createImmutable(base_path());
        $dotenv->load();
        $this->pushDefaultAliases();
        $this->loadedProviders = new Arrayable();
        // $dotenv->required(['DB_USERNAME'])->notEmpty(); ///TODO: get required env keys from config if set
        // print_r($_ENV);
    }

    private function pushDefaultAliases()
    {
        Facade::pushDefaultAliases([
            
        ]);
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
        $providers = config('app.providers', []);
        // print_r($providers);

        foreach ($providers as $provider) {
            // echo "1".PHP_EOL;
            if (!$this->loadedProviders()->keyExists($provider)) {
                // echo $provider.PHP_EOL;
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
        // foreach ($app->loadedProviders() as $provider) {
        //     $provider->boot();
        // }
    }
}
