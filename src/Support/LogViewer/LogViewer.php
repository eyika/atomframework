<?php

namespace Eyika\Atom\Framework\Support\LogViewer;

use Eyika\Atom\Framework\Exceptions\Storage\FileNotFoundException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Cache\DbCache;
use Eyika\Atom\Framework\Support\Storage\Storage;

class LogViewer
{
    /**
     * @var string file
     */
    private $file = '';

    /**
     * @var string folder
     */
    private $folder = '';

    /**
     * @var string storage_path
     */
    private $storage_path;

    /**
     * @var string storage_disk
     */
    private $storage_disk;

    /**
     * Why? Uh... Sorry
     */
    const MAX_FILE_SIZE = 52428800;

    /**
     * @var Level level
     */
    private $level;

    /**
     * @var Pattern pattern
     */
    private $pattern;

    /**
     * LaravelLogViewer constructor.
     */
    public function __construct()
    {
        $this->level = new Level();
        $this->pattern = new Pattern();
        $this->storage_path = function_exists('config') ? config('logviewer.storage_path', storage_path('logs')) : storage_path('logs');
        $this->storage_disk = config('logviewer.storage_disk', 'log');
    }

    /**
     * @param string $folder
     */
    public function setFolder($folder)
    {
        if (storage( cache: new DbCache)->exists($folder, true)) {

            $this->folder = $folder;
        } else if (is_array($this->storage_path)) {

            foreach ($this->storage_path as $value) {

                $logsPath = $value . '/' . $folder;

                if (storage( cache: new DbCache)->exists($logsPath, true)) {
                    $this->folder = $folder;
                    break;
                }
            }
        } else {

            $logsPath = $this->storage_path . '/' . $folder;
            if (storage( cache: new DbCache)->exists($logsPath, true)) {
                $this->folder = $folder;
            }

        }
    }

    /**
     * @param string $file
     * @throws \Exception
     */
    public function setFile($file)
    {
        // $file = $this->pathToLogFile($file);

        if (storage( cache: new DbCache)->exists($file, true)) {
            $this->file = $file;
        }
    }

    /**
     * @param string $file
     * @return string
     * @throws \Exception
     */
    public function pathToLogFile($file)
    {

        // if (app('files')->exists($file) ) { // try the absolute path
        // if (storage( cache: new DbCache)->exists($file, true)) { // try the absolute path

        //     return $file;
        // }
        if (is_array($this->storage_path)) {
            foreach ($this->storage_path as $folder) {
                // if (app('files')->exists($folder . '/' . $file)) { // try the absolute path
                if (storage( cache: new DbCache)->exists($folder . '/' . $file)) { // try the absolute path
                    $file = $folder . '/' . $file;
                    break;
                }
            }
            return $file;
        }

        $logsPath = $this->storage_path;
        $logsPath .= ($this->folder) ? '/' . $this->folder : '';
        $file = $logsPath . '/' . $file;
        // check if requested file is really in the logs directory
        if (dirname($file) !== $logsPath) {
            throw new \Exception('No such log file: ' . $file);
        }

        return $file;
    }

    /**
     * @return string
     */
    public function getFolderName()
    {
        return $this->folder;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return basename($this->file);
    }

    /**
     * @return array
     */
    public function all()
    {
        $log = array();

        if (!$this->file) {
            $log_file = (!$this->folder) ? $this->getFiles() : $this->getFolderFiles();
            if (!count($log_file)) {
                return [];
            }
            $this->file = $log_file[0];
        }

        $max_file_size = function_exists('config') ? config('logviewer.max_file_size', self::MAX_FILE_SIZE) : self::MAX_FILE_SIZE;
        // if ( app('files')->size($this->file) > $max_file_size) {

        // try {
            if ($this->getStorage()->size($this->file) > $max_file_size) {
                return null;
            }
        // } catch (FileNotFoundException $e) {
        //     return null;
        // }

        if (!$this->getStorage()->exists($this->file)) {
            return [[
                'context' => '',
                'level' => '',
                'date' => null,
                'text' => 'Log file "' . $this->file . '" not readable',
                'stack' => '',
            ]];
        }

        // $file = app('files')->get($this->file);
        $file = $this->getStorage()->get($this->file);

        preg_match_all($this->pattern->getPattern('logs'), $file, $headings);

        if (!is_array($headings)) {
            return $log;
        }

        $log_data = preg_split($this->pattern->getPattern('logs'), $file);

        if ($log_data[0] < 1) {
            array_shift($log_data);
        }

        foreach ($headings as $h) {
            for ($i = 0, $j = count($h); $i < $j; $i++) {
                foreach ($this->level->all() as $level) {
                    if (strpos(strtolower($h[$i]), '.' . $level) || strpos(strtolower($h[$i]), $level . ':')) {

                        preg_match($this->pattern->getPattern('current_log', 0) . $level . $this->pattern->getPattern('current_log', 1), $h[$i], $current);
                        if (!isset($current[4])) {
                            continue;
                        }

                        $log[] = array(
                            'context' => $current[3],
                            'level' => $level,
                            'folder' => $this->folder,
                            'level_class' => $this->level->cssClass($level),
                            'level_img' => $this->level->img($level),
                            'date' => $current[1],
                            'text' => $current[4],
                            'in_file' => isset($current[5]) ? $current[5] : null,
                            'stack' => preg_replace("/^\n*/", '', $log_data[$i])
                        );
                    }
                }
            }
        }

        if (empty($log)) {

            $lines = explode(PHP_EOL, $file);
            $log = [];

            foreach ($lines as $key => $line) {
                $log[] = [
                    'context' => '',
                    'level' => '',
                    'folder' => '',
                    'level_class' => '',
                    'level_img' => '',
                    'date' => $key + 1,
                    'text' => $line,
                    'in_file' => null,
                    'stack' => '',
                ];
            }
        }

        return $log;
    }

    /**Creates a multidimensional array
     * of subdirectories and files
     *
     * @param null $path
     *
     * @return array
     */
    public function foldersAndFiles($path = null)
    {
        $contents = array();
        $dir = $path ? $path : $this->storage_path;
        foreach (scandir($dir) as $node) {
            if ($node == '.' || $node == '..') continue;
            $path = $dir . (is_windows() ? '\\' : '/') . $node;
            if (is_dir($path)) {
                $contents[$path] = $this->foldersAndFiles($path);
            } else {
                $contents[] = $path;
            }
        }

        return $contents;
    }

    /**Returns an array of
     * all subdirectories of specified directory
     *
     * @param string $folder
     *
     * @return array
     */
    public function getFolders($folder = '')
    {
        $folders = [];
        $listObject = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->storage_path . '/' . $folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($listObject as $fileinfo) {
            if ($fileinfo->isDir()) $folders[] = $fileinfo->getRealPath();
        }
        return $folders;
    }


    /**
     * @return array
     */
    public function getFolderFiles()
    {
        return $this->getFiles($this->folder);
    }

    /**
     * @param string $folder
     * @return array
     */
    public function getFiles($folder = '')
    {
        $files = [];
        $pattern = function_exists('config') ? config('logviewer.pattern', '*.log') : '*.log';
        $fullPath = $folder ? $this->storage_path . '/' . $folder : $this->storage_path;

        $listObject = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($listObject as $fileinfo) {
            if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', $pattern)[1])
                // $files[] = str_replace(storage_path(), '', $fileinfo->getRealPath());
                $files[] = basename($fileinfo->getRealPath());
        }

        sort($files);

        return array_values($files);
    }

    /**
     * @return Storage
     */
    public function getStorage()
    {
        return storage($this->storage_disk, cache: new DbCache);
    }

    /**
     * @param string $filepath
     */
    public function clean(string $filepath)
    {
        $this->getStorage()->put($filepath, '');
    }

    /**
     * 
     */
    public function cleanFolder()
    {
        $files = ($this->getFolderName())
            ? $this->getFolderFiles()
            : $this->getFiles();

        foreach ($files as $file) {
            $this->getStorage()->delete($file, true);
        }
    }

    /**
     * 
     */
    public function delete(string $filepath)
    {
        $this->getStorage()->delete($filepath);
    }

    /**
     * @return string
     */
    public function getStoragePath()
    {
        return $this->storage_path;
    }

    /**
     * @param $path
     *
     * @return void
     */
    public function setStoragePath($path)
    {
        $this->storage_path = $path;
    }

    public static function directoryTreeStructure($storage_path, array $array)
    {
        foreach ($array as $k => $v) {
            if (is_dir($k)) {

                $exploded = explode( is_windows() ? "\\" : "/", $k);
                $show = array_pop($exploded);

                echo '<div class="list-group folder">
				    <a href="?f=' . encrypt($k) . '">
					    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><span
						    class="fa fa-folder"></span> ' . $show . '
				    </a>
			    </div>';

                if (is_array($v)) {
                    self::directoryTreeStructure($storage_path, $v);
                }

            } else {

                $exploded = explode( is_windows() ? "\\" : "/", $v);
                $show2 = array_pop($exploded);
                $folder = str_replace($storage_path, "", rtrim(str_replace($show2, "", $v), is_windows() ? "\\" : "/"));
                $file = $v;


                echo '<div class="list-group">
				    <a href="?l=' . encrypt($file) . '&f=' . encrypt($folder) . '">
					    <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> <span
						    class="fa fa-file"></span> ' . $show2 . '
				    </a>
			    </div>';

            }
        }

        return;
    }
}
