<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\Response as HttpResponse;

/**
 * @method static HttpResponse ok($data = [])
 * @method static HttpResponse noContent()
 * @method static HttpResponse created(string $message = '', $data = [])
 * @method static HttpResponse notFound(string $message, array|null $data = null)
 * @method static HttpResponse unprocessableEntity(string $message = "unprocessable request", string|array $errors = "")
 * @method static HttpResponse serverError(string $message="")
 * @method static HttpResponse badRequest(string $message = "", array $errors = [])
 * @method static HttpResponse forbidden(string $message = "", array $errors = [])
 * @method static HttpResponse unauthorized(string $message = "Unauthorized")
 */
class JsonResponse extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'json_response';
    }
}
