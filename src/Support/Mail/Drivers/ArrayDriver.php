<?php
namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class ArrayDriver implements MailerInterface
{
    protected static $sentEmails = [];

    public function send($to, $subject, $body): MailerResponse
    {
        try {
            // Store the email in the array
            self::$sentEmails[] = [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
            ];

            return new MailerResponse(true, null, null);
        } catch (\Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }

    public static function getSentEmails()
    {
        return self::$sentEmails;
    }
}
