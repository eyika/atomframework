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
            $this->start();
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
        session_regenerate_id(true);
    }

    public function active()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
