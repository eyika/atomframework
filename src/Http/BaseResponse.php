<?php

namespace Eyika\Atom\Framework\Http;

use Cookie;
use Exception;
use Eyika\Atom\Framework\Support\Facade\Request as FacadeRequest;
use Eyika\Atom\Framework\Support\View\Blade;
use Eyika\Atom\Framework\Support\View\Twig;

class BaseResponse
{
    public const STATUS_OK = 200;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_CREATED = 201;
    public const NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_UNPROCESSABLE_ENTITY = 422;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;

    protected const METHOD_TO_FUNC = [
        self::STATUS_OK => 'ok',
        self::STATUS_NO_CONTENT => 'noContent',
        self::STATUS_CREATED => 'created',
        self::NOT_MODIFIED => 'notModified',
        self::STATUS_BAD_REQUEST => 'badRequest',
        self::STATUS_NOT_FOUND => 'notFound',
        self::STATUS_UNAUTHORIZED => 'unauthorized',
        self::STATUS_UNPROCESSABLE_ENTITY => 'unprocessable_entity',
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
    protected static $file_path = '';

    protected static $viewFileName = '';

    // Method to set a cookie header
    public static function setCookie(Cookie $cookie)
    {
        static::$headers[] = ['Set-Cookie' => [$cookie, 0]];
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
        $cookies = [];

        foreach (static::$headers as $header) {
            foreach ($header as $key => $value) {
                if (str_contains($key, 'Set-Cookie')) {
                    $cookies[] = [$key => $value];
                }
            }
        }
        return $cookies;
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
    public function withInputs()
    {
        static::$inputs = FacadeRequest::input();
        return new static;
    }

    // Method to send the response headers and content
    public function send()
    {
        http_response_code(self::$statusCode);
        $this->sendHeaders();

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

        self::compileView();

        echo self::$body;
        return true;
    }

    private function compileView()
    {
        try {
            $path = resource_path('views');

            if (config('view.use_advance_engine')) {
                $view = new Blade($path);
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

    protected function sendHeaders()
    {
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
