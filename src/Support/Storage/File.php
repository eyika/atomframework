<?php

namespace Eyika\Atom\Framework\Support\Storage;

use Exception;

class File
{
    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public static function exists($path)
    {
        return file_exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     * @throws Exception
     */
    public static function get($path)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return file_get_contents($path);
    }

    /**
     * Put the contents into a file.
     *
     * @param string $path
     * @param string $contents
     * @param bool $lock
     * @return int|bool
     */
    public static function put($path, $contents, $lock = false)
    {
        return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Delete the file at a given path.
     *
     * @param string $path
     * @return bool
     */
    public static function delete($path)
    {
        if (static::exists($path)) {
            return unlink($path);
        }

        return false;
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function copy($from, $to)
    {
        return copy($from, $to);
    }

    /**
     * Copy a directory to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach (static::files($source) as $file) {
            $src = $source . DIRECTORY_SEPARATOR . $file;
            $dest = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($src)) {
                static::copyDirectory($src, $dest);
            } else {
                copy($src, $dest);
            }
        }
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function move($from, $to)
    {
        return rename($from, $to);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     * @return int
     * @throws Exception
     */
    public static function size($path)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return filesize($path);
    }

    /**
     * Get the last modified time of a file.
     *
     * @param string $path
     * @return int
     * @throws Exception
     */
    public static function lastModified($path)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return filemtime($path);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @param bool $force
     * @return bool
     */
    public static function makeDirectory($path, $mode = 0755, $recursive = false, $force = false)
    {
        if ($force && static::exists($path)) {
            return true;
        }

        return mkdir($path, $mode, $recursive);
    }

    /**
     * Delete a directory.
     *
     * @param string $path
     * @return bool
     */
    public static function deleteDirectory($path)
    {
        if (static::exists($path) && is_dir($path)) {
            return rmdir($path);
        }

        return false;
    }

    /**
     * Get the files in a directory.
     *
     * @param string $directory
     * @return array
     */
    public static function files($directory)
    {
        if (!is_dir($directory)) {
            return [];
        }

        return array_diff(scandir($directory), ['.', '..']);
    }

    /**
     * Get the file extension.
     *
     * @param string $path
     * @return string
     */
    public static function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Change the file mode.
     *
     * @param string $path
     * @param int $mode
     * @return bool
     * @throws Exception
     */
    public static function chmod($path, $mode)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return chmod($path, $mode);
    }

    /**
     * Create a symbolic link.
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    public static function symlink($target, $link)
    {
        return symlink($target, $link);
    }

    /**
     * Get the absolute path of a file.
     *
     * @param string $path
     * @return string|bool
     */
    public static function realpath($path)
    {
        return realpath($path);
    }

    /**
     * Determine if the path is a directory.
     *
     * @param string $path
     * @return bool
     */
    public static function isDirectory($path)
    {
        return is_dir($path);
    }

    /**
     * Determine if the path is writable.
     *
     * @param string $path
     * @return bool
     */
    public static function isWritable($path)
    {
        return is_writable($path);
    }

    /**
     * Determine if the path is readable.
     *
     * @param string $path
     * @return bool
     */
    public static function isReadable($path)
    {
        return is_readable($path);
    }

    /**
     * Get the MIME type of a file.
     *
     * @param string $path
     * @return string|false
     * @throws Exception
     */
    public static function mimeType($path)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return mime_content_type($path);
    }

    /**
     * Get the filename without extension.
     *
     * @param string $path
     * @return string
     */
    public static function filename($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Create a hard link.
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    public static function link($target, $link)
    {
        return link($target, $link);
    }

    /**
     * Get the basename of a file.
     *
     * @param string $path
     * @return string
     */
    public static function basename($path)
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Get the directory name of a file.
     *
     * @param string $path
     * @return string
     */
    public static function dirname($path)
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Get the type of a file.
     *
     * @param string $path
     * @return string|false
     */
    public static function fileType($path)
    {
        return filetype($path);
    }

    /**
     * Check if a file is a symbolic link.
     *
     * @param string $path
     * @return bool
     */
    public static function isSymlink($path)
    {
        return is_link($path);
    }

    /**
     * Read the link to which a symbolic link points.
     *
     * @param string $path
     * @return string|false
     */
    public static function readlink($path)
    {
        return readlink($path);
    }

    /**
     * Rename a file or directory.
     *
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public static function rename($oldName, $newName)
    {
        return rename($oldName, $newName);
    }
}
