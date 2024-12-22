<?php

namespace Eyika\Atom\Framework\Support\Storage;

use Eyika\Atom\Framework\Exceptions\Storage\InvalidDiskException;
use Eyika\Atom\Framework\Exceptions\Storage\InvalidStorageAdapterException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Cache\Cache;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Storage\Contracts\CustomStorageAdapterCallback;
use League\Flysystem\FilesystemAdapter;

class Storage
{
    protected $flysystemCompatibleAdapters = ['local', 's3', 'google', 'azure', 'ftp', 'sftp'];
    protected $customDriverAdapters = [];
    protected string $disk;
    protected array $disks;
    protected CacheInterface $cache;
    protected File $file;

    public function __construct(string|null $disk = null, CacheInterface|null $cache = null)
    {
        $this->disks = config('filesystems.disks');
        $disks = Arr::keys($this->disks);
        if (!empty($disk) && !Arr::keyExists($disks, $disk)) {
            throw new InvalidDiskException('the given disk deos not exist in filesystem config');
        }
        $this->disk = $disk ? $disk : config('filesystems.default');
        $this->cache = $cache ?? new Cache();
        $this->file = new File(disk: $this->disk);
    }

    public function drive(string $driver): self
    {
        if (Arr::keyExists($this->customDriverAdapters, $driver)) {
            $this->file->setFileSystemAdapter($this->customDriverAdapters[$driver]);
        } else if (Arr::keyExists($this->flysystemCompatibleAdapters, $driver)) {
            $this->file->setFileSystemAdapter($this->customDriverAdapters[$driver]);
        } else {
            throw new InvalidDiskException();
        }
        
        return $this;
    }

    public function disk(string $disk): self
    {
        if (!Arr::keyExists($this->disks, $disk)) {
            throw new InvalidDiskException();
        }
        $this->file->setDisk($disk);

        return $this;
    }

    public function cache(CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    public function extend(string $driverName, CustomStorageAdapterCallback $callback): self
    {
        if (Arr::keyExists($this->customDriverAdapters, $driverName)) {
            throw new InvalidStorageAdapterException("a custom driver with the name $driverName is already registered");
        }

        $adapter = $callback(Facade::getFacadeApplication(), $this->disk);

        if (! $adapter instanceof FilesystemAdapter) {
            throw new InvalidStorageAdapterException();
        }

        $this->customDriverAdapters[$driverName] = $adapter;

        return $this;
    }

    public function get(string $path): string
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return $cached;
        }

        // $path = $isfullpath ? $path : $this->getFullPath($path);
        $contents = false;

        $contents = $this->file->get($path);

        if ($contents !== false) {
            $this->cache->set($this->cacheKey($path), $contents);
        }

        return $contents;
    }

    public function put(string $path, string $contents, $options = []): int
    {
        // $path = $isfullpath ? $path : $this->getFullPath($path);
        $result = false;
        $result = $this->file->put($path, $contents, $options['lock'] ?? false);

        if ($result) {
            $this->cache->set($this->cacheKey($path), $contents);
        }

        return $result;
    }

    public function putFile(string $path, File $file, $options = [])
    {
        // $path = $isfullpath ? $path : $this->getFullPath($path);
        $result = false;
        $result = $this->file->put($path, $file->contents(), $options['lock'] ?? false);

        if ($result) {
            $this->cache->set($this->cacheKey($path), $file->contents());
        }

        return $result;
    }

    public function putFileAs(string $path, File $file, string $name, $options = [])
    {
        // $path = $isfullpath ? $path : $this->getFullPath($path);
        $result = false;
        $result = $this->file->put($path.$name, $file->contents(), $options['lock'] ?? false);

        if ($result) {
            $this->cache->set($this->cacheKey($path), $file->contents());
        }

        return $result;
    }

    public function getVisibility(string $path): string
    {
        return $this->file->visibility($path);
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        return $this->file->setVisibility($path, $visibility);
    }

    public function prepend(string $path, string $data): int
    {
        return $this->file->prepend($path, $data);
    }

    public function append(string $path, string $data)
    {
        return $this->file->append($path, $data);
    }

    public function delete($path, $isfullpath = false): bool
    {
        // $path = $isfullpath ? $path : $this->getFullPath($path);
        // $result = false;

        $result = $this->file->delete($path);

        if ($result) {
            $this->cache->delete($this->cacheKey($path));
        }

        return $result;
    }

    public function copy(string $from, string $to): bool
    {
        return $this->file->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->file->move($from, $to);
    }

    public function size($path)
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return strlen(serialize($cached));
        }

        $size = 0;

        $size = $this->file->size($path);

        return $size;
    }

    public function lastModified(string $path): int
    {
        return $this->file->lastModified($path);
    }

    public function url(string $path): string
    {
        //TODO:: implement for adapters that are not supported by flysystem by default
        $adapter = $this->file->getFileSystemAdapter();

        return $adapter->publicUrl($path);
    }

    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        //TODO:: implement for adapters that are not supported by flysystem by default
        $temporaryUrl = $this->file->getFileSystemAdapter()->temporaryUrl($path, $expiration);

        return $temporaryUrl;
    }

    public function files(string $directory = '', bool $recursive = false): array
    {
        return $recursive ? $this->file->allFiles($directory) : $this->file->files($directory);
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->file->allFiles($directory);
    }
    
    public function directories(string $directory = ''): array
    {
        return $this->file->directories($directory);
    }

    public function allDirectories(string $directory = ''): array
    {
        return $this->file->allDirectories($directory);
    }

    public function makeDirectory(string $path, $visibility = null, bool $recursive = false, bool $force = false): bool
    {
        return $this->file->makeDirectory($path, $visibility, $recursive, $force);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->file->deleteDirectory($directory);
    }

    public function cleanDirectory(string $directory): bool
    {
        return $this->file->cleanDirectory($directory);
    }

    public function exists($path, $isfullpath = false)
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return true;
        }

        // $path = $isfullpath ? $path : $this->getFullPath($path);
        $exists = false;

        return $this->file->exists($path);

        return $exists;
    }

    protected function getFullPath($path)
    {
        if (Arr::exists($this::$flysystemCompatibleAdapters, $this->disks[$this->disk]['driver'])) {
            return ; // For S3, the full path is managed by the S3 client.
        }

        $basePath = self::$disk['root'];
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }

    protected function ensureDirectoryExists($directory)
    {
        return $this->file->ensureDirectoryExists($directory);
    }

    protected function cacheKey($path)
    {
        return $this->disk . ':' . $path;
    }
}
