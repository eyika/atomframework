<?php

namespace Eyika\Atom\Framework\Http\Client\Events;

use Eyika\Atom\Framework\Http\Client\Request;
use Eyika\Atom\Framework\Http\Client\Response;

class ResponseReceived
{
    /**
     * The request instance.
     *
     * @var \Eyika\Atom\Framework\Http\Client\Request
     */
    public $request;

    /**
     * The response instance.
     *
     * @var \Eyika\Atom\Framework\Http\Client\Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param  \Eyika\Atom\Framework\Http\Client\Request  $request
     * @param  \Eyika\Atom\Framework\Http\Client\Response  $response
     * @return void
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
