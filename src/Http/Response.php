<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\Facade\Request as FacadeRequest;

class Response extends BaseResponse
{
    public function plain(string $message, int $statusCode = self::STATUS_OK): self
    {
        return $this->_plain($message, $statusCode);
    }

    public function image(string $data, int $statusCode = self::STATUS_OK, string $type = "jpeg"): self
    {
        return $this->_plain($data, $statusCode, "image/$type");
    }

    public function html(string $message, int $statusCode = self::STATUS_OK): self
    {
        return $this->_plain($message, $statusCode, 'text/html');
    }

    public function custom(string $message, int $statusCode = self::STATUS_OK, $mime = 'text/plain'): self
    {
        return $this->_plain($message, $statusCode, $mime);
    }

    public function json(array $data, int $statusCode = self::STATUS_OK): self
    {
        // Validated as an HTTP status code, NOT against METHOD_TO_FUNC. That map lists the codes
        // with a named helper, which is a much smaller set — so `json($data, 409)` threw
        // "Invalid HTTP status code" despite 409 being a framework constant WITH a helper, as did
        // every redirect code and 502/503. Whether a convenience method happens to exist says
        // nothing about whether a status is legitimate.
        if ($statusCode < 100 || $statusCode > 599) {
            throw new Exception("Invalid HTTP status code: $statusCode");
        }

        return $this->create($data, $statusCode);
    }

    public function view(string $file_name, array $data = []): self
    {
        $this->shouldCompileView = true;
        $this->viewFileName = $file_name;
        $this->viewData = $data;

        return $this;
    }

    public function redirect(string $to, int $code = self::STATUS_FOUND, int|null $delay = null): self
    {
        // Strip CR/LF to prevent response-header injection via the redirect target.
        $to = str_replace(["\r", "\n"], '', $to);
        $this->isRedirect = true;

        if ($delay) {
            return self::status($code)->setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return self::setHeader('Location', $to, $code);
    }

    public function back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
    {
        $to = null;

        // Only honour the Referer when it is same-origin — an attacker-set Referer
        // must never become an open redirect off-site.
        $referer = FacadeRequest::headers('HTTP_REFERER') ?: FacadeRequest::headers('Referer');
        if (!empty($referer) && filter_var($referer, FILTER_VALIDATE_URL)) {
            $refHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
            $appHost = strtolower((string) FacadeRequest::host());
            if ($refHost !== '' && $refHost === $appHost) {
                $to = $referer;
            }
        }
        // Fallback if the referrer is missing/cross-origin/invalid.
        if (!$to) {
            $to = Route::previous() ?: '/';
        }
        $to = str_replace(["\r", "\n"], '', (string) $to);
        $this->isRedirect = true;

        if ($delay) {
            return $this->setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return $this->setHeader('Location', $to, $code);
    }

    public function download(string $file_path, string|null $file_name = null): self
    {
        $status = self::STATUS_OK;

        // Resolve the real path (collapses ../) and, when a download base dir is
        // configured, confine to it — prevents traversal to arbitrary files when
        // the path is influenced by user input.
        $real = realpath($file_path);
        $base = config('filesystem.download_base');
        $confined = $base ? (is_string($real) && str_starts_with($real, realpath($base) . DIRECTORY_SEPARATOR)) : true;

        if ($real === false || !is_file($real) || !$confined) {
            $status = self::STATUS_NOT_FOUND;
            return $this->body('File not found.')->setHeader('Content-Type', 'text/plain', $status);
        }

        // Sanitise the download filename (basename + strip CR/LF and quotes) to
        // avoid header injection / path leakage in Content-Disposition.
        $file_name = str_replace(["\r", "\n", '"'], '', basename($file_name ?? basename($real)));

        return $this->status($status)->setIsFileResponse($real)
            ->setHeader('Content-Description', 'File Transfer')
            ->setHeader('Content-Type', 'application/octet-stream', $status)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file_name . '"')
            ->setHeader('Content-Transfer-Encoding', 'binary')
            ->setHeader('Expires', '0')
            ->setHeader('Cache-Control', 'must-revalidate')
            ->setHeader('Pragma', 'public')
            ->setHeader('Content-Length', filesize($file_path));
    }

    public function proxy(Request $request, ?string $target = null, array $extraHeaders = [])
    {
        if ($target) {
            return (new Proxy($request, $target, $extraHeaders))->send();
        }

        return new Proxy($request, extraHeaders: $extraHeaders);
    }

    public function setCsrf(): void
    {
        Csrf::setCsrfToken();
    }

    // Add errors to the response
    public function withErrors(array $errors)
    {
        $this->errors = array_merge($this->errors, $errors);
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
        return $this;
    }

    private function _plain(string $message, int $statusCode = self::STATUS_OK, $mime = 'text/plain'): self
    {
        $this->shouldCompileView = false;

        return $this->status($statusCode)->body($message)
            ->setHeader('Content-Type', "$mime; charset=utf-8")
            ->status($statusCode);
    }
}
