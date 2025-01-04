<?php

namespace Eyika\Atom\Framework\Support;

use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\Language;

final class StrHelpers
{
    public static function english()
    {
        return InflectorFactory::createForLanguage(Language::ENGLISH)->build();
    }

    public static function french()
    {
        return InflectorFactory::createForLanguage(Language::FRENCH)->build();
    }

    public static function norwegian_bokmal()
    {
        return InflectorFactory::createForLanguage(Language::NORWEGIAN_BOKMAL)->build();
    }

    public static function PORTUGUESE()
    {
        return InflectorFactory::createForLanguage(Language::PORTUGUESE)->build();
    }

    public static function spanish()
    {
        return InflectorFactory::createForLanguage(Language::SPANISH)->build();
    }

    public static function turkish()
    {
        return InflectorFactory::createForLanguage(Language::TURKISH)->build();
    }
}