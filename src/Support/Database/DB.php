<?php

namespace Eyika\Atom\Support\Database;

class DB
{
    public static bool $transaction_mode;

    public function __construct()
    {
        static::$transaction_mode = false;
    }

    public static function init()
    {
        return new static();
    }

    public static function beginTransaction()
    {
        mysqly::beginTransaction();
        $_SESSION['transaction_mode'] = true;
        self::$transaction_mode = true;
    }

    public static function commit()
    {
        mysqly::commit();
        $_SESSION['transaction_mode'] = false;
        self::$transaction_mode = false;
    }

    public static function rollback()
    {
        mysqly::rollback();
        $_SESSION['transaction_mode'] = false;
        self::$transaction_mode = false;
    }
}