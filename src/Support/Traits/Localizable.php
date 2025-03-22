<?php

namespace Eyika\Atom\Framework\Support\Traits;

use Eyika\Atom\Framework\Support\Facade\Facade;

trait Localizable
{
    /**
     * Run the callback with the given locale.
     *
     * @param  string  $locale
     * @param  \Closure  $callback
     * @return mixed
     */
    public function withLocale($locale, $callback)
    {
        if (! $locale) {
            return $callback();
        }

        $app = Facade::getFacadeApplication();

        $original = $app->getLocale();

        try {
            $app->setLocale($locale);

            return $callback();
        } finally {
            $app->setLocale($original);
        }
    }
}
