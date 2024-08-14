<?php

namespace Basttyy\FxDataServer\libs\Storage;

use Aws\S3\S3Client;
use Basttyy\FxDataServer\libs\Interfaces\CacheInterface;
use stdClass;

class Storage
{
    protected string $disk;
    protected CacheInterface $cache;

    protected static $disks = [
        'local' => __DIR__ . '/storage/local',
        's3' => [
            'bucket' => 'your-s3-bucket-name',
            'region' => 'your-s3-region',
            'key' => 'your-s3-access-key',
            'secret' => 'your-s3-secret-key',
        ]
    ];

    public function __construct($disk = 'local', CacheInterface $cache = null)
    {
        $this->disk = $disk;
        $this->cache = $cache ?? new RedisCache();
    }

    public function disk(string $disk)
    {
        return $this->disk = $disk;
    }

    public static function init($disk = 'local', CacheInterface $cache = null)
    {
        return new static($disk, $cache);
    }

    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function put($path, $contents, $isfullpath = false)
    {
        $path = $isfullpath ? $path : $this->getFullPath($path);
        $result = false;

        if ($this->disk === 'local') {
            $this->ensureDirectoryExists(dirname($path));
            $result = file_put_contents($path, $contents) !== false;
        } elseif ($this->disk === 's3') {
            $s3Client = $this->getS3Client();
            // $result = $s3Client->putObject([
            //     'Bucket' => self::$disks['s3']['bucket'],
            //     'Key' => $path,
            //     'Body' => $contents,
            // ]);
        }

        if ($result) {
            $this->cache->set($this->cacheKey($path), $contents);
        }

        return $result;
    }

    public static function putStatic($path, $contents, $isfullpath = false, $disk = 'local')
    {
        return static::init($disk)->put($path, $contents, $isfullpath);
    }

    public function get($path, $isfullpath = false)
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return $cached;
        }

        $path = $isfullpath ? $path : $this->getFullPath($path);
        $contents = false;

        if ($this->disk === 'local') {
            $contents = file_exists($path) ? file_get_contents($path) : false;
        } elseif ($this->disk === 's3') {
            $s3Client = $this->getS3Client();
            // $result = $s3Client->getObject([
            //     'Bucket' => self::$disks['s3']['bucket'],
            //     'Key' => $path,
            // ]);
            // $contents = (string)$result['Body'];
        }

        if ($contents !== false) {
            $this->cache->set($this->cacheKey($path), $contents);
        }

        return $contents;
    }

    public static function getStatic($path, $isfullpath = false, $disk = 'local')
    {
        return static::init($disk)->get($path, $isfullpath);
    }

    public function delete($path, $isfullpath = false)
    {
        $path = $isfullpath ? $path : $this->getFullPath($path);
        $result = false;

        if ($this->disk === 'local') {
            $result = file_exists($path) ? unlink($path) : false;
        } elseif ($this->disk === 's3') {
            // $s3Client = $this->getS3Client();
            // $result = $s3Client->deleteObject([
            //     'Bucket' => self::$disks['s3']['bucket'],
            //     'Key' => $path,
            // ]);
        }

        if ($result) {
            $this->cache->delete($this->cacheKey($path));
        }

        return $result;
    }

    public static function deleteStatic($path, $isfullpath = false, $disk = 'local')
    {
        return static::init($disk)->delete($path, $isfullpath);
    }

    public function exists($path, $isfullpath = false)
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return true;
        }

        $path = $isfullpath ? $path : $this->getFullPath($path);
        $exists = false;

        if ($this->disk === 'local') {
            $exists = file_exists($path);
        } elseif ($this->disk === 's3') {
            // $s3Client = $this->getS3Client();
            // $result = $s3Client->doesObjectExist(self::$disks['s3']['bucket'], $path);
            // $exists = $result;
        }

        return $exists;
    }

    public static function existsStatic($path, $isfullpath = false, $disk = 'local')
    {
        return static::init($disk)->exists($path, $isfullpath);
    }

    public function size($path, $isfullpath = false)
    {
        $cached = $this->cache->get($this->cacheKey($path));
        if ($cached) {
            return strlen(serialize($cached));
        }

        $fullPath = $isfullpath ? $path : $this->getFullPath($path);
        $size = 0;

        if ($this->disk === 'local') {
            $size = filesize($fullPath);
        } elseif ($this->disk === 's3') {
            // $s3Client = $this->getS3Client();
            // $result = $s3Client->doesObjectExist(self::$disks['s3']['bucket'], $path);
            // $size = $result;
        }

        return $size;
    }
    public static function sizeStatic($path, $isfullpath = false, $disk = 'local')
    {
        return static::init($path)->size($disk, $isfullpath);
    }

    protected function getFullPath($path)
    {
        if ($this->disk === 'local') {
            $basePath = self::$disks['local'];
            return rtrim($basePath, '/') . '/' . ltrim($path, '/');
        }

        return $path; // For S3, the full path is managed by the S3 client.
    }

    protected function getS3Client()
    {
        // return new S3Client([
        //     'version' => 'latest',
        //     'region' => self::$disks['s3']['region'],
        //     'credentials' => [
        //         'key' => self::$disks['s3']['key'],
        //         'secret' => self::$disks['s3']['secret'],
        //     ],
        // ]);
        return new stdClass;
    }

    protected function ensureDirectoryExists($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    protected function cacheKey($path)
    {
        return $this->disk . ':' . $path;
    }
}
