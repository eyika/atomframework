<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Facade\Request as FacadeRequest;
use Eyika\Atom\Framework\Support\View\Blade;
use Eyika\Atom\Framework\Support\View\Twig;

class BaseResponse
{
    public const STATUS_OK = 200;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_CREATED = 201;
    public const STATUS_MOVED_PERMANENTLY = 301;
    public const STATUS_FOUND = 302;
    public const STATUS_SEE_OTHER = 303;
    public const STATUS_NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_FORBIDDEN = 403;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_UNPROCESSABLE_ENTITY = 422;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;
    public const STATUS_SERVICE_NOT_AVAILABLE = 503;

    protected const METHOD_TO_FUNC = [
        self::STATUS_OK => 'ok',
        self::STATUS_NO_CONTENT => 'noContent',
        self::STATUS_CREATED => 'created',
        self::STATUS_MOVED_PERMANENTLY => 'movedPermanently',
        self::STATUS_FOUND => 'found',
        self::STATUS_SEE_OTHER => 'seeOther',
        self::STATUS_NOT_MODIFIED => 'notModified',
        self::STATUS_BAD_REQUEST => 'badRequest',
        self::STATUS_UNAUTHORIZED => 'unauthorized',
        self::STATUS_FORBIDDEN => 'forbidden',
        self::STATUS_NOT_FOUND => 'notFound',
        self::STATUS_UNPROCESSABLE_ENTITY => 'unprocessableEntity',
        self::STATUS_INTERNAL_SERVER_ERROR => 'serverError',
    ];

    protected static $headers = [];
    protected static $statusCode = 200;
    protected static $body = '';
    protected static $errors = [];
    protected static $inputs = [];
    protected static $viewData = [];

    protected static $instantiated = false;
    protected static $isFileResponse = false;
    protected static $isRedirect = false;
    protected static $shouldCompileView = false;
    protected static $file_path = '';

    protected static $viewFileName = '';
    protected static Arrayable $cookies;

    public function __construct()
    {
        static::$cookies = new Arrayable();
    }

    // Method to set a cookie header
    public static function setCookie($name, $value = '', $expiry = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true)
    {
        static::$cookies->set($name, new Cookie($name, $value, $expiry, $path, $domain, $secure, $httpOnly));
        return new static;
    }

    // Method to set a header
    public static function setHeader(string $key, string $content, int|null $code = null, bool $replace = true)
    {
        if ($code)
            static::$headers[] = [$key => [$content, $replace, $code]];
        else static::$headers[] = [$key => [$content, $replace]];

        return new static;
    }

    public static function cookies()
    {
        return static::$cookies;
    }

    // Set a status code
    public static function status(int $code)
    {
        static::$statusCode = $code;
        return new static;
    }

    // Add errors to the response
    public static function withErrors(array $errors)
    {
        static::$errors = $errors;
        return new static;
    }

    // Add inputs to the response
    public static function withInputs()
    {
        static::$inputs = FacadeRequest::input();
        return new static;
    }

    // Method to send the response headers and content
    public static function send()
    {
        http_response_code(self::$statusCode);
        static::sendHeaders();

        if (FacadeRequest::wantsJson() || FacadeRequest::isXmlHttpRequest()) {
            echo self::$body;
            return true;
        }

        if (self::$isFileResponse) {
            ob_clean();
            flush();
            readfile(self::$file_path);
            return true;
        }

        if (self::$isRedirect) {
            return true;
        }

        if (static::$shouldCompileView) {
            self::compileView();
        }

        echo self::$body;
        return true;
    }

    private static function compileView()
    {
        try {
            $path = resource_path('views');

            if (config('view.use_advance_engine')) {
                $view = new Blade($path);
                static::$viewData['errors'] = array_key_exists('errors', static::$viewData) ? array_merge(static::$viewData['errors'], static::$errors) : static::$errors;

                $content = $view->run(static::$viewFileName, static::$viewData);
            } else {
                $content = Twig::make(static::$viewFileName.".blade.php", "$path/", static::$viewData, true);
            }

            self::body($content)
                ->setHeader('Content-Type', 'text/html; charset=utf-8');
            return;
        } catch (Exception $e) {
            self::body("Server Error: " . $e->getMessage())
                ->status(self::STATUS_INTERNAL_SERVER_ERROR)
                ->setHeader('Content-Type', 'text/html; charset=utf-8');
            return;
        }
    }

    protected static function sendHeaders()
    {
        self::$cookies->each(function (Cookie $cookie) {
            header("{$cookie->getName()}: {$cookie->getValue()}");
        });
        foreach (static::$headers as $header) {
            foreach ($header as $key => $value) {
                $val = str_contains($key, 'Set-Cookie') ? (string) $value[0] : $value[0];
                isset($value[2]) ? header("{$key}: {$val}", $value[1], $value[2]) : header("{$key}: {$val}", $value[1]);
            }
        }
    }

    public static function body(string $content)
    {
        static::$body = $content;
        return new static;
    }

    public function setIsFileResponse(string $file_path, bool $value = true)
    {
        self::$isFileResponse = $value;
        self::$file_path = $file_path;
        return new static;
    }
}
