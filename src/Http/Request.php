<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

class Request
{
    public const HEADER_X_FORWARDED_FOR = 'HTTP_X_FORWARDED_FOR';
    public const HEADER_X_FORWARDED_HOST = 'HTTP_X_FORWARDED_HOST';
    public const HEADER_X_FORWARDED_PORT = 'HTTP_X_FORWARDED_PORT';
    public const HEADER_X_FORWARDED_PROTO = 'HTTP_X_FORWARDED_PROTO';

    protected $query;
    protected $body;
    protected $attributes;
    protected $cookies;
    protected $files;
    protected $server;
    protected $headers;
    protected $proxyheader;
    protected $trustedProxies = [];
    protected Session $session;

    public UserModelInterface $auth_user;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->attributes = [];
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = getallheaders();
        $this->proxyheader = 0;

        // Handle JSON payload
        if ($this->isJson()) {
            $jsonData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = array_merge($this->body, $jsonData);
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

    public function replaceInput(array $input)
    {
        $this->body = $input;
    }

    public function replaceQuery(array $query)
    {
        $this->query = $query;
    }

    public function all()
    {
        return array_merge($this->query, $this->body, $this->attributes);
    }

    public function has($key)
    {
        return $this->input($key) !== null || $this->query($key) !== null;
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

    public function cookie($key = null, $default = null)
    {
        if ($key == null)
            return $this->cookies;
        return $this->retrieveItem($this->cookies, $key, $default);
    }

    public function header($key = null, $default = null)
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
        return $this->header('Content-Type') === 'application/json';
    }

    public function wantsJson()
    {
        return $this->expectsJson();
    }

    function expectsJson()
    {
        return strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    }
    
    function isXmlHttpRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function getPathInfo()
    {
        return $this->server('REQUEST_URI', '');
    }

    public function getOriginPathInfo()
    {
        return $this->server('ORIG_PATH_INFO', '');
    }

    public function getRequestUri()
    {
        return $this->server('REQUEST_URI', '');
    }

    public function hasSession()
    {
        return isset($this->session);
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
        // preg_match($regex, $this->getPathInfo(), $matches);
        strpos($this->getPathInfo(), $regex) === true;
    }

    public function url()
    {
        $requestUri = rtrim(filter_var($this->server('REQUEST_URI'), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');
        return $requestUri;
    }

    public function getScheme()
    {
        if ($this->isFromTrustedProxy() && $this->header('X-Forwarded-Proto')) {
            return $this->headers['X-Forwarded-Proto'];
        }

        return $this->header('HTTPS') && $this->headers['HTTPS'] === 'on' ? 'https' : 'http';
    }

    public function getHost()
    {
        if ($this->isFromTrustedProxy() && $this->header('X-Forwarded-Host')) {
            return $this->headers['X-Forwarded-Host'];
        }

        return $this->headers['HTTP_HOST'];
    }

    public function getSchemeAndHttpHost()
    {
        return $this->getScheme() . '://' . $this->getHost();
    }

    public function setTrustedProxies(array $proxies, int $headers = null)
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

        $clientIp = $this->headers['REMOTE_ADDR'] ?? '';

        return in_array($clientIp, $this->trustedProxies);
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
 