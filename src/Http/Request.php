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
    protected array $files;
    /** Files parsed from a PUT multipart body — kept on the instance instead of
     *  mutating the $_FILES global (WRK-13). */
    protected array $rawFiles = [];
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

        // Fetch the whitelist once, not once per cookie (PERF-07).
        $whitelistedCookies = config('cookies.whitelisted_cookies', []);
        foreach ($_COOKIE as $name => $value) {
            // Create a new Cookie instance for each $_COOKIE element
            if (!in_array($name, $whitelistedCookies)) {
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
        if ($this->isMethod('PUT') && strpos((string) $this->headers('Content-Type'), 'multipart/form-data') === 0) {
            $this->body = $this->parseMultipartRequest();
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

    protected function parseMultipartRequest()
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
                        'name' => basename($fileName), // strip client-supplied path (traversal)
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
    
        // Keep the parsed files on the instance instead of writing the $_FILES global
        // (WRK-13); initRequestFiles() reads them from here.
        $this->rawFiles = $files;
        return $fields;
    }

    // Save file to a temporary location
    protected function saveTempFile($content)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'atom_put_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }
    
    protected function initRequestFiles()
    {
        $this->files = [];

        // Prefer files parsed from a PUT multipart body (kept on the instance) over
        // the $_FILES superglobal (WRK-13).
        $source = $this->rawFiles ?: $_FILES;
        foreach ($source as $fieldName => $fileData) {
            // Normalize multiple file uploads
            if (is_array($fileData['name'])) {
                foreach ($fileData['name'] as $index => $name) {
                    $file = new File();
                    $file->setUploadProperties(new FileUploadProperties(
                        basename($name), // strip any client-supplied path (traversal)
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
                    basename($fileData['name']), // strip any client-supplied path (traversal)
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
        if (array_key_exists($name, $this->input)) {
            return $this->input[$name];
        }
        if (array_key_exists($name, $this->route_params)) {
            return $this->route_params[$name];
        }
        if (array_key_exists($name, $this->query)) {
            return $this->query[$name];
        }
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        return null;
    }

    public function __isset($name) {
        return array_key_exists($name, $this->input)
            || array_key_exists($name, $this->route_params)
            || array_key_exists($name, $this->query)
            || array_key_exists($name, $this->attributes);
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
        $this->attributes = array_merge($this->attributes, $data);
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
        $this->body = array_diff_key($input, $this->files);
    }

    public function replaceQuery(array $query)
    {
        $this->query = $query;
    }

    public function replaceAttributes(array $input)
    {
        $this->input = $input;
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
        return array_merge($this->query, $this->input(), $this->attributes);
    }

    public function has($key)
    {
        // Existence check (a present-but-null value counts), consistent with __isset.
        return array_key_exists($key, $this->input)
            || array_key_exists($key, $this->query)
            || array_key_exists($key, $this->attributes)
            || array_key_exists($key, $this->route_params);
    }

    public function hasHeader($key)
    {
        return !empty($this->headers($key));
    }

    public function hasBody()
    {
        // Parenthesised: `>` binds tighter than `??`, so the original returned the
        // length string (or a stray bool) instead of "body larger than the min".
        return (int) $this->server('CONTENT_LENGTH', 0) > (int) env('CONTENT_LENGTH_MIN', 0);
    }

    /**
     * @return File[]
     */
    public function files()
    {
        return $this->files;
    }

    /**
     * @return File[]|File|null
     */
    public function file(string $key): Array|File|null
    {
        return $this->retrieveItem($this->files, $key);
    }

    public function hasFile(string $key)
    {
        return !is_null($this->file($key));
    }

    /**
     * @return Arrayable<Cookie>
     */
    public function cookies(): Arrayable
    {
        return $this->cookies;
    }

    /**
     * @return Cookie[]|Cookie
     */
    public function cookie($key = null, $default = null)
    {
        if ($key == null)
            return $this->cookies->toArray();
        return $this->retrieveItem($this->cookies->toArray(), $key, $default);
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
        // Match the media type regardless of parameters (e.g. "; charset=utf-8") —
        // an exact === comparison dropped the body of every such JSON request.
        return str_contains(strtolower((string) $this->headers('Content-Type')), 'application/json');
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
     * Check whether the current path matches the given pattern ("*" wildcard),
     * e.g. is('admin/*'). Was previously a no-op (no return + a never-true compare).
     */
    public function is(string $pattern): bool
    {
        $path = trim($this->url(), '/');
        $pattern = trim($pattern, '/');

        if ($pattern === $path) {
            return true;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $path);
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
            $host = $this->headers('X-Forwarded-Host');
        } else {
            $host = $this->server('HTTP_HOST');
        }

        // Host-header poisoning guard (opt-in): when a trusted-hosts allowlist is
        // configured, an unrecognised Host falls back to the configured app host so
        // it can't poison generated URLs / password-reset links / emails.
        $trusted = array_map('strtolower', config('app.trusted_hosts', []));
        if (!empty($trusted)) {
            $bare = strtolower(preg_replace('/:\d+$/', '', (string) $host));
            if (!in_array($bare, $trusted, true)) {
                $appHost = parse_url((string) config('app.url', ''), PHP_URL_HOST);
                return $appHost ?: ($trusted[0] ?? $host);
            }
        }

        return $host;
    }

    public function address()
    {
        return $this->server('REMOTE_ADDR', '');
    }

    public function clientIp()
    {
        // Only trust X-Forwarded-For when the request actually came through a
        // configured trusted proxy; otherwise it is client-spoofable. (The previous
        // regex used a literal `d` instead of `\d`, so it never matched anyway.)
        if ($this->isFromTrustedProxy()) {
            $forwarded = $this->server('HTTP_X_FORWARDED_FOR', '');
            if ($forwarded !== '') {
                // XFF is a comma list; the left-most entry is the original client.
                $ip = trim(explode(',', $forwarded)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
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
        $query = $this->query();
        $signature = $query['signature'] ?? null;
        if (empty($signature) || !is_string($signature)) {
            return false;
        }

        // Temporary signed URLs carry an `expires` timestamp.
        if (isset($query['expires']) && (int) $query['expires'] < time()) {
            return false;
        }

        unset($query['signature']);
        foreach ($ignoredParams as $ignored) {
            unset($query[$ignored]);
        }
        ksort($query);

        // Canonical = path + sorted query (minus signature/ignored). Must match the
        // signer (Support\Url::signedRoute). Keyed by app.key, compared constant-time.
        $canonical = $this->url() . (empty($query) ? '' : '?' . http_build_query($query));
        $expected = hash_hmac('sha256', $canonical, (string) config('app.key'));

        return hash_equals($expected, $signature);
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
        // Delegate to the single CSRF implementation so the session key, token
        // sources and constant-time comparison stay consistent (this previously
        // used a third session key 'csrf' and a loose, non-constant-time `!=`).
        return Csrf::csrfIsValid();
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
 