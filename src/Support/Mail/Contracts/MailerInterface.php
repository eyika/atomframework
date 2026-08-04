<?php
namespace Eyika\Atom\Framework\Support\Mail\Contracts;

interface MailerInterface
{
    public function to(string $address, string|null $name = null): MailerInterface;
    public function from(string $address, string $name): MailerInterface;
    public function replyTo(string $address, string|null $name = null): MailerInterface;

    /**
     * Set a custom message header — `List-Unsubscribe`, `X-Entity-Ref-ID`, and so on.
     *
     * Required for bulk mail: Gmail and Yahoo have required `List-Unsubscribe` plus
     * `List-Unsubscribe-Post: List-Unsubscribe=One-Click` since February 2024.
     *
     * Not every transport can carry arbitrary headers — the SES v1 `SendEmail` API cannot, and
     * that driver throws at send time rather than dropping them, since a silently omitted
     * compliance header is exactly the failure this exists to prevent.
     */
    public function header(string $name, string $value): MailerInterface;

    /** @param array<string, string> $headers */
    public function headers(array $headers): MailerInterface;

    /** @return array<string, string> */
    public function getCustomHeaders(): array;

    public function send(string $subject, string $body): MailerResponse;
}
