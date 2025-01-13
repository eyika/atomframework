<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\Facade\Request as FacadeRequest;

class Response extends BaseResponse
{
    public static function plain(string $message, int $statusCode = self::STATUS_OK): self
    {
        return static::_plain($message, $statusCode);
    }

    public static function html(string $message, int $statusCode = self::STATUS_OK): self
    {
        return static::_plain($message, $statusCode, 'text/html');
    }

    public static function json(array|string $message, array|int $dataOrStatus = self::STATUS_OK, int|null $statusCode = null): self
    {
        if ((! self::$instantiated))
            new static;

        $data = is_array($dataOrStatus) ? $dataOrStatus : null;
        $statusCode = $statusCode ?? (is_int($dataOrStatus) ? $dataOrStatus : self::STATUS_OK);

        if (!isset(self::METHOD_TO_FUNC[$statusCode])) {
            throw new Exception("Invalid HTTP status code: $statusCode");
        }

        $responseBody = $data ? ['message' => $message, 'data' => $data] : ['message' => $message];

        return static::status($statusCode)->body(json_encode($responseBody))
            ->setHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function view(string $file_name, array $data = []): self
    {
        if ((! self::$instantiated))
            new static;

        static::$shouldCompileView = true;
        static::$viewFileName = $file_name;
        static::$viewData = $data;

        return new static;
    }

    public static function redirect(string $to, int $code = self::STATUS_FOUND, int|null $delay = null): self
    {
        if ((! self::$instantiated))
            (new static)->status($code);

        self::$isRedirect = true;

        if ($delay) {
            return self::status($code)->setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return self::setHeader('Location', $to, $code);
    }

    public static function back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
    {
        if ((! self::$instantiated))
            (new static)->status($code);

        $to = null;

        // Redirect to the previous page
        if (!empty(FacadeRequest::header('HTTP_REFERER'))) {
            $to = filter_var(FacadeRequest::header('HTTP_REFERER'), FILTER_VALIDATE_URL);
        }
        // Fallback if the referrer is not valid
        if (!$to) {
            $to = Route::previous();
        }
        self::$isRedirect = true;

        if ($delay) {
            return self::setHeader('Refresh', "$delay; URL={$to}", $code);
        }

        return self::setHeader('Location', $to, $code);
    }

    public static function download(string $file_path, string|null $file_name = null): self
    {
        $status = self::STATUS_OK;

        if (!file_exists($file_path)) {
            $status = self::STATUS_NOT_FOUND;
            return self::body('File not found.')->setHeader('Content-Type', 'text/plan', $status);
        }

        if ((! self::$instantiated))
            (new static)->status($status);

        $file_name = $file_name ?? basename($file_path);

        return self::setIsFileResponse($file_path)
            ->setHeader('Content-Description', 'File Transfer')
            ->setHeader('Content-Type', 'application/octet-stream', $status)
            ->setHeader('Content-Disposition', 'attachment; filename=' . $file_name)
            ->setHeader('Content-Transfer-Encoding', 'binary')
            ->setHeader('Expires', '0')
            ->setHeader('Cache-Control', 'must-revalidate')
            ->setHeader('Pragma', 'public')
            ->setHeader('Content-Length', filesize($file_path));
    }

    public static function setCsrf(): void
    {
        Csrf::setCsrf();
    }

    private static function _plain(string $message, int $statusCode = self::STATUS_OK, $mime = 'text/plain'): self
    {
        if ((! self::$instantiated))
            new static;

        static::$shouldCompileView = false;

        return static::status($statusCode)->body($message)
            ->setHeader('Content-Type', "$mime; charset=utf-8")
            ->status($statusCode);
    }
}
