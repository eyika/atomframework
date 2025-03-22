<?php

namespace Eyika\Atom\Framework\Support\Traits;

trait Tappable
{
    /**
     * Call the given Closure with this instance then return the instance.
     *
     * @param  (callable($this): mixed)|null  $callback
     * @return ($callback is null ? \Eyika\Atom\Framework\Support\HigherOrderTapProxy : $this)
     */
    public function tap($callback = null)
    {
        return tap($this, $callback);
    }
}
