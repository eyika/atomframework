<?php

namespace Eyika\Atom\Framework\Support\Mail\Concerns;

/**
 * Custom message headers, collected on the driver and applied at send time in whatever form the
 * transport expects (a PHPMailer custom header, a Mailgun `h:` key, a Postmark Headers array …).
 *
 * The motivating case is bulk-mail compliance: since February 2024 Gmail and Yahoo require
 * `List-Unsubscribe` together with `List-Unsubscribe-Post: List-Unsubscribe=One-Click` on bulk
 * sends, and a sender without them is throttled or binned. An in-body unsubscribe link satisfies
 * a human reader but not the automated check.
 */
trait CollectsCustomHeaders
{
    /** @var array<string, string> */
    protected array $customHeaders = [];

    /**
     * Set one header. Re-setting the same name replaces it, so a caller cannot accidentally emit
     * a duplicate `List-Unsubscribe`.
     */
    public function header(string $name, string $value): self
    {
        $name = trim($name);
        $value = trim($value);

        // A newline in either half would let a caller inject additional headers or split the
        // message body — the classic header-injection hole. Reject rather than sanitise, so a
        // mistake is visible instead of silently altering what was sent.
        if (preg_match('/[\r\n]/', $name . $value)) {
            throw new \InvalidArgumentException("Mail header [{$name}] must not contain CR or LF.");
        }

        if ($name === '') {
            throw new \InvalidArgumentException('Mail header name must not be empty.');
        }

        $this->customHeaders[$name] = $value;

        return $this;
    }

    /**
     * Set several headers at once.
     *
     * @param array<string, string> $headers
     */
    public function headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header((string) $name, (string) $value);
        }

        return $this;
    }

    /** @return array<string, string> */
    public function getCustomHeaders(): array
    {
        return $this->customHeaders;
    }

    /**
     * Drop collected headers. Drivers call this after a send for the same reason they clear
     * recipients: the Mailer keeps ONE driver instance for the life of the process, so without it
     * one campaign's List-Unsubscribe would ride along on the next message sent in the same
     * queue:work run.
     */
    public function clearCustomHeaders(): void
    {
        $this->customHeaders = [];
    }
}
