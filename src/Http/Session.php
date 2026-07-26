<?php
namespace Eyika\Atom\Framework\Http;

use Closure;
use Eyika\Atom\Framework\Support\Session\FileSessionHandler;
use Eyika\Atom\Framework\Support\Session\MysqlSessionHandler;
use Eyika\Atom\Framework\Support\Session\RedisSessionHandler;
use InvalidArgumentException;
use Serializable;

class Session
{
    protected const SERIALIZED_PREFIX = 'sess.serialized:-';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            switch (config('session.driver')) {
                case 'file':
                    session_set_save_handler(new FileSessionHandler, true);
                    break;
                case 'redis':
                    session_set_save_handler(new RedisSessionHandler, true);
                    break;
                case 'database':
                    session_set_save_handler(new MysqlSessionHandler, true);
                    break;
                default:
                    session_set_save_handler(new MysqlSessionHandler, true);
                    break;
            }
        }
    }

    public function save()
    {
        session_write_close();
    }

    public function has(string $key)
    {
        return array_key_exists($key, $_SESSION);
    }

    public function set(string $key, mixed $value): void
    {
        if (is_resource($value)) {
            throw new InvalidArgumentException("Cannot serialize resource types.");
        }
    
        if ($value instanceof Closure) {
            throw new InvalidArgumentException("Cannot serialize closures. Use a callable reference instead.");
        }
    
        // Serialize if value is neither string nor scalar
        if (!is_string($value) && !is_scalar($value) && !is_object($value)) {
            $value = self::SERIALIZED_PREFIX . serialize($value);
        } else if ((is_object($value) && method_exists($value, '__serialize')) || $value instanceof Serializable) {
            $value = serialize($value);
        }
    
        $_SESSION[$key] = $value;
    }    

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            $value = $_SESSION[$key];
    
            // Check if the value is serialized
            if (is_string($value) && str_starts_with($value, self::SERIALIZED_PREFIX)) {
                return unserialize(substr($value, strlen(self::SERIALIZED_PREFIX)));
            }
    
            return $value;
        }
    
        return $default;
    }

    public function unset(string $key)
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }   

    public function forget(string $key)
    {
        $this->unset($key);
    }

    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session-cookie params explicitly rather than inheriting php.ini.
            if (!headers_sent()) {
                $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                    || (($_SERVER['SERVER_PORT'] ?? null) == 443);
                session_set_cookie_params([
                    'lifetime' => (int) config('session.lifetime', 0),
                    'path'     => '/',
                    'domain'   => config('session.domain', ''),
                    'secure'   => $secure,
                    'httponly' => true,
                    'samesite' => config('session.same_site', 'Lax'),
                ]);
            }
            session_start();
        }
    }

    public function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function regenerate()
    {
        // Only meaningful for an active session; guard so callers (e.g. login) can
        // invoke it unconditionally without warnings when no session is running.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_regenerate_id(true);
        }
        return false;
    }

    public function active()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
