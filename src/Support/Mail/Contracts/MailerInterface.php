<?php
namespace Eyika\Atom\Framework\Support\Mail\Contracts;

interface MailerInterface
{
    public function to(string $address, string|null $name = null): MailerInterface;
    public function from(string $address, string $name): MailerInterface;
    public function replyTo(string $address, string|null $name = null): MailerInterface;
    public function send(string $subject, string $body): MailerResponse;
}
