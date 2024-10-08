<?php
namespace Eyika\Atom\Framework\Support\Mail\Contracts;

interface MailerInterface
{
    public function to(string $address, string $name = null): MailerInterface;
    public function send(string $subject, string $body): MailerResponse;
}
