<?php

namespace Eyika\Atom\Support\Interfaces;

use Eyika\Atom\Http\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}
