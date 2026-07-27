<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Foundation\Contracts\DeferrableProvider;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class Application implements ApplicationInterface
{
    use ServiceContainer;

    /** @var Arrayable<ServiceProvider> $loadedProviders */
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
        $GLOBALS[self::GLOBAL_VARS['base_path']] = $basepath;
        $GLOBALS[self::GLOBAL_VARS['framework_namespace']] = NamespaceHelper::getBaseNamespace();
        $GLOBALS[self::GLOBAL_VARS['project_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", $isRunningInTestEnv ? "src" : "app");
        $GLOBALS[self::GLOBAL_VARS['test_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "test");
        $GLOBALS[self::GLOBAL_VARS['database_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json", "database");

        if (!$isRunningInTestEnv) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
            $dotenv->load();
        }

        $this->pushDefaultAliases();
        $this->loadedProviders = new Arrayable();
        $this->isRunningInTestEnv = $isRunningInTestEnv;

        // Default event dispatcher so the Event facade / event() helper / framework
        // route+model events always resolve, even without an app EventServiceProvider
        // (an app provider may still rebind 'events' — its instance then wins).
        if (!$this->bound('events')) {
            $this->singleton('events', fn () => new \Eyika\Atom\Framework\Foundation\Event\Dispatcher());
        }
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

    /**
     * @return Arrayable<ServiceProvider>
     */
    public function loadedProviders(): Arrayable
    {
        return $this->loadedProviders;
    }

    public function loadProvider(string $alias, ServiceProvider $provider){
        $this->loadedProviders->push([$alias => $provider]);
    }

    public function registerProviders(): void
    {
        // Merge the app's configured providers with those auto-discovered from
        // installed packages' composer.json extra.atom.providers (PKG-02). The
        // per-provider keyExists guard below prevents any double registration.
        $providers = array_values(array_unique(array_merge(
            config('app.providers', []),
            (new PackageManifest())->providers()
        )));

        foreach ($providers as $provider) {
            if ($this->loadedProviders()->keyExists($provider)) {
                continue;
            }

            // Deferred providers (PKG-04): don't register/boot now — record the
            // services they provide so they register lazily on first resolution.
            if (is_a($provider, DeferrableProvider::class, true)) {
                $instance = new $provider($this);
                foreach ($instance->provides() as $service) {
                    $this->deferredServices[$service] = $provider;
                }
                continue;
            }

            $instance = new $provider($this);
            /** @var ServiceProvider $instance */
            $instance->register();
            $this->loadProvider($provider, $instance);

            // Automatically register facades
            foreach ($instance->getFacades() as $alias => $class) {
                $this->instance($alias, new $class);
            }
        }

        $this->bootProviders();
    }

    /**
     * Register (and boot) the deferred provider that supplies $service, then drop its
     * deferred entries so it is not triggered again (PKG-04). Overrides the container's
     * no-op hook, called from make() on first resolution of a deferred service.
     */
    protected function loadDeferredService(string $service): void
    {
        if (!isset($this->deferredServices[$service])) {
            return;
        }

        $provider = $this->deferredServices[$service];
        if ($this->loadedProviders()->keyExists($provider)) {
            return;
        }

        $instance = new $provider($this);
        /** @var ServiceProvider $instance */
        $instance->register();
        $this->loadProvider($provider, $instance);

        foreach ($instance->getFacades() as $alias => $class) {
            $this->instance($alias, new $class);
        }

        $instance->boot();

        if ($instance instanceof DeferrableProvider) {
            foreach ($instance->provides() as $provided) {
                unset($this->deferredServices[$provided]);
            }
        }
    }

    /**
     * Reset all PER-REQUEST state so a persistent worker doesn't leak one request's
     * state into the next (WRK). The worker loop calls this between requests. App-level
     * singletons (db.connection, config) are preserved; only request-scoped identity,
     * routing, facade-resolved instances, and scoped bindings are cleared.
     */
    public function flushRequestState(): void
    {
        \Eyika\Atom\Framework\Support\Auth\Auth::flush();          // WRK-03 identity leak
        \Eyika\Atom\Framework\Http\Route::flushRequestState();    // WRK-05 route table
        \Eyika\Atom\Framework\Support\Validator::flush();         // WRK-06 validation state
        \Eyika\Atom\Framework\Support\Facade\Facade::clearResolvedInstances(); // WRK-04
        $this->forgetScopedInstances();                            // WRK-09 scoped bindings
    }

    protected function bootProviders(): void
    {
        $this->loadedProviders()->each(function (&$index, ServiceProvider &$instance) {
            $instance->boot();
        });
    }
}
