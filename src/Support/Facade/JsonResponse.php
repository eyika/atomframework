<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\JsonResponse as HttpJsonResponse;

/**
 * @method static HttpJsonResponse getInstance()
 * @method static HttpJsonResponse ok(string $message, $data = [])
 * @method static HttpJsonResponse noContent()
 * @method static HttpJsonResponse created(string $message = '', $data = [])
 * @method static HttpJsonResponse notFound(string $message, array|null $data = null)
 * @method static HttpJsonResponse unprocessableEntity(string $message = "unprocessable request", string|array $errors = "")
 * @method static HttpJsonResponse serverError(string $message="")
 * @method static HttpJsonResponse badRequest(string $message = "", array $errors = [])
 * @method static HttpJsonResponse forbidden(string $message = "", array $errors = [])
 * @method static HttpJsonResponse unauthorized(string $message = "Unauthorized")
 */
class JsonResponse extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'json_response';
    }
}
