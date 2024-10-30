<?php

namespace Eyika\Atom\Framework\Support\Storage;

use Exception;
use Eyika\Atom\Framework\Support\Arr;
use finfo;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsS3V3PortableVisibilityConverter;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class File
{
    const adapters = [
        'local' => 'League\Flysystem\Local\LocalFilesystemAdapter',
        'ftp' => 'League\Flysystem\Ftp\FtpAdapter',
        's3' => 'League\Flysystem\AwsS3V3\AwsS3V3Adapter',
        'azure' => 'League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter',
        'google' => 'League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter'
    ];

    protected $customDriverAdapters = [];

    protected Filesystem $filesystem;
    protected array $diskconfig = [];
    protected string $visibility;
    protected string $contents;

    public function __construct(Filesystem $filesystem = null, $disk = null)
    {
        $this->setDiskConfig($disk);

        if ($filesystem) {
            $this->filesystem = $filesystem;
        } else {
            $this->initAdapter();
        }
        
        $this->visibility = $this->diskconfig['visibility'] ?? 'public';
    }

    public function setFileSystemAdapter(FilesystemAdapter $filesystem)
    {
        $this->filesystem = new Filesystem($filesystem);
    }

    public function getFileSystemAdapter()
    {
        return $this->filesystem;
    }

    public function setDisk(string $disk = null)
    {
        $this->setDiskConfig($disk);
        $this->initAdapter();
    }

    protected function setDiskConfig(string $disk = null)
    {
        $this->diskconfig = is_null($disk) ? config('filesystems.disks')[config('filesystems.default')] : config('filesystems.disks')[$disk];
    }

    protected function initAdapter()
    {
        // $classname = self::adapters[$this->diskconfig['driver']] ?? null;

        if (empty($this->diskconfig['driver'])) {
            throw new FilesystemException('driver not found or adapter package not installed');
        }

        switch ($this->diskconfig['driver']) {
            case 'local':
                $this->filesystem = self::initLocalAdapter();
                break;
            case 'ftp':
                $this->filesystem = self::initFtpAdapter();
                break;
            case 'sftp':
                $this->filesystem = self::initFtpAdapter();
                break;
            case 's3':
                $this->filesystem = self::initS3Adapter();
                break;
            case 'azure':
                $this->filesystem = self::initAzureAdapter();
                break;
            case 'google':
                $this->filesystem = self::initGoogleAdapter();
                break;
        }
    }

    protected function initLocalAdapter(): Filesystem
    {
        $this->diskconfig['root'] .= str_ends_with("/", $this->diskconfig['root']) ? "" : "/";
        if (!file_exists($this->diskconfig['root']))
            mkdir($this->diskconfig['root'], 0775, true);

        $adapter = new LocalFilesystemAdapter(
            $this->diskconfig['root'],
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
        return new Filesystem($adapter, publicUrlGenerator: new LocalPublicUrlGenerator());
    }

    protected function initS3Adapter(): Filesystem
    {
        $options = Arr::except($this->diskconfig, ['driver', 'key', 'secret', 'throw', 'prefix']);
        $options['credentials'] = [
            'key' => $this->diskconfig['key'],
            'secret' => $this->diskconfig['secret']
        ];

        $client = new \Aws\S3\S3Client($options);

        $adapter = new AwsS3V3Adapter($client, $this->diskconfig['bucket'], $this->diskconfig['prefix'] ?? '',
            new AwsS3V3PortableVisibilityConverter($this->diskconfig['visibility'])
        );
        return new Filesystem($adapter);
    }

    protected function initFtpAdapter(): Filesystem
    {
        $adapter = new FtpAdapter(
            // Connection options
            FtpConnectionOptions::fromArray($this->diskconfig)
        );

        return new Filesystem($adapter);
    }

    protected function initAzureAdapter(): Filesystem
    {
        $client = BlobRestProxy::createBlobService($this->diskconfig['dsn']);

        $adapter = new AzureBlobStorageAdapter(
            $client,
            $this->diskconfig['container-name'],
            $this->diskconfig['prefix'] ?? '',
        );

        return new Filesystem($adapter);
    }

    protected function initGoogleAdapter(): Filesystem
    {
        $storageClient = new StorageClient([
            'projectId' => $this->diskconfig['project_id'],
            'keyFilePath' => $this->diskconfig['key_file_path'],
        ]);
        $bucket = $storageClient->bucket($this->diskconfig['bucket']);
        
        $adapter = new GoogleCloudStorageAdapter($bucket, $this->diskconfig['prefix'] ?? '');
        
        return new Filesystem($adapter);
    }

    public function setContents(string $contents)
    {
        $this->contents = $contents;
    }

    public function contents()
    {
        return $this->contents;
    }

    /**
     * Check if a file or directory exists.
     *
     * @param string $path
     * @return bool
     * @throws UnableToCheckExistence
     */
    public function exists($path)
    {
        return $this->filesystem->fileExists($path);
        // return file_exists($path);
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string
     * @throws FilesystemException
     */
    public function get($path)
    {
        return $this->filesystem->read($path);
        // if (!static::exists($path)) {
        //     throw new Exception("File does not exist at path {$path}");
        // }

        // return file_get_contents($path);
    }

    /**
     * Put the contents into a file.
     *
     * @param string $path
     * @param string $contents
     * @param bool $lock
     * @return int
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function put($path, $contents, $lock = false)
    {
        $this->filesystem->write($path, $contents, [
            'lock' => $lock ? LOCK_EX : 0
        ]);
        return strlen($contents);
        // return file_put_contents($path, $contents, $lock ? LOCK_EX : 0);
    }

    /**
     * Replace the contents of a file.
     *
     * @param string $path
     * @param string $contents
     * @return bool
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function replace($path, $contents)
    {
        $this->filesystem->write($path, $contents);

        return true;
    }

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $contents
     * @return int
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function prepend(string $path, string $data)
    {
        try {
            $existingContent = $this->filesystem->read($path);
        } catch (UnableToReadFile $e) {
            $existingContent = '';
        }

        $data = $data . $existingContent;
        $this->filesystem->write($path, $data);

        return strlen($data);
    }

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $contents
     * @return int
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function append(string $path, string $data)
    {
        try {
            $existingContent = $this->filesystem->read($path);
        } catch (UnableToReadFile $e) {
            $existingContent = '';
        }

        $data = $existingContent . $data;
        $this->filesystem->write($path, $data);

        return strlen($data);
    }

    // /**
    //  * Put the uploaded contents into a file.
    //  *
    //  * @param string $contents
    //  * @param string $path
    //  * @return int|bool
    //  */
    // public function upload($contents, $path)
    // {
    //     static::ensureDirectoryExists($path);
    //     return move_uploaded_file($contents, $path);
    // }
    /**
     * Put the uploaded contents into a file using Flysystem.
     *
     * @param string $tempPath The uploaded file contents.
     * @param string $path The path where the file should be stored.
     * @return int
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    public function upload($tempPath, $path)
    {
        if ($contents = file_get_contents($tempPath) == false) {
            throw new FilesystemException('unable to read uploaded file');
        }

        // Write the file to the specified path
        $this->filesystem->write(basename($path), $contents);

        return strlen($contents);
    }

    /**
     * Delete the file at a given path.
     *
     * @param string $path
     * @return bool
     * @throws UnableToDeleteFile
     * @throws FilesystemException
     */
    public function delete($path)
    {
        if ($this->exists($path)) {
            $this->filesystem->delete($path);
        }
        return true;
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     * @throws UnableToMoveFile
     * @throws FilesystemException
     */
    public function move($from, $to)
    {
        $this->filesystem->move($from, $to);
        
        return true;
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     * @throws UnableToCopyFile
     * @throws FilesystemException
     */
    public function copy($from, $to)
    {
        $this->filesystem->copy($from, $to, [
            'visibility' => $this->visibility
        ]);

        return true;
    }

    /**
     * Get the filename without extension.
     *
     * @param string $path
     * @return string
     */
    public function name($path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get the basename of a file.
     *
     * @param string $path
     * @return string
     */
    public function basename($path)
    {
        return pathinfo($path, PATHINFO_BASENAME);
    }

    /**
     * Get the directory name of a file.
     *
     * @param string $path
     * @return string
     */
    public function dirname($path)
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    /**
     * Get the file extension.
     *
     * @param string $path
     * @return string
     */
    public function extension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Guess the file extension.
     *
     * @param string $path
     * @return string
     */
    public function guessExtension(string $path)
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file('/path/to/file.txt');
        
        // Map the MIME type to an extension (simplified)
        $extensions = [
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        
        $extension = $extensions[$mimeType] ?? 'unknown';

        return $extension;
    }

    /**
     * Get the type of a file.
     *
     * @param string $path
     * @return string
     */
    public function type($path)
    {
        try {
            $mimeType = $this->filesystem->mimeType($path);
        
            if (str_starts_with($mimeType, 'directory')) {
                $type = 'directory';
            } else {
                $type = 'file';
            }
        } catch (UnableToRetrieveMetadata $exception) {
            $type = 'unknown';
        }

        return $type;
    }

    /**
     * Get the MIME type of a file.
     *
     * @param string $path
     * @return string
     * @throws Exception
     */
    public function mimeType($path)
    {
        try {
            $mimeType = $this->filesystem->mimeType($path);
        } catch (UnableToRetrieveMetadata $exception) {
            // Handle the case where the MIME type can't be determined
            $mimeType = 'unknown';
        }

        return $mimeType;
    }

    /**
     * Get the visibility of a file
     * @param string $path
     * @return string
     * 
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function visibility(string $path)
    {
        return $this->filesystem->visibility($path);
    }

    /**
     * Set the visibility of a file
     * @param string $path
     * @param string $visibility
     * @return string
     * 
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function setVisibility($path, $visibility)
    {
        return $this->filesystem->setVisibility($path, $visibility);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     * @return int
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function size($path)
    {

        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return $this->filesystem->fileSize($path);
    }

    /**
     * Get the last modified time of a file.
     *
     * @param string $path
     * @return int
     * @throws UnableToCheckExistence
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function lastModified($path)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return $this->filesystem->lastModified($path);
    }

    /**
     * Check if a path is a directory.
     *
     * @param string $path
     * @return bool
     * 
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function isDirectory($path)
    {
        $mimeType = $this->filesystem->mimeType($path);

        return str_starts_with($mimeType, 'directory');
    }

    /**
     * Check if a path is a file.
     *
     * @param string $path
     * @return bool
     * 
     * @throws UnableToRetrieveMetadata
     * @throws FilesystemException
     */
    public function isFile($path)
    {
        
        $mimeType = $this->filesystem->mimeType($path);

        return str_starts_with($mimeType, 'file');
    }

    /**
     * Check if a file is readable.
     *
     * @param string $path
     * @return bool
     */
    public function isReadable($path)
    {
        try {
            $this->filesystem->read($path);
            return true; // File is readable
        } catch (UnableToReadFile $e) {
            return false; // File is not readable
        }
    }

    /**
     * Check if a file is writeable.
     *
     * @param string $path
     * @return bool
     */
    public function isWriteable($path)
    {
        try {
            $this->filesystem->write($path, 'test-write');

            $this->filesystem->delete($path);
            return true; // File is readable
        } catch (UnableToReadFile $e) {
            return false; // File is not readable
        } catch (UnableToDeleteFile $e) {
            return false; // File is not readable
        } catch (FilesystemException $e) {
            return false; // File is not readable
        }
    }

    /**
     * Find path names matching a pattern
     * 
     * @param string $pattern
     * @param int $flags
     * 
     * @return array
     * @throws UnableToListContents
     * @throws FilesystemException
     */
    public function glob(string $pattern, int $flags = 0)
    {
        // Retrieve all contents of the directory
        $contents = $this->filesystem->listContents('', true);

        $matchingFiles = [];

        foreach ($contents as $item) {
            if ($item instanceof StorageAttributes && $item->isFile()) {
                // Use fnmatch to filter files based on the pattern
                if (fnmatch($pattern, $item->path())) {
                    $matchingFiles[] = $item->path();
                }
            }
        }

        return $matchingFiles;
    }

    /**
     * Get all files in a directory
     * 
     * @param string $pattern
     * @param bool $hidden
     * 
     * @return array
     * @throws UnableToListContents
     * @throws FilesystemException
     */
    public function files(string $directory, bool $hidden = false)
    {
        $contents = $this->filesystem->listContents($directory, false);

        $files = [];

        foreach ($contents as $item) {
            if ($item instanceof StorageAttributes && $item->isFile()) {
                $path = $item->path();
                $isHidden = basename($path)[0] === '.';

                if ($hidden || !$isHidden) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Get all files recursively
     * 
     * @param string $pattern
     * @param bool $hidden
     * 
     * @return array
     * @throws UnableToListContents
     * @throws FilesystemException
     */
    public function allFiles(string $directory, bool $hidden = false)
    {
        $contents = $this->filesystem->listContents($directory, false);

        $files = [];

        foreach ($contents as $item) {
            if ($item instanceof StorageAttributes && $item->isFile()) {
                $path = $item->path();
                $isHidden = basename($path)[0] === '.';

                if ($hidden || !$isHidden) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Get all directories within a given directory
     * 
     * @param string $pattern
     * @return array
     * @throws UnableToListContents
     * @throws FilesystemException
     */
    public function directories(string $directory)
    {
        $contents = $this->filesystem->listContents($directory, false);

        $files = [];

        foreach ($contents as $item) {
            if ($item instanceof StorageAttributes && $item->isDir()) {
                $path = $item->path();
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Get all directories within a given directory recursively
     * 
     * @param string $pattern
     * @return array
     * @throws UnableToListContents
     * @throws FilesystemException
     */
    public function allDirectories(string $directory)
    {
        $contents = $this->filesystem->listContents($directory, true);

        $files = [];

        foreach ($contents as $item) {
            if ($item instanceof StorageAttributes && $item->isDir()) {
                $path = $item->path();
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Ensure a directory exists
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @return void
     * @throws UnableToCheckExistence
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function ensureDirectoryExists($path, int $mode = 0755, bool $recursive = true)
    {
        $paths = explode('/', $path);
        if (str_contains($paths[count($paths) -1], '.')) {
            array_pop($paths);
        }

        $paths = implode('/', $paths);

        if (!$this->exists($paths)) {
            $this->makeDirectory($paths);
        }
    }

    /**
     * Ensure a directory exists
     * @param string $path
     * @return bool
     * @throws UnableToCheckExistence
     * @throws FilesystemException
     */
    public function directoryExists($path)
    {
        return $this->filesystem->directoryExists($path);
    }

    /**
     * Create a directory.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function makeDirectory($path, $visibility = null, bool $recursive = false, bool $force = false)
    {
        try {
            if ($recursive) {
                // Create directory recursively
                $this->filesystem->createDirectory($path, [
                    'visibility' => $visibility ?? $this->visibility
                ]);
            } else {
                // If not recursive, check if parent exists
                $parentDir = dirname($path);
                if ($this->filesystem->directoryExists($parentDir)) {
                    $this->filesystem->createDirectory($path, [
                        'visibility' => $visibility ?? $this->visibility
                    ]);
                } elseif ($force) {
                    // If force is true, create the parent directories as well
                    $this->filesystem->createDirectory($path, [
                        'visibility' => $visibility ?? $this->visibility
                    ]);
                }
            }
        } catch (FilesystemException $e) {
            // Handle the exception and return false if failed
            return false;
        }
    
        return false;
    }

    /**
     * Copy a directory to a new location.
     *
     * @param string $source
     * @param string $destination
     * @return bool
     * @throws UnableToCheckExistence
     * @throws UnableToCreateDirectory
     * @throws FilesystemException
     */
    public function copyDirectory($source, $destination, int $options = null)
    {
        $overwrite = $options['overwrite'] ?? true; // Option to overwrite existing files
        $includeHidden = $options['include_hidden'] ?? true; // Option to include hidden files
    
        try {
            $contents = $this->filesystem->listContents($source, true);
            
            foreach ($contents as $item) {
                if ($item instanceof StorageAttributes && $item->isFile()) {
                    $filePath = $item->path();
                    $newPath = str_replace($source, $destination, $filePath);
    
                    // Check if the file is hidden (starts with a dot)
                    $isHidden = basename($filePath)[0] === '.';
    
                    // Skip hidden files if include_hidden is false
                    if (!$includeHidden && $isHidden) {
                        continue;
                    }
    
                    // Check if the destination file exists and whether to overwrite
                    if ($this->filesystem->fileExists($newPath) && !$overwrite) {
                        continue;
                    }
    
                    // Copy the file to the new location
                    $this->filesystem->copy($filePath, $newPath);
                }
            }
    
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @param bool $overwrite
     * 
     * @return bool
     */
    public function moveDirectory(string $from, string $to, bool $overwrite = false)
    {
        return $this->copyDirectory($from, $to, $overwrite) == false && $this->deleteDirectory($from) == false;
    }

    /**
     * Delete a directory.
     *
     * @param string $directory
     * @param preserve
     * @return bool
     */
    public function deleteDirectory(string $directory, bool $preserve = false)
    {
        try {
            if ($preserve) {
                return $this->cleanDirectory($directory);
            } else {
                return $this->filesystem->deleteDirectory($directory);
            }
        } catch (UnableToDeleteDirectory $e) {
            return false;
        }
    }
    
    /**
     * Remove all files from a directory
     * 
     * @param string $directory
     * @return bool
     */
    public function cleanDirectory(string $directory)
    {
        try {
            $contents = $this->filesystem->listContents($directory, false);
    
            foreach ($contents as $item) {
                $path = $item->path();
    
                if ($item->isFile()) {
                    $this->filesystem->delete($path);
                } elseif ($item->isDir()) {
                    $this->filesystem->deleteDirectory($path);
                }
            }
    
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * Rename a file or directory.
     *
     * @param string $oldName
     * @param string $newName
     * @return bool
     */
    public function rename($oldName, $newName)
    {
        return $this->copy($oldName, $newName) && $this->delete($oldName);
    }

    /**
     * Change the file mode (only works for local filesystem).
     *
     * @param string $path
     * @param int $mode
     * @return bool
     * @throws Exception
     */
    public function chmod($path, $mode)
    {
        if (!static::exists($path)) {
            throw new Exception("File does not exist at path {$path}");
        }

        return chmod($path, $mode);
    }

    /**
     * Create a hard link (only works for local filesystem).
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    public function link($target, $link)
    {
        return link($target, $link);
    }

    /**
     * Create a symbolic link (only works for local filesystem).
     *
     * @param string $target
     * @param string $link
     * @return bool
     */
    public function symlink($target, $link)
    {
        return symlink($target, $link);
    }

    /**
     * Check if a file is a symbolic link (only works for local filesystem).
     *
     * @param string $path
     * @return bool
     */
    public function isSymlink($path)
    {
        return is_link($path);
    }

    /**
     * Read the link to which a symbolic link points (only works for local filesystem).
     *
     * @param string $path
     * @return string|false
     */
    public function readlink($path)
    {
        return readlink($path);
    }

    /**
     * Get the absolute path of a file.
     *
     * @param string $path
     * @return string|false
     */
    public function realpath($path)
    {
        return realpath($path);
    }
}
