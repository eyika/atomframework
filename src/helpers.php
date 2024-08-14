<?php

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Storage\Storage;
use Eyika\Atom\Framework\Support\Url;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

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

if (! function_exists('transaction_ref')) {
    /**
     * Returns a unique transaction reference
     */
    function transaction_ref(string $prefix = 'btfxtrans-')
    {
        return uniqid($prefix);
    }
}

if (! function_exists('request')) {
    /**
     * Returns the current request object
     */
    function request() {
        return Request::capture();
    }
}

if (! function_exists('storage')) {
    /**
     * Return the current storage object
     */
    function storage(string $disk = 'local', CacheInterface $cache = null) {
        return Storage::init($disk, $cache);
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
        $config_name = array_shift($parts);

        $config = [];
        $data = include_once __DIR__."/../../config/$config_name.php";

        $config = (array)$data;

        foreach ($parts as $key => $part) {
            if (!array_key_exists($part, $config)) {
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
        //$dotenv = Dotenv::createImmutable(__DIR__);
        //$dotenv->load();
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
            // is_int($data) || is_float($data) || is_double($data) || is_null($data) || is_bool($data)
            $data = is_string($data) ? htmlspecialchars(strip_tags($data)) : $data;
        }

        return $data;
    }
}

if (! function_exists('getIpAddress')) {
    /**
     * Get the IP address of the current request
     * 
     * @param Request $request
     * @return string
     */
    function getIpAddress(Request $request)
    {
        if ($request->HTTP_CLIENT_IP) {
            return $request->HTTP_CLIENT_IP;
        } elseif ($request->HTTP_X_FORWARDED_FOR) {
            return $request->HTTP_X_FORWARDED_FOR;
        } else {
            return $request->REMOTE_ADDR;
        }
    }
}

if (!function_exists("consoleLog")) {
    function consoleLog($level, $msg) {
        file_put_contents("php://stdout", "[" . $level . "] " . $msg . "\n");
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $folder = '')
    {
        $is_windows = strtolower(PHP_OS_FAMILY) === "windows";

        // if ($folder != '')
        //     $folder = $is_windows ? "$folder\\" : "$folder/";
        return $is_windows ? __DIR__."\\..\\..\\storage\\$folder" : __DIR__."/../../storage/$folder";
    }
}

if (!function_exists('is_windows')) {
    function is_windows () {
        return strtolower(PHP_OS_FAMILY) === "windows";
    }
}

if (! function_exists('logger')) {
    function logger(string $path = null, int $level = Monolog\Level::Debug, $bubble = true, $filePermission = 0664, $useLocking = false)
    {
        $logger_path = strtolower(PHP_OS_FAMILY) === "windows" ? "logs\\custom.log" : "logs/custom.log";
        $path = is_null($path) ? storage_path().$logger_path : $path;
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
