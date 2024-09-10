<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\Storage as StorageStorage;

/**
 * @method static StorageStorage drive(string $driver)
 * @method static StorageStorage disk(string $disk)
 * @method static StorageStorage cache(CacheInterface $cache)
 * @method static StorageStorage extend(string $driverName, callable $callback)
 * @method static string get(string $path)
 * @method static int put(string $path, string $contents, $options = [])
 * @method static int putFile(string $path, File $file, $options = [])
 * @method static int putFileAs(string $path, File $file, string $name, $options = [])
 * @method static string getVisibility(string $path)
 * @method static bool setVisibility(string $path, string $visibility)
 * @method static int prepend(string $path, string $data)
 * @method static int append(string $path, string $data)
 * @method static bool delete(string $path, $isfullpath = false)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string url(string $disk)
 * @method static string temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = [])
 * @method static array files(string $directory = '', bool $recursive = false)
 * @method static array allFiles(string $directory = '')
 * @method static array directories(string $directory = '')
 * @method static array allDirectories(string $directory = '')
 * @method static bool makeDirectory(string $path, $visibility = null, bool $recursive = false, bool $force = false)
 * @method static bool deleteDirectory(string $directory)
 * @method static bool cleanDirectory(string $directory)
 * @method static bool exists($path, $isfullpath = false)
 */
class Storage extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'storage';
    }
}
