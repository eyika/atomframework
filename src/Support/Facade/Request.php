<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Http\Session;
use Eyika\Atom\Framework\Support\Arrayable;

/**
 * @property string HEADER_X_FORWARDED_FOR
 * @property string HEADER_X_FORWARDED_HOST
 * @property string HEADER_X_FORWARDED_PORT
 * @property string HEADER_X_FORWARDED_PROTO
 * 
 * @method static void __set($name, $value)
 * @method static void __get($name)
 * @method static bool isAssetRequest(bool|null $value = null)
 * @method static capture()
 * @method static mixed query($key = null, $default = null)
 * @method static mixed input($key = null, $default = null)
 * @method static mixed merge(array $data)
 * @method static mixed only(array $keys)
 * @method static mixed except(array $keys)
 * @method static void replaceInput(array $input)
 * @method static void replaceQuery(array $query)
 * @method static void replace(string $bodyOrQuery, array $data)
 * @method static array all()
 * @method static bool has($key)
 * @method static bool hasHeader()
 * @method static bool hasBody()
 * @method static File[] files()
 * @method static File file(string $key)
 * @method static bool hasFile(string $key)
 * @method static Arrayable cookies()
 * @method static mixed cookie($key = null, $default = null)
 * @method static mixed headers($key = null, $default = null)
 * @method static mixed server($key = null, $default = null)
 * @method static string method()
 * @method static string documentRoot()
 * @method static bool isMethod($method)
 * @method static bool isJson()
 * @method static bool isOptions()
 * @method static bool wantsJson()
 * @method static bool expectsJson()
 * @method static bool isXmlHttpRequest()
 * @method static bool isHtml()
 * @method static bool isNotHtml()
 * @method static string pathInfo()
 * @method static string originPathInfo()
 * @method static string requestUri()
 * @method static bool hasSession()
 * @method static void setSession(Session $session)
 * @method static Session getSession()
 * @method static bool is(string $regex)
 * @method static string url()
 * @method static string uri()
 * @method static string scheme()
 * @method static string host()
 * @method static string address()
 * @method static string clientIp()
 * @method static string schemeAndHttpHost()
 * @method static void setTrustedProxies(array $proxies, int|null $headers = null)
 * @method static bool isFromTrustedProxy()
 * @method static bool hasValidSignature()
 * @method static bool hasValidSignatureWhileIgnoring()
 * @method static bool validateSignature()
 * @method static bool|array validate(array $params, string $separator = '|')
 * @method static array validationErrors()
 * @method static mixed retrieveItem($source, $key = null, $default = null)
 * @method static void setItem($source, string $key, string|array $value)
 * @method static bool validateCsrf()
 */
class Request extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'request';
    }
}
