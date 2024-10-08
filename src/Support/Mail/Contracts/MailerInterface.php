<?php
namespace Eyika\Atom\Framework\Support\Mail\Contracts;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): MailerResponse;
}
