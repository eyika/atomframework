<?php

namespace Eyika\Atom\Framework\Http;

use Exception;
use Eyika\Atom\Framework\Support\Database\Model;

class JsonResponse
{
    public const STATUS_OK = 200;
    public const STATUS_NO_CONTENT = 204;
    public const STATUS_CREATED = 201;
    public const NOT_MODIFIED = 304;
    public const STATUS_BAD_REQUEST = 400;
    public const STATUS_NOT_FOUND = 404;
    public const STATUS_UNAUTHORIZED = 401;
    public const STATUS_UNPROCESSABLE_ENTITY = 422;
    public const STATUS_INTERNAL_SERVER_ERROR = 500;

    public function __construct(int $status_code, $data = null)
    {
        if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], 'toArray')) {
            $data['data'] = $data['data']->toArray();
        } else if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], '__toArray')) {
            $data['data'] = $data['data']->__toArray();
        }

        $body = $data ? json_encode($data) : null;
        http_response_code($status_code);
        header("Content-type: application/json");
        echo $body;
    }

    public static function ok($message = "", $data = null): bool
    {
        try {
            new self(self::STATUS_OK, ['message' => $message, 'data' => $data]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function noContent(): bool
    {
        try {
            new self(self::STATUS_NO_CONTENT);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function created(string $message = '', $data = []): bool
    {
        try {
            new self(self::STATUS_CREATED, ['message' => $message, 'data' => $data]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function badRequest(string $message="", string|array $error = ""): bool
    {
        try {
            new self(self::STATUS_BAD_REQUEST, ['message' => $message, 'error' => $error]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function notFound(string $error, array|null $data = null): bool
    {
        try {
            new self(self::STATUS_NOT_FOUND, ['message' => $error, 'error' => $data]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function unauthorized(string $message = "unauthorized request"): bool
    {
        try {
            new self(self::STATUS_UNAUTHORIZED, ['message' => $message]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function unprocessableEntity(string $message = "unprocessable request", string|array $error = ""): bool
    {
        try {
            new self(self::STATUS_UNPROCESSABLE_ENTITY, ['message' => $message, 'error' => $error]);
            return true;
        } catch (Exception $ex) {
        }
    }

    public static function serverError(string $message=""): bool
    {
        try {
            new self(self::STATUS_INTERNAL_SERVER_ERROR, ['message' => $message]);
            return true;
        } catch (Exception $ex) {
        }
    }

    // private function respond(int $statusCode, $body = null)
    // {
    //     try {
    //         return self::json($body)->withStatus($statusCode);
    //     } catch (Exception $ex) {
    //     }

    // }
}