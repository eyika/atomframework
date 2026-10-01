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

    /** Did the provider accept the message? */
    public function successful(): bool
    {
        return $this->success;
    }

    /**
     * Did it fail? The reason is in `->error`.
     *
     * This exists because `if ($mailer->send($subject))` is **always true** — the method returns an
     * object, and every object is truthy. There is no `__toBool` in PHP, so the shape of this class
     * cannot protect a caller who treats it as a bool; a method that says what it means can.
     */
    public function failed(): bool
    {
        return !$this->success;
    }

    /**
     * Raise if the message was not accepted, for a caller that would rather not branch.
     *
     * The driver catches the provider's exception and reports it here instead, so a `try/catch`
     * around `send()` is dead code. This is how to get the throwing behaviour back.
     */
    public function throwIfFailed(): self
    {
        if ($this->success) {
            return $this;
        }

        throw $this->exception ?? new Exception($this->error ?? 'The mail provider refused the message.');
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
