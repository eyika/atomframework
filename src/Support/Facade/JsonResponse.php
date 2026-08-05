<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\JsonResponse as HttpJsonResponse;

/**
 * @method static HttpJsonResponse getInstance()
 * @method static int getStatusCode()
 * @method static BaseResponse terminate()
 * @method static HttpJsonResponse ok(string $message, mixed $data = [])
 * @method static HttpJsonResponse noContent()
 * @method static HttpJsonResponse created(string $message = '', mixed $data = [])
 * @method static HttpJsonResponse notFound(string $message, mixed $data = null)
 * @method static HttpJsonResponse conflict(string $message = "", array $errors = [])
 * @method static HttpJsonResponse unprocessableEntity(string $message = "unprocessable request", string $errors = "")
 * @method static HttpJsonResponse tooManyRequests(string $message = "Too many requests", int|null $retryAfter = null, array $errors = [])
 * @method static HttpJsonResponse serverError(string $message="")
 * @method static HttpJsonResponse badGateway(string $message = "")
 * @method static HttpJsonResponse serviceUnavailable(string $message = "Service unavailable", int|null $retryAfter = null, array $errors = [])
 * @method static HttpJsonResponse badRequest(string $message = "", array $errors = [])
 * @method static HttpJsonResponse paymentRequired(string $message = "", array $errors = [])
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
