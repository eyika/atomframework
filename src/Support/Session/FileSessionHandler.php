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
        $this->savePath = config('session.files', sys_get_temp_dir()); // Default to system temp dir if not set
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0744, true);
        }
    }

    public function open($savePath, $sessionName): bool
    {
        if (!is_dir($this->savePath)) {
            return mkdir($this->savePath, 0744, true);
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

        // @unlink avoids the "no such file" warning when a concurrent
        // request (or session GC) already removed the file between the
        // existence check and our own unlink — classic TOCTOU.
        if (@unlink($file)) {
            return true;
        }

        // If the file is gone for any reason, treat destroy as successful.
        return !file_exists($file);
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

        if (!file_exists($file)) {
            return '';
        }

        // Shared lock so we don't read a file mid-write. file_get_contents
        // doesn't take a lock by itself, so we open+flock manually.
        $fh = @fopen($file, 'rb');
        if ($fh === false) {
            return '';
        }

        $data = '';
        if (flock($fh, LOCK_SH)) {
            $data = stream_get_contents($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);

        return $data === false ? '' : $data;
    }

    public function write($sessionId, $sessionData): bool
    {
        $file = $this->filePath($sessionId);

        // Atomic write: write to a tmp file in the same directory then
        // rename over the target. rename() is atomic on the same filesystem,
        // so concurrent readers never see a half-written session file —
        // which was causing PHP to emit "Failed to decode session object".
        $tmp = $file . '.tmp.' . bin2hex(random_bytes(6));
        if (@file_put_contents($tmp, $sessionData, LOCK_EX) === false) {
            return false;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }

        return true;
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
