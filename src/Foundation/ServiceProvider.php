<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Facade\Console;
use Eyika\Atom\Framework\Support\Facade\Facade;

abstract class ServiceProvider
{
    protected ApplicationInterface $app;

    protected Arrayable $publishables;
    protected array $facades = [];

    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
        $this->publishables = new Arrayable();
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
     * Return default framework providers.
     */
    public static function defaultProviders(): Arrayable
    {
        return new Arrayable([
            \App\Providers\CacheServiceProvider::class,
            \App\Providers\RouteServiceProvider::class,
            \App\Providers\ConsoleServiceProvider::class,
            \App\Providers\EventServiceProvider::class,
            \App\Providers\ViewServiceProvider::class,
            \App\Providers\DatabaseServiceProvider::class,
        ]);
    }

    /**
     * Register files to be published with an optional tag.
     */
    protected function publishes(array $paths, string $tag = 'default'): void
    {
        $this->publishables->push([$tag => $paths]);
    }

    /**
     * Retrieve all publishable paths by tag.
     */
    public function getPublishables(string|null $tag = null): Arrayable
    {
        return $tag ? new Arrayable($this->publishables[$tag] ? [$tag => $this->publishables[$tag]] : []) : $this->publishables;
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
}
