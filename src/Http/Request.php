<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;
use Eyika\Atom\Framework\Support\Database\Model;

class Request
{
    protected $query;
    protected $body;
    protected $attributes;
    protected $cookies;
    protected $files;
    protected $server;
    protected $headers;

    public Model & UserModelInterface $auth_user;

    public function __construct()
    {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->attributes = [];
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;
        $this->server = $_SERVER;
        $this->headers = getallheaders();

        // Handle JSON payload
        if ($this->isJson()) {
            $jsonData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = array_merge($this->body, $jsonData);
            }
        }
    }

    public function __get($name) {
        $data = array_merge($this->query, $this->body, $this->cookies, $this->files, $this->server, $this->headers, $this->attributes);
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
        return $this->isJson();
    }

    public function url()
    {
        $requestUri = rtrim(filter_var($this->server('REQUEST_URI'), FILTER_SANITIZE_URL), '/');
        $requestUri = strtok($requestUri, '?');
        return $requestUri;
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
 