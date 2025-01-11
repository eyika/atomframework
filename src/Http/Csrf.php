<?php

namespace Eyika\Atom\Framework\Http;

use Eyika\Atom\Framework\Support\Facade\Session;

class Csrf
{
    public static function getCsrf()
    {
        $csrf = null;

        if (!Session::has('csrf_token')) {
            $csrf = bin2hex(random_bytes(50));
            Session::set('csrf_token', );
        }
        return $csrf ?? Session::get('csrf_token');
    }

    public static function setCsrf()
    {
        echo '<input type="hidden" name="csrf_token" value="' . static::getCsrf() . '">';
    }
}
