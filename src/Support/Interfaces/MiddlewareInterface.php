<?php

namespace Basttyy\FxDataServer\libs\Interfaces;

use Basttyy\FxDataServer\libs\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}