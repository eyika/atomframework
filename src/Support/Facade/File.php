<?php

namespace Eyika\Atom\Framework\Support\Facade;

use League\Flysystem\Filesystem;

/**
 * @method static void setFileSystemAdapter(FilesystemAdapter $filesystem)
 * @method static Filesystem getFileSystemAdapter()
 * @method static void setDisk(string $disk)
 * @method static void setContents(string $contents)
 * @method static string contents()
 * @method static bool `exists(string $path)
 * @method static string get(string $path)
 * @method static int put(string $path, string $contents, bool $lock = false)
 * @method static bool replace(string $path, string $contents)
 * @method static int prepend(string $path, string $data)
 * @method static int append(string $path, string $data)
 * @method static int upload(string $tempPath, string $path)
 * @method static bool delete(string $path)
 * @method static bool move(string $from, string $to)
 * @method static string name(string $path)
 * @method static string basename(string $path)
 * @method static string dirname(string $path)
 * @method static string extension(string $path)
 * @method static string guessExtension(string $path)
 * @method static string type(string $path)
 * @method static string mimeType(string $path)
 * @method static string visibility(string $path)
 * @method static string setVisibility(string $path, string $visibility)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static bool isDirectory(string $path)
 * @method static bool isFile(string $path)
 * @method static bool isReadable(string $path)
 * @method static bool isWriteable(string $path)
 * @method static array glob(string $pattern, int $flags = 0)
 * @method static array files(string $directory, bool $hidden = false)
 * @method static array allFiles(string $directory, bool $hidden = false)
 * @method static array directories(string $directory)
 * @method static array allDirectories(string $directory)
 * @method static bool directoryExists(string $path)
 * @method static void ensureDirectoryExists($path, int $mode = 0755, bool $recursive = true)
 * @method static bool makeDirectory($path, $visibility = null, bool $recursive = false, bool $force = false)
 * @method static bool copyDirectory($source, $destination, int|null $options = null)
 * @method static bool moveDirectory(string $from, string $to, bool $overwrite = false)
 * @method static bool deleteDirectory(string $directory, bool $preserve = false)
 * @method static bool cleanDirectory(string $directory)
 * @method static bool rename(string $oldName, string $newName)
 * @method static bool chmod(string $path, string $mode)
 * @method static bool link(string $target, string $link)
 * @method static bool symlink(string $target, string $link)
 * @method static bool isSymlink(string $path)
 * @method static string|false readlink(string $path)
 * @method static string|false realpath(string $path)
 */
class File extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'file';
    }
}
