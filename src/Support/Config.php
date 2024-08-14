<?php

namespace Basttyy\FxDataServer\libs;

use Basttyy\FxDataServer\libs\Interfaces\CacheInterface;
use Basttyy\FxDataServer\libs\Storage\DbCache;
use Hybridauth\Exception\NotImplementedException;
use InvalidArgumentException;

class Config
{
    protected static $config = [];
    protected static CacheInterface $cache;
    protected static $cacheEnabled = false;
    protected static $cache_prefix = 'config__';

    public function __construct()
    {
    }

    /**
     * Load all configuration files.
     */
    public static function loadConfigFiles($directory = __DIR__)
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Config directory does not exist: $directory");
        }

        foreach (glob($directory . '/*.php') as $file) {
            $key = basename($file, '.php');
            self::$config[$key] = require $file;
        }
    }

    /**
     * Enable or disable caching.
     *
     * @param bool $enabled
     */
    public static function setCache(CacheInterface $cache = null): self
    {
        self::$cacheEnabled = true;
        self::$cache = $cache ?? new DbCache();

        return new static();
    }

    /**
     * Retrieve a configuration value using dot notation.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        if (self::$cacheEnabled && $value = self::$cache->get(self::$cache_prefix . $key)) {
            return $value;
        }

        $segments = explode('.', $key);
        $config = self::$config;

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        if (self::$cacheEnabled) {
            self::$cache->set(self::$cache_prefix . $key, $config);
        }

        return $config;
    }

    /**
     * Set a configuration value.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value)
    {
        $segments = explode('.', $key);
        $config = &self::$config;

        foreach ($segments as $segment) {
            if (!isset($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;

        if (self::$cacheEnabled) {
            self::$cache->set(self::$cache_prefix . $key, $value);
        }
    }

    /**
     * Clear the cache.
     */
    public static function clearCache()
    {
        throw new NotImplementedException('clear feature is not yet implemented');
        // this should be reimplemented to only clear the config cache items
        // self::$cache->clear();
    }
}

// Example usage

// // Load configuration files from the 'config' directory
// Config::loadConfigFiles(__DIR__ . '/config');

// // Enable caching
// Config::enableCache(true);

// // Retrieve configuration values
// $storagePath = Config::get('logviewer.storage_path', '/default/path');
// echo $storagePath; // Outputs: /path/to/storage

// $nonExistentConfig = Config::get('nonexistent.config', 'default_value');
// echo $nonExistentConfig; // Outputs: default_value

// // Set a configuration value
// Config::set('app.debug', true);

// // Retrieve the newly set configuration value
// $appDebug = Config::get('app.debug');
// echo $appDebug; // Outputs: 1

// // Clear the cache
// Config::clearCache();
