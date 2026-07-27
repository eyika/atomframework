<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Config;
use Eyika\Atom\Framework\Support\Facade\Console;
use Eyika\Atom\Framework\Support\Facade\Facade;

abstract class ServiceProvider
{
    protected ApplicationInterface $app;

    /** @var array<string, array<string,string>> Publishable groups: tag => [source => destination]. */
    protected array $publishables = [];
    protected array $facades = [];

    // Package-development registries (PKG-01). Providers push into these during
    // register()/boot(); the consuming subsystems (migrator, console kernel, views)
    // read them back. Append-only per boot; flushable for tests / worker reload.
    protected static array $migrationPaths = [];
    protected static array $viewNamespaces = [];
    protected static array $translationNamespaces = [];
    protected static array $packageCommands = [];

    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Register services into the container.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any necessary functionality after services have been registered.
     */
    public function boot(): void
    {
        // This method can be overridden in child classes if needed.
    }

    /**
     * Register files to be published (source => destination) under a tag. Repeated
     * calls with the same tag accumulate. (PKG-05: was pushing onto a list while
     * getPublishables read it as a tag-keyed map, and Arrayable's []-access is a
     * no-op — so tag filtering never worked.)
     */
    protected function publishes(array $paths, string $tag = 'default'): void
    {
        $this->publishables[$tag] = array_merge($this->publishables[$tag] ?? [], $paths);
    }

    /**
     * Retrieve publishable paths as an Arrayable of tag => [source => destination].
     * With a $tag, only that group is returned.
     */
    public function getPublishables(string|null $tag = null): Arrayable
    {
        if ($tag === null) {
            return new Arrayable($this->publishables);
        }

        return new Arrayable(
            isset($this->publishables[$tag]) ? [$tag => $this->publishables[$tag]] : []
        );
    }

    /**
     * Publish all registered assets.
     */
    public static function publishAll(string|null $tag = null, string|null $providerClass = null, $force = false): bool
    {
        if (!empty($providerClass)) {
            return static::publishAssets($providerClass, $tag, $force);
        }
    
        $providers = Facade::getFacadeApplication()->loadedProviders();

        $providers->each(function ($index, ServiceProvider $provider) use ($tag, $force) {
            static::publishAssets($provider, $tag, $force);
        });

        return true;
    }

    public static function publishAssets(string|ServiceProvider $provider, $tag, $force): bool
    {
        $app = app();
        if (!$provider instanceof ServiceProvider)
            $provider = new $provider($app);
        /** @var ServiceProvider $provider */

        $publishables = $tag ? $provider->getPublishables($tag) : $provider->getPublishables();

        $publishables->each(function ($tag, $locations) use ($force) {
            foreach ($locations as $source => $destination) {
                if (file_exists($destination) && !$force) {
                    Console::comment("File already exists: $destination");
                    return true;
                } else if (file_exists($destination) && $force) {
                    copy($source, $destination);
                    return true;
                } else {
                    if (!file_exists(dirname($destination))) {
                        mkdir(dirname($destination), 0777, true);
                    }
                    copy($source, $destination);
                }
            }
        });

        return true;
    }

    public function registerFacades(array $facades): void
    {
        foreach ($facades as $alias => $class) {
            $this->facades[$alias] = $class;
        }
    }

    public function getFacades(): array
    {
        return $this->facades;
    }

    // ---------------------------------------------------------------------------
    // Package development helpers (PKG-01)
    // ---------------------------------------------------------------------------

    /**
     * Register a package's routes. Requiring the file executes its Route::* calls;
     * providers boot before route dispatch, so package routes join the table in time.
     */
    protected function loadRoutesFrom(string $path): void
    {
        require_once $path;
    }

    /**
     * Merge a package's default config under $key so the host app's config wins.
     * (App/existing keys override the package defaults.)
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!is_file($path)) {
            return;
        }
        $packageConfig = require $path;
        if (!is_array($packageConfig)) {
            return;
        }
        $existing = config($key, []);
        Config::set($key, array_merge($packageConfig, is_array($existing) ? $existing : []));
    }

    /**
     * Register one or more migration directories so `migrate` picks up the package's
     * migrations alongside the app's (see Db\Migrate::gatherMigrations()).
     */
    protected function loadMigrationsFrom(string|array $paths): void
    {
        foreach ((array) $paths as $path) {
            if (!in_array($path, self::$migrationPaths, true)) {
                self::$migrationPaths[] = $path;
            }
        }
    }

    /** Directories registered via loadMigrationsFrom(), read by the migrate commands. */
    public static function migrationPaths(): array
    {
        return self::$migrationPaths;
    }

    /**
     * Register view directories under a namespace so `view('ns::name')` resolves
     * (consumed by the namespaced-view resolver, PKG-09).
     */
    protected function loadViewsFrom(string|array $paths, string $namespace): void
    {
        foreach ((array) $paths as $path) {
            self::$viewNamespaces[$namespace][] = $path;
        }
    }

    /** namespace => paths[] registered via loadViewsFrom(). */
    public static function viewNamespaces(): array
    {
        return self::$viewNamespaces;
    }

    /**
     * Register a translations directory under a namespace. Stored for the translator
     * subsystem (not yet shipped) — the hint is forward-compatible today.
     */
    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        self::$translationNamespaces[$namespace] = $path;
    }

    /** namespace => path registered via loadTranslationsFrom(). */
    public static function translationNamespaces(): array
    {
        return self::$translationNamespaces;
    }

    /**
     * Register a package's console commands (class-strings). The console kernel picks
     * these up in addition to the framework + app command directories.
     */
    protected function commands(array|string $commands): void
    {
        foreach ((array) $commands as $command) {
            if (!in_array($command, self::$packageCommands, true)) {
                self::$packageCommands[] = $command;
            }
        }
    }

    /** Command class-strings registered via commands(), read by the console kernel. */
    public static function packageCommands(): array
    {
        return self::$packageCommands;
    }

    /**
     * Reset all package registries (test isolation / worker reload).
     */
    public static function flushPackageRegistrations(): void
    {
        self::$migrationPaths = [];
        self::$viewNamespaces = [];
        self::$translationNamespaces = [];
        self::$packageCommands = [];
    }
}
