<?php

namespace Eyika\Atom\Framework\Support;

use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

class StrHelpers extends InflectorFactory
{
    public static function english()
    {
        return static::createForLanguage(Language::ENGLISH)->build();
    }

    public static function french()
    {
        return static::createForLanguage(Language::FRENCH)->build();
    }

    public static function norwegian_bokmal()
    {
        return static::createForLanguage(Language::NORWEGIAN_BOKMAL)->build();
    }

    public static function PORTUGUESE()
    {
        return static::createForLanguage(Language::PORTUGUESE)->build();
    }

    public static function spanish()
    {
        return static::createForLanguage(Language::SPANISH)->build();
    }

    public static function turkish()
    {
        return static::createForLanguage(Language::TURKISH)->build();
    }
}