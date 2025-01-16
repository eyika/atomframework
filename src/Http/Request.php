<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Exceptions\BaseException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;
use Eyika\Atom\Framework\Support\Facade\Session as FacadeSession;
use Eyika\Atom\Framework\Support\Validator;

class Request
{
    public const HEADER_X_FORWARDED_FOR = 'HTTP_X_FORWARDED_FOR';
    public const HEADER_X_FORWARDED_HOST = 'HTTP_X_FORWARDED_HOST';
    public const HEADER_X_FORWARDED_PORT = 'HTTP_X_FORWARDED_PORT';
    public const HEADER_X_FORWARDED_PROTO = 'HTTP_X_FORWARDED_PROTO';

    protected $query;
    public array $route_params;
    protected $body;
    protected $attributes;
    protected Arrayable $cookies;
    protected $files;
    protected $server;
    protected array $headers;
    protected $proxyheader;
    protected $trustedProxies = [];
    protected Session $session;
    protected bool $isAssetRequest;

    public UserModelInterface $auth_user;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->attributes = [];
        $this->route_params = [];
        $this->cookies = new Arrayable();
        $this->isAssetRequest = false;

        foreach ($_COOKIE as $name => $value) {
            // Create a new Cookie instance for each $_COOKIE element
            $this->cookies->set($name, new Cookie($name, $value));
        }
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = getallheaders();
        $this->proxyheader = 0;

        // Handle JSON payload
        if ($this->isJson()) {
            $jsonData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = array_merge($this->body, $jsonData ?? []);
            }
        }
    }

    public function __get($name) {
        if ($item = $this->retrieveItem($this->attributes, $name)) {
            return $item;
        }
        $data = array_merge($this->query, $this->body, $this->cookies, $this->files, $this->server, $this->headers);
        return $this->retrieveItem($data, $name);
    }

    public function __set($name, $value) {
        $this->attributes[$name] = $value;
    }

    public function isAssetRequest(bool|null $value = null)
    {
        if ($value === null) {
            return $this->isAssetRequest;
        }
        $this->isAssetRequest = $value;
    }

    public static function capture()
    {
        return new static();
    }

    public function query($key = null, $default = null)
    {
        if ($key == null)
            return $this->query;

        return $this->retrieveItem($this->query, $key, $default);
    }

    public function input($key = null, $default = null)
    {
        if ($key == null)
            return $this->body;
        return $this->retrieveItem($this->body, $key, $default);
    }

    public function merge(array $data)
    {
        $this->body = array_merge($this->body, $data);
    }

    public function only(array $keys)
    {
        return Arr::only($this->input(), $keys);
    }

    public function except(array $keys)
    {
        return Arr::except($this->input(), $keys);
    }

    public function replaceInput(array $input)
    {
        $this->body = $input;
    }

    public function replaceQuery(array $query)
    {
        $this->query = $query;
    }

    public function replace(string $bodyOrQuery, array $data)
    {
        switch ($bodyOrQuery) {
            case 'query':
                $this->query = $data;
                break;
            case 'body':
                $this->body = $data;
                break;
            default:
                throw new BaseException("request data grou $bodyOrQuery does not exist");
        }
    }

    public function all()
    {
        return array_merge($this->query, $this->body, $this->attributes);
    }

    public function has($key)
    {
        return $this->input($key) !== null || $this->query($key) !== null;
    }

    public function hasHeader($key)
    {
        return !empty($this->headers($key));
    }

    public function hasBody()
    {
        return $this->server('CONTENT_LENGTH') ?? 0 > env('CONTENT_LENGTH_MIN');
    }

    public function file($key = null)
    {
        if ($key == null)
            return $this->files;
        return $this->retrieveItem($this->files, $key);
    }

    /**
     * @return Arrayable<Cookie>
     */
    public function cookies(): Arrayable
    {
        return $this->cookies;
    }

    public function cookie($key = null, $default = null)
    {
        if ($key == null)
            return $this->cookies;
        return $this->retrieveItem($this->cookies, $key, $default);
    }

    public function headers($key = null, $default = null)
    {
        if ($key == null)
            return $this->headers;
        return $this->retrieveItem($this->headers, $key, $default);
    }

    public function server($key = null, $default = null)
    {
        if ($key == null)
            return $this->server;
        return $this->retrieveItem($this->server, $key, $default);
    }

    public function method()
    {
        return $this->server('REQUEST_METHOD', 'GET');
    }
    
    public function documentRoot()
    {
        return $this->server('DOCUMENT_ROOT', '');
    }

    public function isMethod($method)
    {
        return strtolower($this->method()) === strtolower($method);
    }

    public function isJson()
    {
        return $this->headers('Content-Type') === 'application/json';
    }

    public function isOptions()
    {
        return $this->method() === 'OPTIONS';
    }

    public function wantsJson()
    {
        return $this->expectsJson() || $this->isJson();
    }

    function expectsJson()
    {
        return strpos($this->server('HTTP_ACCEPT', ''), 'application/json') !== false;
    }
    
    function isXmlHttpRequest()
    {
        return strtolower($this->server('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    function isHtml()
    {
        return !$this->wantsJson() && !$this->isXmlHttpRequest();
    }

    function isNotHtml()
    {
        return $this->wantsJson() || $this->isXmlHttpRequest();
    }

    public function pathInfo()
    {
        return $this->server('REQUEST_URI', '');
    }

    public function originPathInfo()
    {
        return $this->server('ORIG_PATH_INFO', '');
    }

    public function requestUri()
    {
        return $this->server('REQUEST_URI', '');
    }

    public function hasSession()
    {
        return isset($this->session) && $this->session->active();
    }

    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    public function getSession()
    {
        return $this->session;
    }

    /**
     * check if the request uri matches this regex string
     */
    public function is(string $regex)
    {
        // preg_match($regex, $this->pathInfo(), $matches);
        strpos($this->pathInfo(), $regex) === true;
    }

    public function url()
    {
        $requestUri = rtrim(filter_var($this->server('REQUEST_URI'), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');
        return $requestUri;
    }

    public function uri()
    {
        return $this->url();
    }

    public function scheme()
    {
        if ($this->isFromTrustedProxy() && $this->headers('X-Forwarded-Proto')) {
            return $this->headers['X-Forwarded-Proto'];
        }

        return $this->headers('HTTPS') && $this->headers['HTTPS'] === 'on' ? 'https' : 'http';
    }

    public function host()
    {
        if ($this->isFromTrustedProxy() && $this->headers('X-Forwarded-Host')) {
            return $this->headers['X-Forwarded-Host'];
        }

        return $this->server['HTTP_HOST'];
    }

    public function address()
    {
        return $this->server('REMOTE_ADDR', '');
    }

    public static function clientIp()
    {
        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && \preg_match('/^(d{1,3}).(d{1,3}).(d{1,3}).(d{1,3})$/', $_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    public function schemeAndHttpHost()
    {
        return $this->scheme() . '://' . $this->host();
    }

    public function setTrustedProxies(array $proxies, int|null $headers = null)
    {
        $this->trustedProxies = $proxies;

        // If headers are provided, merge them with the existing headers
        if (!empty($headers)) {
            $this->proxyheader = $headers;
        }
    }

    public function isFromTrustedProxy()
    {
        if (empty($this->trustedProxies)) {
            return false;
        }

        $clientIp = $this->server('REMOTE_ADDR', '');

        return in_array($clientIp, $this->trustedProxies);
    }

    public function hasValidSignature(): bool
    {
        return $this->validateSignature();
    }

    public function hasValidSignatureWhileIgnoring(array $ignoredParams): bool
    {
        return $this->validateSignature($ignoredParams);
    }

    protected function validateSignature(array $ignoredParams = []): bool
    {
        throw new NotImplementedException('this method validateSignature is not yet implemented');
    }

    public function validate(array $params, string $separator = '|'): bool|array
    {
        return Validator::validate($this->input(), $params, $separator);
    }

    public function validationErrors()
    {
        return Validator::$errors;
    }

    protected function retrieveItem($source, $key = null, $default = null)
    {
        if ($key === null) {
            return $source;
        }

        return $source[$key] ?? $default;
    }

    protected function setItem($source, string $key, string|array $value)
    {
        $this->{$source}[$key] = $value;
    }

    public function validateCsrf()
    {
        $session_csrf = FacadeSession::get('csrf');
        $request_csrf = $this->input('csrf');

        if (!$session_csrf || !$request_csrf) {
            return false;
        }
        if ($session_csrf != $request_csrf) {
            return false;
        }
        return true;
    }
}


/**
 * Usage examples
 */

//  <?php
//  require 'Request.php';
 
//  // Capture the current request
//  $request = Request::capture();

//  // Retrieve query parameter
//  $userId = $request->query('user_id');
 
//  // Retrieve form input
//  $username = $request->input('username');
 
//  // Retrieve all inputs
//  $allInputs = $request->all();
 
//  // Check if a specific input exists
//  if ($request->has('email')) {
//      $email = $request->input('email');
//  }
 
//  // Retrieve a file
//  $file = $request->file('profile_picture');
 
//  // Retrieve a cookie
//  $cookie = $request->cookie('session_id');
 
//  // Retrieve a header
//  $userAgent = $request->header('User-Agent');
 
//  // Check request method
//  if ($request->isMethod('post')) {
//      // Handle POST request
//  }
 
//  // Check if the request is JSON
//  if ($request->isJson()) {
//      $jsonData = $request->all();
//  }
 
//  // Get the request method
//  $method = $request->method();
 