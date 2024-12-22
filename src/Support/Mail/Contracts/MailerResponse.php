<?php
namespace Eyika\Atom\Framework\Support\Mail\Contracts;

use Exception;

class MailerResponse
{
    public bool $success;
    public int | string | null $message_id;
    public string | null $error;
    public Exception | null $exception;

    public function __construct(bool $success, int | string|null $message_id = null, string|null $error = null, Exception|null $exception = null)
    {
        $this->success = $success;
        $this->message_id = $message_id;
        $this->error = $error;
        $this->exception = $exception;
    }

    public function __toArray()
    {
        return [
            'success' => $this->success,
            'message_id' => $this->message_id,
            'error' => $this->error,
            'exception' => $this->exception,
        ];
    }
}
