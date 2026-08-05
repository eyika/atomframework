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
    /**
     * Which forwarded headers a trusted proxy is believed for — real bit flags, combined with `|`.
     *
     * The values match Symfony's, so knowledge carried from there (or from Laravel's TrustProxies)
     * works as expected. They previously held the `$_SERVER` key strings while keeping these
     * names, which meant the documented `A | B` usage silently produced a byte-wise-OR'd binary
     * string instead of an int.
     *
     * Trusting a proxy is not all-or-nothing: a proxy that sets `X-Forwarded-For` but never
     * `X-Forwarded-Host` should be believed for the former only, or a client can choose the host
     * your app resolves tenants and generated URLs from.
     */
    public const HEADER_X_FORWARDED_FOR   = 0b000010;
    public const HEADER_X_FORWARDED_HOST  = 0b000100;
    public const HEADER_X_FORWARDED_PROTO = 0b001000;
    public const HEADER_X_FORWARDED_PORT  = 0b010000;

    /** Every forwarded header this framework understands. */
    public const HEADER_X_FORWARDED_ALL = self::HEADER_X_FORWARDED_FOR
        | self::HEADER_X_FORWARDED_HOST
        | self::HEADER_X_FORWARDED_PROTO
        | self::HEADER_X_FORWARDED_PORT;

    /** Flag → the `$_SERVER` key it governs. */
    private const FORWARDED_HEADER_KEYS = [
        self::HEADER_X_FORWARDED_FOR   => 'HTTP_X_FORWARDED_FOR',
        self::HEADER_X_FORWARDED_HOST  => 'HTTP_X_FORWARDED_HOST',
        self::HEADER_X_FORWARDED_PROTO => 'HTTP_X_FORWARDED_PROTO',
        self::HEADER_X_FORWARDED_PORT  => 'HTTP_X_FORWARDED_PORT',
    ];

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
    // Injectable request source (WRK-01) — captured from superglobals + php://input by
    // default, so a Request never reads process globals directly after construction.
    protected array $postData = [];
    protected array $filesSource = [];
    protected ?string $rawBody = null;
    protected $server;
    protected array $headers;
    /** Bitmask of HEADER_X_FORWARDED_* a trusted proxy is believed for. Only consulted once a proxy is trusted. */
    protected int $proxyheader = self::HEADER_X_FORWARDED_ALL;
    protected $trustedProxies = [];
    protected Session $session;
    protected bool $isAssetRequest;

    public AuthenticatableInterface|User|null $auth_user;

    /**
     * @param array|null $source WRK-01 injectable request source. Keys: server, query,
     *   post, cookies, files, headers, rawBody. When null (default) each is captured
     *   from the matching PHP superglobal / php://input — so `new Request()` is
     *   unchanged, while a worker or test can pass explicit data and never touch
     *   process globals.
     */
    public function __construct(?array $source = null)
    {
        $this->auth_user = null;
        $this->cookies = new Arrayable();
        $this->isAssetRequest = false;

        $source ??= [];
        $server  = $source['server']  ?? $_SERVER;
        $query   = $source['query']   ?? $_GET;
        $post    = $source['post']    ?? $_POST;
        $cookies = $source['cookies'] ?? $_COOKIE;
        $files   = $source['files']   ?? $_FILES;
        $headers = $source['headers'] ?? getallheaders();
        // Present-but-'' means an empty injected body; absent means read php://input.
        $this->rawBody = array_key_exists('rawBody', $source) ? (string) $source['rawBody'] : null;

        $this->server = $server;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->query = $query;
        $this->postData = $post;
        $this->filesSource = $files;

        // Fetch the whitelist once, not once per cookie (PERF-07).
        $whitelistedCookies = config('cookies.whitelisted_cookies', []);
        foreach ($cookies as $name => $value) {
            if (!in_array($name, $whitelistedCookies)) {
                $this->cookies->set($name, new Cookie($name, $value));
            }
        }

        $this->setRequestBodyAndFiles();
        $this->input = [...$this->body, ...$this->files];
        $this->attributes = [];
        $this->route_params = [];
    }

    /**
     * The raw request body (php://input) — read once and cached (the stream is
     * one-shot), or the injected body when a source was provided (WRK-01).
     */
    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    protected function setRequestBodyAndFiles()
    {
        if ($this->isMethod('PUT') && strpos((string) $this->headers('Content-Type'), 'multipart/form-data') === 0) {
            $this->body = $this->parseMultipartRequest();
        } elseif ($this->isJson()) {
            // Read raw JSON input if content-type is JSON (from the injectable source).
            $jsonData = json_decode($this->rawBody(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->body = $jsonData ?? [];
            } else {
                $this->body = [];
            }
        } else {
            $this->body = $this->postData;
        }
    
        $this->initRequestFiles();
    }

    protected function parseMultipartRequest()
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? '';

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
        $rawData = $this->rawBody();
    
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
        // the injected/captured files source (WRK-13 + WRK-01).
        $source = $this->rawFiles ?: $this->filesSource;
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

    /**
     * Resolve a dynamic property.
     *
     * ATTRIBUTES ARE CHECKED FIRST, and deliberately so. They are what trusted server-side code
     * binds — middleware attaching the resolved tenant, the authenticated customer, and so on —
     * via `$request->foo = $obj`. `input` and `query` are supplied by the CLIENT.
     *
     * Attributes used to be checked LAST, which meant anything a middleware bound could be
     * shadowed by a request parameter of the same name. Two consequences, the second serious:
     *
     *  - a route with a `{business}` param shadowed a middleware-bound `$request->business`,
     *    handing back the raw URL segment where an object was expected;
     *  - on an unauthenticated route, a client could shadow bound context simply by naming it in
     *    the request body — `$request->current_customer` returned whatever the caller posted
     *    under that key. Server-set context must not be overridable by the caller.
     *
     * Route params still outrank input, since they are matched from the path rather than sent as
     * a payload. Declared properties (auth_user etc.) never reach __get at all.
     */
    public function __get($name) {
        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }
        if (array_key_exists($name, $this->route_params)) {
            return $this->route_params[$name];
        }
        if (array_key_exists($name, $this->input)) {
            return $this->input[$name];
        }
        if (array_key_exists($name, $this->query)) {
            return $this->query[$name];
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

    /**
     * Bind server-side context onto the request — the explicit form of `$request->foo = $obj`.
     *
     * Prefer this and attribute() over the magic accessors when the value MUST be the one your
     * middleware set: they read and write only the attribute bag, so no request parameter can
     * shadow them regardless of how __get's precedence evolves.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Read bound context, ignoring input, query and route params entirely.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    /** Whether $key was bound as an attribute (a bound null still counts). */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Every bound attribute.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
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
     * A cookie's VALUE, or `name => value` for all of them — the same shape `query()` and
     * `input()` return, so `$request->cookie('x') ?? $request->query('x')` has one type.
     *
     * This used to hand back the `Cookie` wrapper, which made that line string-or-object
     * depending on which branch hit. Anything defensive — `is_string()`, `===`, `json_encode()` —
     * then quietly rejected the cookie path, so the cookie appeared not to work while nothing
     * threw or logged. Reading and writing are different jobs: a `Cookie` describes a Set-Cookie
     * header (path, domain, SameSite, expiry) and none of those exist on an inbound cookie, where
     * the browser sends only `name=value`.
     *
     * Use {@see cookieObject()} for the wrapper.
     */
    public function cookie($key = null, $default = null): mixed
    {
        if ($key === null) {
            return array_map(
                fn (Cookie $cookie) => $cookie->getValue(),
                $this->cookies->toArray()
            );
        }

        $cookie = $this->retrieveItem($this->cookies->toArray(), $key);

        return $cookie instanceof Cookie ? $cookie->getValue() : $default;
    }

    /** The `Cookie` wrapper for a request cookie, or null when it isn't set. */
    public function cookieObject(string $key): ?Cookie
    {
        $cookie = $this->retrieveItem($this->cookies->toArray(), $key);

        return $cookie instanceof Cookie ? $cookie : null;
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
        if ($proto = $this->forwardedHeader(self::HEADER_X_FORWARDED_PROTO)) {
            // XFP may be a comma list when chained proxies each append; the left-most is the client's.
            return strtolower(trim(explode(',', $proto)[0]));
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
        if ($forwardedHost = $this->forwardedHeader(self::HEADER_X_FORWARDED_HOST)) {
            // Same comma-list rule as XFP.
            $host = trim(explode(',', $forwardedHost)[0]);
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

    /**
     * The port the client reached the app on.
     *
     * Behind a terminating proxy `SERVER_PORT` is the internal one (often 80), so a trusted
     * `X-Forwarded-Port` wins when present. Falls back to the port in `Host`, then `SERVER_PORT`,
     * then the scheme default.
     */
    public function port(): int
    {
        if ($forwardedPort = $this->forwardedHeader(self::HEADER_X_FORWARDED_PORT)) {
            $port = trim(explode(',', $forwardedPort)[0]);
            if (ctype_digit($port)) {
                return (int) $port;
            }
        }

        $host = (string) $this->server('HTTP_HOST', '');
        // Bracketed IPv6 literals put colons in the host, so only a trailing :digits is a port.
        if (preg_match('/:(\d+)$/', $host, $m)) {
            return (int) $m[1];
        }

        $serverPort = $this->server('SERVER_PORT', '');
        if (is_numeric($serverPort)) {
            return (int) $serverPort;
        }

        return $this->scheme() === 'https' ? 443 : 80;
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
        $forwarded = $this->forwardedHeader(self::HEADER_X_FORWARDED_FOR);
        if ($forwarded === null) {
            return $this->address();
        }

        // Walk the chain from the RIGHT, discarding hops that are themselves trusted proxies;
        // the first address that isn't one is the client. Taking the left-most entry instead is
        // only correct when every proxy OVERWRITES the header — proxies that append (the common
        // case, and what the spec describes) leave the left-most entry as whatever the original
        // caller sent, so a client could simply state its own address.
        $chain = array_map('trim', explode(',', $forwarded));
        $chain[] = (string) $this->address(); // the peer is the right-most hop, and it is real

        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $ip = $chain[$i];

            // Garbage anywhere in the chain means everything to its left is unusable. Fall back
            // to the peer rather than the left-most entry — that entry is exactly what an
            // attacker prepends, so trusting it here would reward injecting a malformed hop.
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return $this->address();
            }

            if (!$this->isTrustedAddress($ip)) {
                return $ip;
            }
        }

        // Every hop, including the peer, was a trusted proxy — so there is no untrusted address
        // to find and the left-most entry is the best claim available. This is the '*' case.
        $leftmost = $chain[0] ?? '';

        return filter_var($leftmost, FILTER_VALIDATE_IP) ? $leftmost : $this->address();
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

    /**
     * Declare which upstream addresses are proxies, and which forwarded headers to believe them for.
     *
     * `$proxies` entries may be a literal IP, a CIDR block (`10.0.0.0/8`, `2001:db8::/32`), or the
     * single-element `['*']` to trust whatever peer connects. `'*'` is a deliberate act with real
     * consequences — it lets any direct client set its own IP, host and scheme — so it is honoured
     * rather than silently ignored, but nothing enables it for you.
     *
     * `$headers` is a bitmask of HEADER_X_FORWARDED_*; `null` means all of them. Pass `0` to trust
     * a proxy's identity for nothing, which is a valid (if unusual) thing to want.
     */
    public function setTrustedProxies(array $proxies, int|null $headers = null)
    {
        $this->trustedProxies = $proxies;
        $this->proxyheader = $headers ?? self::HEADER_X_FORWARDED_ALL;
    }

    /** The forwarded-header bitmask currently in effect. */
    public function trustedHeaderSet(): int
    {
        return $this->proxyheader;
    }

    public function isFromTrustedProxy()
    {
        if (empty($this->trustedProxies)) {
            return false;
        }

        $clientIp = $this->server('REMOTE_ADDR', '');

        return $clientIp !== '' && $this->isTrustedAddress($clientIp);
    }

    /** Whether an arbitrary address (not just the peer) is one of the configured proxies. */
    protected function isTrustedAddress(string $ip): bool
    {
        foreach ($this->trustedProxies as $proxy) {
            if ($this->ipMatches($ip, (string) $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether `$ip` is covered by a trusted-proxy entry.
     *
     * This used to be a bare `in_array()`, so a CIDR entry could never match anything: an operator
     * writing `TRUSTED_PROXIES=10.0.0.0/8` got silent no-trust — safe by accident, and invisible
     * to debug because the config looked right.
     */
    protected function ipMatches(string $ip, string $entry): bool
    {
        $entry = trim($entry);

        if ($entry === '*') {
            return true;
        }

        if (!str_contains($entry, '/')) {
            return $ip === $entry;
        }

        [$subnet, $bits] = explode('/', $entry, 2);
        if (!ctype_digit($bits)) {
            return false;
        }

        $ipBin     = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        // A v4 address is never inside a v6 block (and vice versa) — differing packed lengths.
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        if ($wholeBytes > 0 && substr($ipBin, 0, $wholeBytes) !== substr($subnetBin, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($ipBin[$wholeBytes]) & $mask) === (ord($subnetBin[$wholeBytes]) & $mask);
    }

    /** Whether a trusted proxy is believed for a specific forwarded header. */
    protected function trustsForwardedHeader(int $flag): bool
    {
        return $this->isFromTrustedProxy() && ($this->proxyheader & $flag) === $flag;
    }

    /** The value of a forwarded header, but only if a trusted proxy is believed for it. */
    protected function forwardedHeader(int $flag): ?string
    {
        if (!$this->trustsForwardedHeader($flag)) {
            return null;
        }

        $value = $this->server(self::FORWARDED_HEADER_KEYS[$flag] ?? '', '');

        return $value === '' ? null : (string) $value;
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
 