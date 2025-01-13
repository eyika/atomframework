<?php

namespace Eyika\Atom\Framework\Http;

use Exception;

class JsonResponse extends BaseResponse
{
    public static function create(mixed $data = null, int $statusCode = 200): self
    {
        if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], 'toArray')) {
            $data['data'] = $data['data']->toArray(includeDynamicProperties: true);
        } else if (is_array($data) && isset($data['data']) && is_object($data['data']) && method_exists($data['data'], '__toArray')) {
            $data['data'] = $data['data']->__toArray();
        }


        if (! self::$instantiated)
            new static;

        $jsonData = json_encode($data);
        return self::body($jsonData)->status($statusCode)
                        ->setHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public static function ok($data = []): self
    {
        return self::create($data, self::STATUS_OK);
    }

    public static function noContent(): self
    {
        return self::create(statusCode: self::STATUS_CREATED);
    }

    public static function created(string $message = '', $data = []): self
    {
        return self::create($data, self::STATUS_CREATED);
    }

    public static function notFound(string $message, array|null $data = null): self
    {
        return self::create(['message' => $message, 'errors' => $data], self::STATUS_NOT_FOUND);
    }

    public static function unprocessableEntity(string $message = "unprocessable request", string|array $errors = ""): self
    {
        return self::create(['message' => $message, 'errors' => $errors], self::STATUS_UNPROCESSABLE_ENTITY);
    }

    public static function serverError(string $message=""): self
    {
        return self::create(['message' => $message], self::STATUS_INTERNAL_SERVER_ERROR);
    }

    public static function badRequest(string $message = "", array $errors = []): self
    {
        return self::create(['message' => $message, 'errors' => $errors], self::STATUS_BAD_REQUEST);
    }

    public static function unauthorized(string $message = "Unauthorized"): self
    {
        return self::create(['message' => $message], self::STATUS_UNAUTHORIZED);
    }

    // private function respond(int $statusCode, $body = null)
    // {
    //     try {
    //         return self::json($body)->withStatus($statusCode);
    //     } catch (Exception $ex) {
    //     }

    // }
}
