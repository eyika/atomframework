<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

interface ExceptionHandler
{
    public function render($request, \Throwable $exception);
}