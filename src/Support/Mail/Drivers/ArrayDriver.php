<?php
namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class ArrayDriver implements MailerInterface
{
    protected static $sentEmails = [];
    protected array $tos;

    public function send($subject, $body): MailerResponse
    {
        try {
            // Store the email in the array
            self::$sentEmails[] = [
                'to' => $this->tos,
                'subject' => $subject,
                'body' => $body,
            ];

            return new MailerResponse(true, null, null);
        } catch (\Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }

    public function to(string $address, string $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public static function getSentEmails()
    {
        return self::$sentEmails;
    }
}
