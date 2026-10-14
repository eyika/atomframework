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
     * Every bundled transport carries arbitrary headers — the SES driver does so on the SES v2
     * `SendEmail` API (`Content.Simple.Message.Headers`), which is why it runs on v2 rather than
     * v1, whose `SendEmail` action had no field for them.
     */
    public function header(string $name, string $value): MailerInterface;

    /** @param array<string, string> $headers */
    public function headers(array $headers): MailerInterface;

    /** @return array<string, string> */
    public function getCustomHeaders(): array;

    public function send(string $subject, string $body): MailerResponse;
}
