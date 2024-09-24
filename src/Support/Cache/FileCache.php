<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use App\Contracts\AbstractFile;
use Eyika\Atom\Framework\Support\Storage\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class FileCache implements CacheInterface
{
    protected $file;
    protected $cacheDirectory;
    protected $prefix;

    public function __construct()
    {
        $cacheDirectory = config('cache.stores.file.path');
        $this->prefix = config('cache.prefix');

        if (!file_exists($cacheDirectory))
            mkdir($cacheDirectory, 0775, true);

        $adapter = new LocalFilesystemAdapter(
            $cacheDirectory,
            PortableVisibilityConverter::fromArray([
                'file' => [
                    'public' => 0644,
                    'private' => 0664,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0775,
                ],
            ]),
            LOCK_EX,
            LocalFilesystemAdapter::SKIP_LINKS
        );

        $this->file = new File(new Filesystem($adapter));

        // Ensure the cache directory exists
        // if (!$this->file->isDirectory($cacheDirectory)) {
        //     $this->file->makeDirectory($cacheDirectory, 0755, true);
        // }

        $this->cacheDirectory = rtrim($cacheDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the cache file path based on the key.
     */
    protected function getCacheFilePath(string $key): string
    {
        return $this->cacheDirectory . md5($key) . '.cache';
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
        $filePath = $this->getCacheFilePath($key);

        if (!$this->file->exists($filePath)) {
            return null;
        }

        $data = $this->file->get($filePath);

        // Decode the data
        $cacheItem = unserialize($data);

        // Check if the cache item is still valid
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
            // Cache expired, delete the file
            $this->delete($key);
            return null;
        }

        return $cacheItem['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value, int $ttl = 3600): bool
    {
        $filePath = $this->getCacheFilePath($key);
        $expiresAt = ($ttl === 0) ? 0 : time() + $ttl;

        $cacheItem = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        // Serialize and write to file
        return $this->file->put($filePath, serialize($cacheItem)) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);

        if ($this->file->exists($filePath)) {
            return $this->file->delete($filePath);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = $this->file->files($this->cacheDirectory);

        foreach ($files as $file) {
            $this->file->delete($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);

        if (!$this->file->exists($filePath)) {
            return false;
        }

        $data = $this->file->get($filePath);
        $cacheItem = unserialize($data);

        // Check if the cache has expired
        return $cacheItem['expires_at'] === 0 || $cacheItem['expires_at'] > time();
    }
}
