<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Exceptions\BaseException;
use Eyika\Atom\Framework\Exceptions\Http\RequestException;
use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Exceptions\ValidationException;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Auth\Contracts\AuthenticatableInterface;
use Eyika\Atom\Framework\Support\Auth\User;
use Eyika\Atom\Framework\Support\Facade\Session as FacadeSession;
use Eyika\Atom\Framework\Support\Storage\File;
use Eyika\Atom\Framework\Support\Storage\FileUploadProperties;
use Eyika\Atom\Framework\Support\Validator;

use function PHPUnit\Framework\isNull;

class Request
{
    public const HEADER_X_FORWARDED_FOR = 'HTTP_X_FORWARDED_FOR';
    public const HEADER_X_FORWARDED_HOST = 'HTTP_X_FORWARDED_HOST';
    public const HEADER_X_FORWARDED_PORT = 'HTTP_X_FORWARDED_PORT';
    public const HEADER_X_FORWARDED_PROTO = 'HTTP_X_FORWARDED_PROTO';

    protected $query;
    public array $route_params;
    protected $body;
    protected $input;
    protected $attributes;
    protected Arrayable $cookies;
    /** @property File[] $files */
    protected $files;
    protected $server;
    protected array $headers;
    protected $proxyheader;
    protected $trustedProxies = [];
    protected Session $session;
    protected bool $isAssetRequest;

    public AuthenticatableInterface|User|null $auth_user;

    public function __construct()
    {
        $this->auth_user = null;
        $this->cookies = new Arrayable();
        $this->isAssetRequest = false;

        foreach ($_COOKIE as $name => $value) {
            // Create a new Cookie instance for each $_COOKIE element
            if (!in_array($name, config('cookies.whitelisted_cookies', []))) {
                $this->cookies->set($name, new Cookie($name, $value));
            }
        }
        $this->server = $_SERVER;
        $this->headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $this->query = $_GET;
        $this->setRequestBodyAndFiles();
        $this->input = [...$this->body, ...$this->files];
        // $this->body = $_POST;
        $this->attributes = [];
        $this->route_params = [];
        $this->proxyheader = 0;
    }

    protected function setRequestBodyAndFiles()
    {
        if ($this->isMethod('PUT') && strpos($this->headers['Content-Type'] ?? '', 'multipart/form-data') === 0) {
            $this->body = $this->parseMultipartPutRequest();
        } elseif ($this->isJson()) {
            // Read raw JSON input if content-type is JSON
            $jsonData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $jsonData ?? [];
            } else {
                $this->body = [];
            }
        } else {
            $this->body = $_POST;
        }
    
        $this->initRequestFiles();
    }

    protected function parseMultipartPutRequest()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
        // Ensure it's a multipart/form-data request
        if (strpos($contentType, 'multipart/form-data') !== 0) {
            return ['error' => 'Invalid Content-Type'];
        }
    
        // Extract boundary from Content-Type
        preg_match('/boundary=(.*)$/', $contentType, $matches);
        if (!isset($matches[1])) {
            return ['error' => 'No boundary found'];
        }
        $boundary = $matches[1];
    
        // Read raw input
        $rawData = file_get_contents("php://input");
    
        // Split by boundary
        $parts = explode("--" . $boundary, $rawData);
    
        $files = [];
        $fields = [];
    
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || $part === "--") continue; // Skip empty or closing boundary
    
            // Ensure the part contains headers and body
            if (!str_contains($part, "\r\n\r\n")) {
                continue; // Skip malformed parts
            }
    
            // Separate headers from content
            $sections = explode("\r\n\r\n", $part, 2);
            if (count($sections) < 2) {
                continue; // Skip invalid parts
            }
    
            list($rawHeaders, $content) = $sections;
    
            $rawHeaders = trim($rawHeaders);
            $content = trim($content);
    
            // Parse headers
            $headers = [];
            foreach (explode("\r\n", $rawHeaders) as $header) {
                if (!str_contains($header, ": ")) continue; // Ensure valid header format
                list($name, $value) = explode(": ", $header, 2);
                $headers[strtolower($name)] = $value;
            }
    
            // Check if it's a file
            if (isset($headers['content-disposition']) && preg_match('/name="([^"]+)"(?:; filename="([^"]+)")?/', $headers['content-disposition'], $matches)) {
                $fieldName = $matches[1];
                $fileName = $matches[2] ?? null;
    
                if ($fileName) {
                    // It's a file
                    $files[$fieldName] = [
                        'name' => $fileName,
                        'type' => $headers['content-type'] ?? 'application/octet-stream',
                        'tmp_name' => $this->saveTempFile($content),
                        'size' => strlen($content),
                    ];
                } else {
                    // It's a normal form field
                    $fields[$fieldName] = $content;
                }
            }
        }
    
        $_FILES = $files;
        return $fields;
    }    

    // Save file to a temporary location
    protected function saveTempFile($content)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'put_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }
    
    protected function initRequestFiles()
    {
        $this->files = [];

        foreach ($_FILES as $fieldName => $fileData) {
            // Normalize multiple file uploads
            if (is_array($fileData['name'])) {
                foreach ($fileData['name'] as $index => $name) {
                    $file = new File();
                    $file->setUploadProperties(new FileUploadProperties(
                        $name,
                        $fileData['type'][$index],
                        $fileData['tmp_name'][$index],
                        $fileData['size'][$index],
                        $fileData['error'][$index] ?? null
                    ));
                    $this->files[$fieldName][] = $file;
                }
            } else {
                // Single file upload
                $file = new File();
                $file->setUploadProperties(new FileUploadProperties(
                    $fileData['name'],
                    $fileData['type'],
                    $fileData['tmp_name'],
                    $fileData['size'],
                    $fileData['error'] ?? null
                ));
                $this->files[$fieldName] = $file;
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

    public function body($key = null, $default = null)
    {
        if ($key == null)
            return $this->body;
        return $this->retrieveItem($this->body, $key, $default);
    }

    public function input($key = null, $default = null)
    {
        if ($key == null)
            return $this->input;
        return $this->retrieveItem($this->input, $key, $default);
    }

    public function merge(array $data)
    {
        $this->input = array_merge($this->input, $data);
    }

    public function only(array $keys)
    {
        return Arr::only($this->body, $keys);
    }

    public function except(array $keys)
    {
        return Arr::except($this->body, $keys);
    }

    public function replaceInput(array $input)
    {
        $this->input = $input;
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
                throw new BaseException("request data group $bodyOrQuery does not exist");
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

    /**
     * @return File[]
     */
    public function files()
    {
        return $this->files;
    }

    public function file(string $key): File|null
    {
        return $this->retrieveItem($this->files, $key);
    }

    public function hasFile(string $key)
    {
        return !(isNull($this->files($key)));
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
        return $this->retrieveItem($this->headers, strtolower($key), $default);
    }

    public function header($key, $default = null)
    {
        return $this->headers($key, $default);
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
        if ($this->isFromTrustedProxy() && $this->server('HTTP_X_FORWARDED_PROTO')) {
            return $this->server('HTTP_X_FORWARDED_PROTO');
        }

        if (
            ($this->server('HTTPS') && $this->server('HTTPS', '') !== 'off') ||
            ($this->server('SERVER_PORT') && $this->server('SERVER_PORT', null) == 443) ||
            ($this->server('REQUEST_SCHEME') && $this->server('REQUEST_SCHEME', '') === 'https')
        ) {
            return 'https';
        }

        return 'http';
    }

    public function host()
    {
        if ($this->isFromTrustedProxy() && $this->headers('X-Forwarded-Host')) {
            return $this->headers('X-Forwarded-Host');
        }

        return $this->server('HTTP_HOST');
    }

    public function address()
    {
        return $this->server('REMOTE_ADDR', '');
    }

    public function clientIp()
    {
        if (\preg_match('/^(d{1,3}).(d{1,3}).(d{1,3}).(d{1,3})$/', $this->server('HTTP_X_FORWARDED_FOR', ''))) {
            return $this->server('HTTP_X_FORWARDED_FOR');
        }
        return $this->address();
    }

    public function ip()
    {
        return $this->clientIp();
    }

    public function userAgent()
    {
        return $this->server('HTTP_USER_AGENT');
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

    public function validateOrFail(array $params, string $separator = '|', $message = 'errors in request', $code = 422)
    {
        Validator::setErrorMessage($message);
        Validator::setErrorCode($code);
        return Validator::validate($this->input(), $params, $separator, true);
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
 