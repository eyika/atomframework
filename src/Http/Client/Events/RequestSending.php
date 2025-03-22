<?php

namespace Eyika\Atom\Framework\Http\Client\Events;

use Eyika\Atom\Framework\Http\Client\Request;

class RequestSending
{
    /**
     * The request instance.
     *
     * @var \Eyika\Atom\Framework\Http\Client\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param  \Eyika\Atom\Framework\Http\Client\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
