<?php

namespace Eyika\Atom\Framework\Support\Interfaces;

use Eyika\Atom\Framework\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}
