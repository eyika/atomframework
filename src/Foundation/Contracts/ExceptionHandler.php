<?php

namespace Eyika\Atom\Framework\Foundation\Contracts;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;

interface ExceptionHandler
{
    public function render(Request $request, \Throwable $exception): BaseResponse;
}