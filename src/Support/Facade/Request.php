<?php

namespace Eyika\Atom\Framework\Support\Facade;

/**
 * @property string HEADER_X_FORWARDED_FOR
 * @property string HEADER_X_FORWARDED_HOST
 * @property string HEADER_X_FORWARDED_PORT
 * @property string HEADER_X_FORWARDED_PROTO
 * 
 * @method static void __set($name, $value)
 * @method static void __get($name)
 * @method static mixed query($key = null, $default = null)
 * @method static mixed input($key = null, $default = null)
 * @method static void replaceInput(array $input)
 * @method static void replaceQuery(array $query)
 * @method static array all()
 * @method static bool has($key)
 * @method static bool hasBody()
 * @method static mixed file($key = null)
 * @method static mixed cookie($key = null, $default = null)
 * @method static mixed header($key = null, $default = null)
 * @method static mixed server($key = null, $default = null)
 * @method static string method()
 * @method static string documentRoot()
 * @method static bool isMethod($method)
 * @method static bool isJson()
 * @method static bool wantsJson()
 * @method static bool expectsJson()
 * @method static bool isXmlHttpRequest()
 * @method static string getPathInfo()
 * @method static string getOriginPathInfo()
 * @method static string getRequestUri()
 * @method static bool hasSession()
 * @method static void setSession(Session $session)
 * @method static array getSession()
 * @method static bool is(string $regex)
 * @method static string url()
 * @method static string getScheme()
 * @method static string getHost()
 * @method static string getSchemeAndHttpHost()
 * @method static void setTrustedProxies(array $proxies, int $headers = null)
 * @method static bool isFromTrustedProxy()
 * @method static mixed retrieveItem($source, $key = null, $default = null)
 * @method static void setItem($source, string $key, string|array $value)
 */
class Request extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'request';
    }
}
