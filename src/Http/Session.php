<?php
namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Session\MysqlSessionHandler;

class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_save_handler(new MysqlSessionHandler, true);
        }
    }

    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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

    public function set(string $key, mixed $value)
    {
        if (gettype($value) !== 'string') {
            $value = serialize($value);
        }
        $_SESSION[$key] = $value;
    }

    public function get(string $key, $default = null)
    {
        if ($this->has($key)) {
            return $_SESSION[$key];
        }
        return $default;
    }

    public function unset(string $key)
    {
        if ($this->has($key)) {
            unset($_SESSION[$key]);
        }
    }
}