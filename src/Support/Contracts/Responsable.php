<?php

namespace Eyika\Atom\Framework\Support\Contracts;

interface Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request);
}
