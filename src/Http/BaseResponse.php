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

    protected $headers = [];
    protected $statusCode = 200;
    protected $body = '';
    protected $errors = [];
    protected $validationErrors = [];
    protected $inputs = [];
    protected $viewData = [];

    protected $isFileResponse = false;
    protected $isRedirect = false;
    protected $shouldCompileView = false;
    protected $file_path = '';

    protected $viewFileName = '';
    protected Arrayable $cookies;

    public function __construct()
    {
        $this->cookies = new Arrayable();
    }

    // Method to set a cookie header
    public function setCookie($name, $value = '', $expiry = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true)
    {
        $this->cookies->set($name, new Cookie($name, $value, $expiry, $path, $domain, $secure, $httpOnly));
        return $this;
    }

    // Method to set a header
    public function setHeader(string $key, string $content, int|null $code = null, bool $replace = true)
    {
        if ($code)
            $this->headers[] = [$key => [$content, $replace, $code]];
        else $this->headers[] = [$key => [$content, $replace]];

        return $this;
    }

    public function cookies()
    {
        return $this->cookies;
    }

    // Set a status code
    public function status(int $code)
    {
        $this->statusCode = $code;
        return $this;
    }

    // Add errors to the response
    public function withErrors(array $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    // Add input validation errors to the response
    public function withValidationErrors(array $validationErrors)
    {
        $this->validationErrors = $validationErrors;
        return $this;
    }

    // Add inputs to the response
    public function withInputs()
    {
        $this->inputs = FacadeRequest::input();
        logger()->info("facadeRequest inputs are: ", $this->inputs);
        return $this;
    }

    // Method to send the response headers and content
    public function send()
    {
        http_response_code($this->statusCode);
        $this->sendHeaders();

        if (FacadeRequest::isNotHtml()) {
            echo $this->body;
            return true;
        }

        if ($this->isFileResponse) {
            ob_clean();
            flush();
            readfile($this->file_path);
            return true;
        }

        if ($this->isRedirect) {
            return true;
        }

        if ($this->shouldCompileView) {
            $this->compileView();
        }

        echo $this->body;
        return true;
    }

    private function compileView()
    {
        try {
            $path = resource_path('views');

            if (config('view.use_advance_engine')) {
                $view = new Blade($path, oldInputs: $this->inputs);
                $this->viewData['errors'] = new Arrayable(array_key_exists('errors', $this->viewData) ? array_merge($this->viewData['errors'], $this->errors) : $this->errors);
                $view->atomSetValidationErrors($this->validationErrors);

                $content = $view->run($this->viewFileName, $this->viewData);
            } else {
                $content = Twig::make($this->viewFileName.".blade.php", "$path/", $this->viewData, true);
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
        $this->cookies->each(function (Cookie $cookie) {
            header("{$cookie->getName()}: {$cookie->getValue()}");
        });
        foreach ($this->headers as $header) {
            foreach ($header as $key => $value) {
                $val = str_contains($key, 'Set-Cookie') ? (string) $value[0] : $value[0];
                isset($value[2]) ? header("{$key}: {$val}", $value[1], $value[2]) : header("{$key}: {$val}", $value[1]);
            }
        }
    }

    public function body(string $content)
    {
        $this->body = $content;
        return $this;
    }

    public function setIsFileResponse(string $file_path, bool $value = true)
    {
        $this->isFileResponse = $value;
        $this->file_path = $file_path;
        return $this;
    }
}
