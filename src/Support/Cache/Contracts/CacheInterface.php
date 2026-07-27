<?php

namespace Eyika\Atom\Framework\Support\Cache\Contracts;

use Psr\Cache\CacheItemPoolInterface;

interface CacheInterface extends CacheItemPoolInterface
{
    /**
     * @param CacheItem $item
     */
    public function setItem($item): bool;
}
