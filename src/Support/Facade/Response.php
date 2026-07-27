<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Response as HttpResponse;
use Eyika\Atom\Framework\Http\Proxy;

/**
 * @method static HttpResponse getInstance()
 * @method static BaseResponse terminate()
 * @method static HttpResponse plain(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse image(string $data, int $statusCode = self::STATUS_OK, string $type = "jpeg")
 * @method static HttpResponse html(string $message, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse custom(string $message, int $statusCode = self::STATUS_OK, $mime = 'text/plain')
 * @method static HttpResponse json(array $data, int $statusCode = self::STATUS_OK)
 * @method static HttpResponse view(string $file_name, array $data = [])
 * @method static HttpResponse redirect(string $to, int $code = self::STATUS_FOUND, int|null $delay = null)
 * @method static HttpResponse back(int $code = self::STATUS_SEE_OTHER, int|null $delay = null)
 * @method static HttpResponse download(string $file_path, string|null $file_name = null)
 * @method static HttpResponse|Proxy proxy(Request $request, ?string $target = null, array $extraHeaders = [])
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
