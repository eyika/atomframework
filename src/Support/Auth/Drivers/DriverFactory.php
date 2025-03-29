<?php
namespace Eyika\Atom\Framework\Support\Auth\Drivers;

use Eyika\Atom\Framework\Support\Auth\Contracts\DriverInterface;

class DriverFactory
{
    protected static array $handlers = [];

    /**
     * Register a new driver handler.
     *
     * @param string $driver
     * @param string $handlerClass
     * @return void
     */
    public static function registerHandler(string $driver, string $handlerClass): void
    {
        self::$handlers[$driver] = $handlerClass;
    }

    /**
     * Get the handler for the given driver.
     *
     * @param string $driver
     * @return DriverInterface
     *
     * @throws \InvalidArgumentException
     */
    public static function getHandler(string $driver, string $provider): DriverInterface
    {
        if (empty(self::$handlers)) {
            self::registerHandlers();
        }
        if (!isset(self::$handlers[$driver])) {
            throw new \InvalidArgumentException("Driver [{$driver}] is not supported.");
        }
        $handlerClass = self::$handlers[$driver];

        return new $handlerClass($provider);
    }

    protected static function registerHandlers()
    {
        foreach (config()->get('auth.auth_drivers', []) as $name => $class) {
            self::registerHandler($name, $class);
        }
    }
}
