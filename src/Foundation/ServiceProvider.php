<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Arrayable;

abstract class ServiceProvider
{
    protected ApplicationInterface $app;

    protected array $publishables = [];
    protected array $facades = [];

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
        if (!isset($this->publishables[$tag])) {
            $this->publishables[$tag] = [];
        }

        $this->publishables[$tag] = array_merge($this->publishables[$tag], $paths);
    }

    /**
     * Retrieve all publishable paths by tag.
     */
    public function getPublishables(string|null $tag = null): array
    {
        return $tag ? ($this->publishables[$tag] ?? []) : $this->publishables;
    }

    /**
     * Publish all registered assets.
     */
    public static function publishAll(string|null $tag = null, string|null $providerClass = null, $force = false): bool
    {
        if (!empty($providerClass)) {
            return static::publishAssets($providerClass, $tag, $force);
        }
    
        foreach (config('app.providers', []) as $providerClass) {
            static::publishAssets($providerClass, $tag, $force);
        }
        return true;
    }

    public static function publishAssets(string $providerClass, $tag, $force): bool
    {
        $app = app();
        /** @var ServiceProvider $provider */
        $provider = new $providerClass($app);

        $publishables = $tag ? $provider->getPublishables($tag) : $provider->getPublishables();

        foreach ($publishables as $source => $destination) {
            if (file_exists($destination) && !$force) {
                // $this->warn("File already exists: $destination");
                return true;
            } else if (file_exists($destination) && $force) {
                copy($source, $destination);
                return true;
            } else {
                mkdir(dirname($destination), 0777, true);
                copy($source, $destination);
            }
        }

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
