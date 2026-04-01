<?php

namespace Eyika\Atom\Framework\Support\Cache;

use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Storage\File;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class FileCache implements CacheInterface
{
    protected $file;
    protected $prefix;
    protected array $deferredItems = [];

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

    }

    /**
     * Get the cache file path relative to the cache root directory.
     */
    protected function getCacheFilePath(string $key): string
    {
        return md5($key) . '.cache';
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key): CacheItem
    {
        $filePath = $this->getCacheFilePath($key);

        if (!$this->file->exists($filePath)) {
            return new CacheItem($key, null, false);
        }

        $data = $this->file->get($filePath);

        // Decode the data
        $cacheItem = unserialize($data);

        // Check if the cache item is still valid
        if ($cacheItem['expires_at'] !== 0 && $cacheItem['expires_at'] < time()) {
            // Cache expired, delete the file
            $this->deleteItem($key);
            return new CacheItem($key, null, false);
        }

        return new CacheItem($key, $cacheItem['value'], true);
    }

    /**
     * @param string[] $keys
     * 
     * @return CacheItemInterface[]
     * 
     * @throws InvalidArgumentException
     */
    public function getItems($keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
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

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $files = $this->file->files('');

        foreach ($files as $file) {
            $this->file->delete($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);

        if ($this->file->exists($filePath)) {
            return $this->file->delete($filePath);
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteItems($keys = []): bool
    {
        foreach ($keys as $key) {
            if (!$this->deleteItem($key))
                return false;
        }
        return true;
    }

    /**
     * @param CacheItem $item
     */
    public function save($item): bool
    {
        $filePath = $this->getCacheFilePath($item->getKey());
        $expiresAt = ($item->getExpiration() === 0) ? 0 : time() + $item->getExpiration();

        $cacheItem = [
            'value' => $item->get(),
            'expires_at' => $expiresAt,
        ];

        // Serialize and write to file
        return $this->file->put($filePath, serialize($cacheItem)) !== false;
    }

    /**
     * @param CacheItem $item
     */
    public function setItem($item): bool
    {
        return $this->save($item);
    }

    /**
     * @param CacheItem $item
     */
    public function saveDeferred($item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }

        $this->deferredItems[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        $allSaved = true;

        foreach ($this->deferredItems as $key => $item) {
            if (!$this->save($item)) {
                $allSaved = false;
            }
        }

        // Clear deferred items after attempting to save
        $this->deferredItems = [];

        return $allSaved;
    }
}
