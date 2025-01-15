<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\Response as HttpResponse;

/**
 * @method static HttpResponse plain(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse html(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse json(array|string $message, array|int $dataOrStatus = self::STATUS_OK, int|null $statusCode = null)
 * @method static HttpResponse view(string $file_name, array $data = [])
 * @method static HttpResponse redirect(string $to, int $code = self::STATUS_FOUND, int|null $delay = null)
 * @method static HttpResponse back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
 * @method static HttpResponse download(string $file_path, string|null $file_name = null)
 * @method static HttpResponse setCsrf()
 */
class Response extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
