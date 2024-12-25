<?php

namespace Eyika\Atom\Framework\Support\Session;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface, SessionIdInterface, SessionUpdateTimestampHandlerInterface
{
    private $savePath;

    public function __construct()
    {
        $this->savePath = config('session.file_path', sys_get_temp_dir()); // Default to system temp dir if not set
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }
    }

    public function open($savePath, $sessionName): bool
    {
        $this->savePath = $savePath ?: $this->savePath;

        if (!is_dir($this->savePath)) {
            return mkdir($this->savePath, 0777, true);
        }

        return true;
    }

    public function close(): bool
    {
        return true; // No cleanup required for file storage
    }

    public function destroy($sessionId): bool
    {
        $file = $this->filePath($sessionId);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function gc($maxLifetime): int|false
    {
        $files = glob("{$this->savePath}/*");

        foreach ($files as $file) {
            if (filemtime($file) + $maxLifetime < time() && is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function read($sessionId): string
    {
        $file = $this->filePath($sessionId);

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return '';
    }

    public function write($sessionId, $sessionData): bool
    {
        $file = $this->filePath($sessionId);

        return file_put_contents($file, $sessionData) !== false;
    }

    public function create_sid(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function validateId($sessionId): bool
    {
        return file_exists($this->filePath($sessionId));
    }

    public function updateTimestamp($sessionId, $sessionData): bool
    {
        return $this->write($sessionId, $sessionData);
    }

    private function filePath($sessionId): string
    {
        return "{$this->savePath}/sess_$sessionId";
    }
}
