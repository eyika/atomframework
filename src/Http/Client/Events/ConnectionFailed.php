<?php

namespace Eyika\Atom\Framework\Http\Client\Events;

use Eyika\Atom\Framework\Http\Client\ConnectionException;
use Eyika\Atom\Framework\Http\Client\Request;

class ConnectionFailed
{
    /**
     * The request instance.
     *
     * @var \Eyika\Atom\Framework\Http\Client\Request
     */
    public $request;

    /**
     * The exception instance.
     *
     * @var \Eyika\Atom\Framework\Http\Client\ConnectionException
     */
    public $exception;

    /**
     * Create a new event instance.
     *
     * @param  \Eyika\Atom\Framework\Http\Client\Request  $request
     * @param  \Eyika\Atom\Framework\Http\Client\ConnectionException  $exception
     * @return void
     */
    public function __construct(Request $request, ConnectionException $exception)
    {
        $this->request = $request;
        $this->exception = $exception;
    }
}
