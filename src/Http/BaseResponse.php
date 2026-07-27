<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Arrayable;
use Eyika\Atom\Framework\Support\Facade\Blade;
use Eyika\Atom\Framework\Support\Facade\Request as FacadeRequest;
use Eyika\Atom\Framework\Support\Facade\Session;
use Eyika\Atom\Framework\Support\View\Twig;
use JsonSerializable;

class BaseResponse
{
    public const REQUEST_VALIDATION_ERRORS_KEY = 'validation_errors';
    public const REQUEST_ERRORS_KEY = 'errors';
    public const VIEW_ERRORS_KEY = 'view_errors';
    public const REQUEST_OLD_INPUTS_KEY = 'old_inputs';

    public const STATUS_OK = 200;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_CREATED = 201;
    public const STATUS_MOVED_PERMANENTLY = 301;
    public const STATUS_FOUND = 302;
    public const STATUS_SEE_OTHER = 303;
    public const STATUS_NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_PAYMENT_REQUIRED = 402;
    public const STATUS_FORBIDDEN = 403;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_CONFLICT = 409;
    public const STATUS_UNPROCESSABLE_ENTITY = 422;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;
    public const STATUS_BAD_GATEWAY = 502;
    public const STATUS_SERVICE_NOT_AVAILABLE = 503;

    protected const METHOD_TO_FUNC = [
        self::STATUS_OK => 'ok',
        self::STATUS_NO_CONTENT => 'noContent',
        self::STATUS_CREATED => 'created',
        self::STATUS_NOT_MODIFIED => 'notModified',
        self::STATUS_BAD_REQUEST => 'badRequest',
        self::STATUS_UNAUTHORIZED => 'unauthorized',
        self::STATUS_PAYMENT_REQUIRED => 'paymentRequired',
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

    protected $_responseSent = false;

    public function __construct()
    {
        $this->cookies = new Arrayable();
    }

    // Method to set a cookie header. $expiry is an ABSOLUTE unix timestamp (e.g.
    // time()+86400); it is converted to both a Max-Age duration and an Expires date.
    // (Previously path/domain were passed to Cookie in swapped positions.)
    public function setCookie($name, $value = '', $expiry = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true, $sameSite = 'Lax')
    {
        $expires = $expiry > 0 ? (new \DateTime())->setTimestamp($expiry) : null;
        $maxAge  = $expiry > 0 ? max(0, $expiry - time()) : null;

        $this->cookies->set($name, new Cookie(
            $name,
            $value,
            $maxAge,
            $domain !== '' ? $domain : null,
            $path,
            $secure,
            $httpOnly,
            $expires,
            $sameSite
        ));
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

    public function terminate()
    {
        $this->_responseSent = true;
        return $this;
    }
    
    public function sendDeferred(): void
    {
        $this->_send(false);
    }

    /**
     * Method to send the response headers and content
     * 
     * @return bool
     */
    public function send(): bool
    {
        if (!$this->_responseSent) {
            return $this->_send();
        }
        return true;
    }

    protected function _send($terminate = true)
    {
        // Mark the response sent so a second send() is a no-op (prevents
        // double-emitting headers/body); previously only terminate() did this.
        if ($terminate) {
            $this->_responseSent = true;
        }

        // File download and redirects take precedence over content-negotiation —
        // otherwise an API/XHR request (isNotHtml) swallowed them by echoing body.
        if ($this->isFileResponse) {
            $this->emitStatus($this->statusCode);
            $this->sendHeaders();
            $this->emitFile($this->file_path);
            return $terminate;
        }

        if ($this->isRedirect) {
            if (count($this->errors))
                Session::set(self::REQUEST_ERRORS_KEY, $this->errors);
            if (count($this->validationErrors))
                Session::set(self::REQUEST_VALIDATION_ERRORS_KEY, $this->validationErrors);
            if (count($this->inputs))
                Session::set(self::REQUEST_OLD_INPUTS_KEY, $this->inputs);

            $this->emitStatus($this->statusCode);
            $this->sendHeaders();
            return $terminate;
        }

        // JSON / XHR: emit the body as-is (no view compilation).
        if (FacadeRequest::isNotHtml()) {
            $this->emitStatus($this->statusCode);
            $this->sendHeaders();
            $this->emitBody($this->body);
            return $terminate;
        }

        if ($this->shouldCompileView) {
            $this->compileView();
        }

        $this->emitStatus($this->statusCode);
        $this->sendHeaders();
        $this->emitBody($this->body);
        return $terminate;
    }

    public function getInstance()
    {
        return $this;
    }

    public function responseSent(): bool
    {
        return $this->_responseSent;
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

    protected function sendHeaders()
    {
        $this->cookies->each(function (Cookie $cookie) {
            // Emit a real Set-Cookie header (with all attributes/flags) rather than a
            // plain "name: value" header. `false` = don't replace, so multiple cookies
            // each get their own Set-Cookie line.
            $this->emitHeader('Set-Cookie: ' . $cookie->toString(), false);
        });
        foreach ($this->headers as $header) {
            foreach ($header as $key => $value) {
                $val = str_contains($key, 'Set-Cookie') ? (string) $value[0] : $value[0];
                $this->emitHeader("{$key}: {$val}", (bool) $value[1], $value[2] ?? null);
            }
        }
    }

    // -----------------------------------------------------------------------------
    // Worker-safety (WRK-02): route ALL response output through the object so a
    // persistent worker can CAPTURE the status/headers/body and send them itself,
    // instead of the framework emitting them directly. Under FPM (capture off) these
    // fall through to the native http_response_code()/header()/echo/readfile().
    // -----------------------------------------------------------------------------

    protected static bool $captureOutput = false;
    protected ?int $sentStatus = null;
    protected array $sentHeaders = [];
    protected string $sentBody = '';

    /** Turn response capture on/off (a worker enables it per request). */
    public static function captureOutput(bool $capture = true): void
    {
        self::$captureOutput = $capture;
    }

    protected function emitStatus(int $code): void
    {
        $this->sentStatus = $code;
        if (!self::$captureOutput) {
            http_response_code($code);
        }
    }

    protected function emitHeader(string $header, bool $replace = true, ?int $code = null): void
    {
        $this->sentHeaders[] = $header;
        if (!self::$captureOutput) {
            $code !== null ? header($header, $replace, $code) : header($header, $replace);
        }
    }

    protected function emitBody(string $body): void
    {
        $this->sentBody .= $body;
        if (!self::$captureOutput) {
            echo $body;
        }
    }

    protected function emitFile(string $path): void
    {
        if (self::$captureOutput) {
            $this->sentBody .= (file_get_contents($path) ?: '');
            return;
        }
        ob_clean();
        flush();
        readfile($path);
    }

    /** The captured status code (worker mode). */
    public function sentStatus(): ?int
    {
        return $this->sentStatus;
    }

    /** The captured header lines (worker mode). @return string[] */
    public function sentHeaders(): array
    {
        return $this->sentHeaders;
    }

    /** The captured body (worker mode). */
    public function sentBody(): string
    {
        return $this->sentBody;
    }

    protected function create(mixed $data = null, int $statusCode = 200): self
    {
        $data = $this->convertObjectsToArray($data);

        $jsonData = json_encode($data);
        $this->body($jsonData)->status($statusCode)
                        ->setHeader('Content-Type', 'application/json; charset=utf-8');
        
        return $this;
    }

    private function compileView()
    {
        try {
            if (config('view.use_advance_engine')) {
                $view = Blade::instance();
                $this->errors = array_merge($this->errors, $view->atomErrors());
                if (count($this->errors))
                    Session::set(self::REQUEST_ERRORS_KEY, $this->errors);

                $content = $view->run($this->viewFileName, $this->viewData);
            } else {
                $path = resource_path('views');
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

    private function convertObjectsToArray(mixed $data, array &$seen = [], ?callable $customHandler = null): array
    {
        $data = Arr::wrap($data);

        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $objectId = spl_object_hash($value);

                // Guard against back-references (e.g. a model relation pointing at
                // its parent) so serialization can't recurse into infinite loop/OOM.
                if (isset($seen[$objectId])) {
                    $data[$key] = 'Circular Reference Detected';
                    continue;
                }

                $seen[$objectId] = true;
    
                if ($customHandler) {
                    $data[$key] = $customHandler($value);
                } elseif (method_exists($value, 'toArray')) {
                    $data[$key] = $value->toArray();
                } elseif (method_exists($value, '__toArray')) {
                    $data[$key] = $value->__toArray();
                } elseif ($value instanceof JsonSerializable) {
                    $data[$key] = $value->jsonSerialize();
                } else {
                    $data[$key] = (array) $value; // Fallback to casting
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->convertObjectsToArray($value, $seen, $customHandler);
            }
        }
    
        return $data;
    }    
}
