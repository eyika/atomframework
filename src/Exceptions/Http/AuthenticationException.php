<?php 

namespace Eyika\Atom\Framework\Exceptions\Http;

use Throwable;

class AuthenticationException extends BaseHttpException
{
    protected array $guards;
    protected string $to;

    public function __construct(string $message = 'invalid auth information', array $guards = [], string $to = '/')
    {
        parent::__construct($message, 403);
        $this->guards = $guards;
        $this->to = $to;
    }

    public function guards()
    {
        return $this->guards;
    }

    public function to()
    {
        return $this->to;
    }
}
