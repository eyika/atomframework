<?php

use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\PaginatedData;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Storage;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Support\Stringable;
use Eyika\Atom\Framework\Support\Url;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

if (! function_exists('classFromFile')) {
        /**
     * Extract the class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $namespace
     * @return string
     */
    function classFromFile(SplFileInfo $file, string $namespace, $after = 'src'): string
    {
        return $namespace.str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after($file->getRealPath(), $after)  //may trigger cyclic reference error
        );
    }
}

if (! function_exists("array_key_last")) {
    function array_key_last($array) {
        if (!is_array($array) || empty($array)) {
            return NULL;
        }

        return array_keys($array)[count($array)-1];
    }
}

if (! function_exists('json_response')) {
    /**
     * Returns a json response for PHP http request
     * 
     * @param int $status_code
     * @param array $data
     */
    function json_response(int $status_code, array $data)
    {
        http_response_code($status_code);
        header("Content-type: application/json");
        echo json_encode($data);
        return true;
    }
}

if (! function_exists('paginate')) {
    function paginate(array $data, ModelInterface|UserModelInterface $model, $currentPage = PaginatedData::currentPage, $recordsPerPage = PaginatedData::recordsPerPage)
    {
        $currentPage = $currentPage;
        $recordsPerPage = $recordsPerPage;
        $totalRecords = $model->count($model->primaryKey, false);
        // Calculate total pages
        $totalPages = ceil($totalRecords / $recordsPerPage);

        return PaginatedData::init($data, $totalRecords, $recordsPerPage, $totalPages, $currentPage);
    }
}

if (! function_exists('transaction_ref')) {
    /**
     * Returns a unique transaction reference
     */
    function transaction_ref(string $prefix = 'btfxtrans-')
    {
        return uniqid($prefix);
    }
}

// if (! function_exists('request')) {
//     /**
//      * Returns the current request object
//      */
//     function request() {
//         return new Request;
//     }
// }

if (! function_exists('storage')) {
    /**
     * Return the current storage object
     */
    function storage(string $disk = null, CacheInterface $cache = null) {
        if (is_null($disk))
            $disk = config('filesystems.default');
        $storage = Storage::disk($disk);
        if ($cache)
            $storage = $storage->cache($cache);
        return $storage;
    }
}

if (! function_exists('database')) {
    /**
     * Return the current database object
     */
    function database() {
        return DB::init();
    }
}

if (!function_exists('encrypt')) {
    function encrypt($value, $serialize = true) {
        $encrypter = new Encrypter();
        return $encrypter->encrypt($value, $serialize);
    }
}

if (!function_exists('decrypt')) {
    function decrypt($value, $serialize = true) {
        $encrypter = new Encrypter();
        return $encrypter->decrypt($value, $serialize);
    }
}

if (!function_exists('url')) {
    function url() {
        return new Url;
    }
}

if (!function_exists('str')) {
    function str(string $value) {
        return new Stringable($value);
    }
}

if (!function_exists('response')) {
    function response() {
        return new Response();
    }
}

if (! function_exists('config')) {
    /**
     * Get a config data from configuration file
     */
    function config(string $config_name, $default = null) {
        $parts = explode('.', $config_name);
        $file = array_shift($parts);
    
        $config = [];
    
        // Load the config file
        $file_path = base_path() . "/config/{$file}.php";
        
        if (file_exists($file_path)) {
            $config = require $file_path;  // or require_once
        } else {
            return $default;
        }
    
        // Traverse the config array using the remaining parts
        foreach ($parts as $part) {
            if (!is_array($config) || !array_key_exists($part, $config)) {
                return $default;
            }
            $config = $config[$part];
        }
    
        return $config;
    }
    
}

if (! function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        return $_ENV[$key] ?? $default;
    }
}

if (! function_exists('sanitize_data')) {
    /**
     * Sanitize an array of data or string data
     * 
     * @param array|string|int $data
     * @return array|string|int
     */
    function sanitize_data($data)
    {
        if (is_iterable($data)) {
            foreach ($data as $key => $dat) {
                $data[$key] = is_string($dat) ? htmlspecialchars(strip_tags($dat)) : $dat;
            }
        } else {
            $data = is_string($data) ? htmlspecialchars(strip_tags($data)) : $data;
        }

        return $data;
    }
}

if (! function_exists('getIpAddress')) {
    /**
     * Get the IP address of the current request
     * 
     * @return string
     */
    function getIpAddress()
    {
        if (Request::server('HTTP_CLIENT_IP')) {
            return Request::server('HTTP_CLIENT_IP');
        } elseif (Request::server('HTTP_X_FORWARDED_FOR')) {
            return Request::server('HTTP_X_FORWARDED_FOR');
        } else {
            return Request::server('REMOTE_ADDR');
        }
    }
}

if (!function_exists("consoleLog")) {
    function consoleLog($level, $msg) {
        file_put_contents("php://stdout", "[" . $level . "] " . $msg . "\n");
    }
}

if (! function_exists('base_path')) {
    function base_path(string $folder = ''): string
    {
        $folder = empty($folder) ? '' : "/$folder";
        return $GLOBALS['base_path'].$folder ?? $_SERVER['DOCUMENT_ROOT'].$folder;
    }
}

if (! function_exists('framework_namespace')) {
    function framework_namespace(string $classname = ''): string
    {
        $classname = empty($classname) ? '' : "\\$classname";
        return $GLOBALS['framework_namespace'].$classname;
    }
}

if (! function_exists('project_namespace')) {
    function project_namespace(string $classname = ''): string
    {
        $classname = empty($classname) ? '' : "\\$classname";
        return $GLOBALS['project_namespace'].$classname;
    }
}

if (! function_exists('asset')) {
    function asset(string $folder = ''): string
    {
        $server_url = Request::getSchemeAndHttpHost();
        $folder = empty($folder) ? '' : "/$folder";
        return $server_url.$folder;
    }
}

if (! function_exists('config_path')) {
    function config_path(string $folder = '')
    {
        return base_path() . "/config/". $folder;
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $folder = '')
    {
        return base_path() . "/storage/". $folder;
    }
}

if (! function_exists('public_path')) {
    function public_path(string $folder = '')
    {
        return base_path() . "/public/". $folder;
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $folder = '')
    {
        return base_path() . "/resources/". $folder;
    }
}

if (! function_exists('database_path')) {
    function database_path(string $folder = '')
    {
        return base_path() . "/database/". $folder;
    }
}

if (!function_exists('is_windows')) {
    function is_windows () {
        return strtolower(PHP_OS_FAMILY) === "windows";
    }
}

if (! function_exists('logger')) {
    function logger(string $path = null, Monolog\Level $level = Monolog\Level::Debug, $bubble = true, $filePermission = 0664, $useLocking = false)
    {
        $path = is_null($path) ? storage_path("logs/custom.log") : $path;
        $log = new Logger('tradingio');
        // Define the date format to match Laravel's
        $dateFormat = "Y-m-d H:i:s";

        // Define the output format including the date format
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

        // Create a formatter with the specified date format and output format
        $formatter = new LineFormatter($output, $dateFormat, true, true);
        $streamHandler = new StreamHandler($path, $level, $bubble, $filePermission, $useLocking);
        $streamHandler->setFormatter($formatter);
        return $log->pushHandler($streamHandler);
    }
}

if (! function_exists('format_int_leading_zero')) {
    function format_int_leading_zero(int $number): string
    {
        if (is_numeric($number) && $number < 10) {
            return "0" . $number;
        } else {
            return strval($number); // Convert to string without leading zero
        }
    }
}

if (! function_exists('is_dev_mode')) {
    function is_dev_mode ()
    {
        return env('APP_ENV') == 'dev';
    }
}

if (! function_exists('is_local_mode')) {
    function is_local_mode ()
    {
        return env('APP_ENV') == 'local';
    }
}

if (! function_exists('is_local_postman')) {
    function is_local_postman ()
    {
        return env('APP_ENV') == 'local' && isset($_SERVER["HTTP_DEV_POSTMAN"]) && $_SERVER['HTTP_DEV_POSTMAN'] == true;
    }
}

if (! function_exists('validate_data_structure')) {
    /**
     * Validate that an array data matches a given structure
     * 
     * @param object $data
     * @param array $structure
     * @return bool
     */
    function validate_data_structure($data, $structure)
    {
        foreach ($structure as $key => $value) {
            if (!property_exists($data, $key)) {
                return false;
            }
    
            if (is_array($value)) {
                if (!is_array($data->$key) || !validate_data_structure($data->$key, $value)) {
                    return false;
                }
            }
        }
    
        return true;
    }
}

if (! function_exists('get_weeks_in_year')) {
    /**
     * Get the number of weeks in a given year
     * 
     * @param string $year
     * 
     * @return int
     */
    function get_weeks_in_year($year) {
        // Create a DateTime object for January 4th of the year
        $date = new DateTime($year . '-01-04');
    
        // Get the week number of January 4th
        $january4WeekNumber = (int)$date->format('W');
    
        // If January 4th falls on or before Thursday, it's week 1 of the year
        if ($date->format('N') <= 4) {
            return $january4WeekNumber;
        } else {
            // Otherwise, the week containing January 1st belongs to the previous year
            return $january4WeekNumber - 1;
        }
    }
}
