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

    public function html(string $message, int $statusCode = self::STATUS_OK): self
    {
        return $this->_plain($message, $statusCode, 'text/html');
    }

    public function json(array $data, int $statusCode = self::STATUS_OK): self
    {
        if (!isset(self::METHOD_TO_FUNC[$statusCode])) {
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
        $this->isRedirect = true;

        if ($delay) {
            return self::status($code)->setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return self::setHeader('Location', $to, $code);
    }

    public function back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
    {
        $to = null;

        // Redirect to the previous page
        if (!empty(FacadeRequest::headers('HTTP_REFERER'))) {
            $to = filter_var(FacadeRequest::headers('HTTP_REFERER'), FILTER_VALIDATE_URL);
        }
        // Fallback if the referrer is not valid
        if (!$to) {
            $to = Route::previous();
        }
        $this->isRedirect = true;

        if ($delay) {
            return $this->setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return $this->setHeader('Location', $to, $code);
    }

    public function download(string $file_path, string|null $file_name = null): self
    {
        $status = self::STATUS_OK;

        if (!file_exists($file_path)) {
            $status = self::STATUS_NOT_FOUND;
            return $this->body('File not found.')->setHeader('Content-Type', 'text/plan', $status);
        }

        $file_name = $file_name ?? basename($file_path);

        return $this->status($status)->setIsFileResponse($file_path)
            ->setHeader('Content-Description', 'File Transfer')
            ->setHeader('Content-Type', 'application/octet-stream', $status)
            ->setHeader('Content-Disposition', 'attachment; filename=' . $file_name)
            ->setHeader('Content-Transfer-Encoding', 'binary')
            ->setHeader('Expires', '0')
            ->setHeader('Cache-Control', 'must-revalidate')
            ->setHeader('Pragma', 'public')
            ->setHeader('Content-Length', filesize($file_path));
    }

    public function setCsrf(): void
    {
        Csrf::setCsrfToken();
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
