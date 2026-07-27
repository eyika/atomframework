<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

/**
 * A service provider that should NOT be registered/booted on every request, but only
 * the first time one of the services it provides is resolved from the container
 * (PKG-04). Reduces per-request boot cost — especially valuable under persistent
 * workers.
 */
interface DeferrableProvider
{
    /**
     * The container abstracts this provider registers. When any of them is first
     * make()'d, the provider is registered and booted on demand.
     *
     * @return string[]
     */
    public function provides(): array;
}
