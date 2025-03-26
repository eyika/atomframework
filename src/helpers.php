<?php

use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Eyika\Atom\Framework\Foundation\Console\ConsoleColorizer;
use Eyika\Atom\Framework\Foundation\Event\Dispatcher;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Route;
use Eyika\Atom\Framework\Support\Auth\Auth;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\User;
use Eyika\Atom\Framework\Support\Cache\Contracts\CacheInterface;
use Eyika\Atom\Framework\Support\Collections\Collection;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Database\PaginatedData;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\App;
use Eyika\Atom\Framework\Support\Facade\Broadcast;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Facade\JsonResponse;
use Eyika\Atom\Framework\Support\Facade\Request;
use Eyika\Atom\Framework\Support\Facade\Response;
use Eyika\Atom\Framework\Support\Facade\Storage;
use Eyika\Atom\Framework\Support\Fluent;
use Eyika\Atom\Framework\Support\HigherOrderTapProxy;
use Eyika\Atom\Framework\Support\Once;
use Eyika\Atom\Framework\Support\Onceable;
use Eyika\Atom\Framework\Support\Optional;
use Eyika\Atom\Framework\Support\Sleep;
use Eyika\Atom\Framework\Support\Str;
use Eyika\Atom\Framework\Support\Stringable;
use Eyika\Atom\Framework\Support\Url;
use Eyika\Atom\Framework\Support\View\Blade;
use Eyika\Atom\Framework\Support\View\Twig;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NoopHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

require_once __DIR__."/Support/Collections/helpers.php";

if (! function_exists('classFromFile')) {
        /**
     * Extract the class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $namespace
     * @return string
     */
    function classFromFile(SplFileInfo|string $file, string $namespace, $base_folder = 'src'): string
    {
        return $namespace.str_replace(
            ['/', '.php'],
            ['\\', ''],
            Str::after(is_string($file) ? $file : $file->getRealPath(), $base_folder)  //may trigger cyclic reference error
        );
    }
}

if (! function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @param  bool  $isFilePath
     * @return string
     */
    function class_basename($class, $isFilePath = false)
    {
        if ($isFilePath) {
            require_once $class;
            return pathinfo($class, PATHINFO_FILENAME);
        }
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
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

if (! function_exists("collect")) {
    function collect($array) {
        return Collection::make($array);
    }
}

if (! function_exists("app")) {
    function app() {
        return Facade::getFacadeApplication();
    }
}

if (! function_exists('paginate')) {
    function paginate(array $data, Model|User $model, $currentPage = PaginatedData::currentPage, $recordsPerPage = PaginatedData::recordsPerPage)
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
    function transaction_ref(string $prefix = '', bool $more_entropy = false)
    {
        return uniqid($prefix, $more_entropy);
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
    function storage(string|null $disk = null, CacheInterface|null $cache = null) {
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
    function database(string $table) {
        return DB::table($table);
    }
}

if (! function_exists('db')) {
    /**
     * Return the current database object
     */
    function db(string $table) {
        return database($table);
    }
}

if (!function_exists('encrypt')) {
    function encrypt($value, $serialize = false) {
        if (!$encrypter = app()->make('encrypter')) {
            $encrypter = new Encrypter();
        }
        return $encrypter->encrypt($value, $serialize);
    }
}

if (!function_exists('decrypt')) {
    function decrypt($value, $serialize = false) {
        if (!$encrypter = app()->make('encrypter')) {
            $encrypter = new Encrypter();
        }
        return $encrypter->decrypt($value, $serialize);
    }
}

if (!function_exists('getHash')) {
    function getHash(string $data, string $algo = 'sha256', ?string $key = null, bool $binary = false) {
        if (!$key)
            $key = env('APP_KEY');

        return hash_hmac($algo, $data, $key, $binary);
    }
}

if (!function_exists('hashValid')) {
    function hashValid(string $known_string, string $user_string) {
        return hash_equals($known_string, $user_string);
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
    /**
     * Returns a http response
     * 
     * @return \Eyika\Atom\Framework\Http\Response
     */
    function response() {
        return Response::getInstance();
    }
}

if (!function_exists('redirect')) {
    /**
     * Returns a redirect http response
     * 
     * @return \Eyika\Atom\Framework\Http\Response
     */
    function redirect(string $to, int $code = BaseResponse::STATUS_FOUND, int|null $delay = null) {
        return Response::redirect($to, $code, $delay);
    }
}

if (! function_exists('json_response')) {
    /**
     * Returns a json response object
     * 
     * @return \Eyika\Atom\Framework\Http\JsonResponse
     */
    function json_response()
    {
        return JsonResponse::getInstance();
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
        $file_path = config_path("{$file}.php");
        
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

if (! function_exists('class_uses_recursive')) {
    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|string  $class
     * @return array
     */
    function class_uses_recursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $class) {
            $results += trait_uses_recursive($class);
        }

        return array_unique($results);
    }
}

if (! function_exists('trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param  object|string  $trait
     * @return array
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (! function_exists('e')) {
    /**
     * Encode HTML special characters in a string.
     *
     * @param  \BackedEnum|string|null  $value
     * @param  bool  $doubleEncode
     * @return string
     */
    function e($value, $doubleEncode = true)
    {
        // if ($value instanceof DeferringDisplayableValue) {
        //     $value = $value->resolveDisplayableValue();
        // }

        // if ($value instanceof Htmlable) {
        //     return $value->toHtml();
        // }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (! function_exists('base_path')) {
    function base_path(string $folder = ''): string
    {
        $folder = empty($folder) ? '' : "/$folder";
        $root = $GLOBALS['base_path'] ?? $_SERVER['DOCUMENT_ROOT'];
        return $root.$folder;
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

if (! function_exists('database_namespace')) {
    function database_namespace(string $classname = ''): string
    {
        $classname = empty($classname) ? '' : "\\$classname";
        return $GLOBALS['database_namespace'].$classname;
    }
}

if (! function_exists('test_namespace')) {
    function test_namespace(string $classname = ''): string
    {
        $classname = empty($classname) ? '' : "\\$classname";
        return $GLOBALS['test_namespace'].$classname;
    }
}

if (! function_exists('asset')) {
    function asset(string $folder = ''): string
    {
        if (config("app.url"))
            $server_url = config('app.url');
        else
            $server_url = Request::schemeAndHttpHost();
        $folder = empty($folder) ? '' : "/$folder";
        return $server_url.$folder;
    }
}

if (! function_exists('config_path')) {
    function config_path(string $folder = '')
    {
        $folder = empty($folder) ? '' : "/$folder";
        return base_path("config$folder");
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $folder = '')
    {
        $folder = empty($folder) ? '' : "/$folder";
        return base_path("storage$folder");
    }
}

if (! function_exists('public_path')) {
    function public_path(string $folder = '')
    {
        $folder = empty($folder) ? '' : "/$folder";
        return base_path("public$folder");
    }
}

if (! function_exists('resource_path')) {
    function resource_path(string $folder = '')
    {
        $folder = empty($folder) ? '' : "/$folder";
        return base_path("resources$folder");
    }
}

if (! function_exists('database_path')) {
    function database_path(string $folder = '')
    {
        $folder = empty($folder) ? '' : "/$folder";
        return base_path("database$folder");
    }
}

if (!function_exists('is_windows')) {
    function is_windows () {
        return strtolower(PHP_OS_FAMILY) === "windows";
    }
}

if (! function_exists('logger')) {
    function logger(
        string|null $path = null,
        Level $level = Level::Debug,
        bool $bubble = true,
        int $filePermission = 0664,
        bool $useLocking = false,
        bool $internal = false,
        string|null $name = null,
        bool $isConsole = false
    ) {
        if ($internal && config('app.debug', true) == false) {
            return (new Logger(''))->pushHandler(new NoopHandler());
        }

        $path = $path ?? storage_path("logs/custom.log");

        if (!$name) {
            $name = config('app.name', 'Atom');
        }

        $log = new Logger($name);

        if ($isConsole) {
            // Date format: Tue Mar  4 10:25:40 2025
            $dateFormat = 'D M  j H:i:s Y';
            // Custom formatter with colorized output
            $formatter = new ConsoleColorizer(
                "%datetime% %channel%.%level_name%: %message%\n",
                $dateFormat,
                true,
                true
            );

            // Apply colors dynamically to log level
            $formatter->includeStacktraces();

            // Console handler (for stdout)
            $handler = new StreamHandler('php://stdout', $level);
            $handler->setFormatter($formatter);
        } else {
            // File handler (for log storage)
            $dateFormat = "Y-m-d H:i:s";
            $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

            $handler = new StreamHandler($path, $level, $bubble, $filePermission, $useLocking);
            $handler->setFormatter(new LineFormatter($output, $dateFormat, true, true));
        }

        return $log->pushHandler($isConsole ? $handler : $handler);
    }
}

if (!function_exists('info')) {
    function info(string $message, array $context = []) {
        logger()->info($message, $context);
    }
}

if (!function_exists('notice')) {
    function notice(string $message, array $context = []) {
        logger()->notice($message, $context);
    }
}

if (!function_exists('warning')) {
    function warning(string $message, array $context = []) {
        logger()->warning($message, $context);
    }
}

if (!function_exists('debug')) {
    function debug(string $message, array $context = []) {
        logger()->debug($message, $context);
    }
}

if (!function_exists('error')) {
    function error(string $message, array $context = []) {
        logger()->error($message, $context);
    }
}

if (!function_exists('critical')) {
    function critical(string $message, array $context = []) {
        logger()->critical($message, $context);
    }
}

if (!function_exists('alert')) {
    function alert(string $message, array $context = []) {
        logger()->alert($message, $context);
    }
}

if (!function_exists('emergency')) {
    function emergency(string $message, array $context = []) {
        logger()->emergency($message, $context);
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

if (!function_exists('view')) {
    /**
     * Get the html output from a view file
     * 
     * @param string $name
     * @param array $data
     * 
     * @return string
     */
    function view($file_name, $data = []) {
        $path = resource_path('views');

        if (config('view.use_advance_engine')) {
            $view = new Blade($path);
            $code = $view->run("$file_name", $data);
        } else {
            $code = Twig::make("$file_name.blade.php", "$path/", $data, true);
        }
        return $code;
    }
}

// if (!function_exists('csrf_token')) {
//     /**
//      * set the csrf token to the view response
//      */
//     function csrf_token ()
//     {
//         Csrf::setCsrfToken();
//     }
// }

if (!function_exists('route')) {
    /**
     * resolve a named route into its url value
     * 
     * @param string $name
     * @param array $parameters
     * 
     * @return string|null
     */
    function route (string $name, array $parameters = [])
    {
        return Route::route($name, $parameters);
    }
}

if (!function_exists('auth_user')) {
    /**
     * get the current authenticated user
     * 
     * @return AuthenticatableInterface
     */
    function auth_user ()
    {
        return Auth::user();
    }
}

if (!function_exists('debugbar')) {
    /**
     * compile the debugbar assets
     * 
     * @return JavascriptRenderer
     */
    function debugbar ()
    {
        return (new StandardDebugBar())->getJavascriptRenderer();
    }
}

if (!function_exists('getCallableName')) {
    function getCallableName(callable $callable): string
    {
        if (is_string($callable)) {
            // Function name
            return $callable;
        }
    
        if (is_array($callable)) {
            // Static method or object method
            $class = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];
            return $class . '::' . $callable[1];
        }
    
        if ($callable instanceof Closure) {
            // Anonymous function
            return 'Closure';
        }
    
        if (is_object($callable) && method_exists($callable, '__invoke')) {
            // Invokable class
            return get_class($callable) . '::__invoke';
        }
    
        throw new InvalidArgumentException('Unsupported callable type.');
    }
}

if (!function_exists('event')) {
    function event($event)
    {
        $dispatcher = app('events');
        /** @var Dispatcher $dispatcher */
        $dispatcher->dispatch($event);
    }
}

if (!function_exists('broadcast')) {
    function broadcast(array $channels, $event, array $payload = [])
    {
        Broadcast::broadcast($channels, $event, $payload);
    }
}






if (! function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * @phpstan-assert-if-false !=null|'' $value
     *
     * @phpstan-assert-if-true !=numeric|bool $value
     *
     * @param  mixed  $value
     * @return bool
     */
    function blank($value)
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Model) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        if ($value instanceof Stringable) {
            return trim((string) $value) === '';
        }

        return empty($value);
    }
}

if (! function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     *
     * @phpstan-assert-if-true !=null|'' $value
     *
     * @phpstan-assert-if-false !=numeric|bool $value
     *
     * @param  mixed  $value
     * @return bool
     */
    function filled($value)
    {
        return ! blank($value);
    }
}

if (! function_exists('fluent')) {
    /**
     * Create a Fluent object from the given value.
     *
     * @param  object|array  $value
     * @return \Illuminate\Support\Fluent
     */
    function fluent($value)
    {
        return new Fluent($value);
    }
}

if (! function_exists('literal')) {
    /**
     * Return a new literal or anonymous object using named arguments.
     *
     * @return \stdClass
     */
    function literal(...$arguments)
    {
        if (count($arguments) === 1 && array_is_list($arguments)) {
            return $arguments[0];
        }

        return (object) $arguments;
    }
}

if (! function_exists('object_get')) {
    /**
     * Get an item from an object using "dot" notation.
     *
     * @template TValue of object
     *
     * @param  TValue  $object
     * @param  string|null  $key
     * @param  mixed  $default
     * @return ($key is empty ? TValue : mixed)
     */
    function object_get($object, $key, $default = null)
    {
        if (is_null($key) || trim($key) === '') {
            return $object;
        }

        foreach (explode('.', $key) as $segment) {
            if (! is_object($object) || ! isset($object->{$segment})) {
                return value($default);
            }

            $object = $object->{$segment};
        }

        return $object;
    }
}

if (! function_exists('laravel_cloud')) {
    /**
     * Determine if the application is running on Laravel Cloud.
     *
     * @return bool
     */
    function laravel_cloud()
    {
        return ($_ENV['LARAVEL_CLOUD'] ?? false) === '1' ||
               ($_SERVER['LARAVEL_CLOUD'] ?? false) === '1';
    }
}

if (! function_exists('once')) {
    /**
     * Ensures a callable is only called once, and returns the result on subsequent calls.
     *
     * @template  TReturnType
     *
     * @param  callable(): TReturnType  $callback
     * @return TReturnType
     */
    function once(callable $callback)
    {
        $onceable = Onceable::tryFromTrace(
            debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2),
            $callback,
        );

        return $onceable ? Once::instance()->value($onceable) : call_user_func($callback);
    }
}

if (! function_exists('optional')) {
    /**
     * Provide access to optional objects.
     *
     * @template TValue
     * @template TReturn
     *
     * @param  TValue  $value
     * @param  (callable(TValue): TReturn)|null  $callback
     * @return ($callback is null ? Optional : ($value is null ? null : TReturn))
     */
    function optional($value = null, ?callable $callback = null)
    {
        if (is_null($callback)) {
            return new Optional($value);
        } elseif (! is_null($value)) {
            return $callback($value);
        }
    }
}

if (! function_exists('preg_replace_array')) {
    /**
     * Replace a given pattern with each value in the array in sequentially.
     *
     * @param  string  $pattern
     * @param  array  $replacements
     * @param  string  $subject
     * @return string
     */
    function preg_replace_array($pattern, array $replacements, $subject)
    {
        return preg_replace_callback($pattern, function () use (&$replacements) {
            foreach ($replacements as $value) {
                return array_shift($replacements);
            }
        }, $subject);
    }
}

if (! function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     *
     * @template TValue
     *
     * @param  int|array<int, int>  $times
     * @param  callable(int): TValue  $callback
     * @param  int|\Closure(int, \Throwable): int  $sleepMilliseconds
     * @param  (callable(\Throwable): bool)|null  $when
     * @return TValue
     *
     * @throws \Throwable
     */
    function retry($times, callable $callback, $sleepMilliseconds = 0, $when = null)
    {
        $attempts = 0;

        $backoff = [];

        if (is_array($times)) {
            $backoff = $times;

            $times = count($times) + 1;
        }

        beginning:
        $attempts++;
        $times--;

        try {
            return $callback($attempts);
        } catch (Throwable $e) {
            if ($times < 1 || ($when && ! $when($e))) {
                throw $e;
            }

            $sleepMilliseconds = $backoff[$attempts - 1] ?? $sleepMilliseconds;

            if ($sleepMilliseconds) {
                Sleep::usleep(value($sleepMilliseconds, $attempts, $e) * 1000);
            }

            goto beginning;
        }
    }
}

if (! function_exists('str')) {
    /**
     * Get a new stringable object from the given string.
     *
     * @param  string|null  $string
     * @return ($string is null ? object : Stringable)
     */
    function str($string = null)
    {
        if (func_num_args() === 0) {
            return new class
            {
                public function __call($method, $parameters)
                {
                    return Str::$method(...$parameters);
                }

                public function __toString()
                {
                    return '';
                }
            };
        }

        return new Stringable($string);
    }
}

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param  TValue  $value
     * @param  (callable(TValue): mixed)|null  $callback
     * @return ($callback is null ? HigherOrderTapProxy : TValue)
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('throw_if')) {
    /**
     * Throw the given exception if the given condition is true.
     *
     * @template TValue
     * @template TException of \Throwable
     *
     * @param  TValue  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return ($condition is true ? never : ($condition is non-empty-mixed ? never : TValue))
     *
     * @throws TException
     */
    function throw_if($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if ($condition) {
            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }
}

if (! function_exists('throw_unless')) {
    /**
     * Throw the given exception unless the given condition is true.
     *
     * @template TValue
     * @template TException of \Throwable
     *
     * @param  TValue  $condition
     * @param  TException|class-string<TException>|string  $exception
     * @param  mixed  ...$parameters
     * @return ($condition is false ? never : ($condition is non-empty-mixed ? TValue : never))
     *
     * @throws TException
     */
    function throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
    {
        throw_if(! $condition, $exception, ...$parameters);

        return $condition;
    }
}

if (! function_exists('trait_uses_recursive')) {
    /**
     * Returns all traits used by a trait and its traits.
     *
     * @param  object|string  $trait
     * @return array
     */
    function trait_uses_recursive($trait)
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += trait_uses_recursive($trait);
        }

        return $traits;
    }
}

if (! function_exists('transform')) {
    /**
     * Transform the given value if it is present.
     *
     * @template TValue
     * @template TReturn
     * @template TDefault
     *
     * @param  TValue  $value
     * @param  callable(TValue): TReturn  $callback
     * @param  TDefault|callable(TValue): TDefault  $default
     * @return ($value is empty ? TDefault : TReturn)
     */
    function transform($value, callable $callback, $default = null)
    {
        if (filled($value)) {
            return $callback($value);
        }

        if (is_callable($default)) {
            return $default($value);
        }

        return $default;
    }
}

if (! function_exists('windows_os')) {
    /**
     * Determine whether the current environment is Windows based.
     *
     * @return bool
     */
    function windows_os()
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (! function_exists('with')) {
    /**
     * Return the given value, optionally passed through the given callback.
     *
     * @template TValue
     * @template TReturn
     *
     * @param  TValue  $value
     * @param  (callable(TValue): (TReturn))|null  $callback
     * @return ($callback is null ? TValue : TReturn)
     */
    function with($value, ?callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}
