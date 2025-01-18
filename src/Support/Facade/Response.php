<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\Response as HttpResponse;

/**
 * @method static HttpResponse getInstance()
 * @method static HttpResponse plain(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse html(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse json(array $data, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse view(string $file_name, array $data = [])
 * @method static HttpResponse redirect(string $to, int $code = self::STATUS_FOUND, int|null $delay = null)
 * @method static HttpResponse back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
 * @method static HttpResponse download(string $file_path, string|null $file_name = null)
 * @method static HttpResponse setCsrf()
 * @method static HttpResponse setCookie($name, $value = '', $expiry = 0, $path = '/', $domain = '', $secure = false, $httpOnly = true) Method to set a cookie header
 * @method static HttpResponse setHeader(string $key, string $content, int|null $code = null, bool $replace = true) Method to set a header
 */
class Response extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
