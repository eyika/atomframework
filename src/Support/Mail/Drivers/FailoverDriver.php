<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class FailoverDriver implements MailerInterface
{
    use CollectsCustomHeaders;
    /**
     * @var array<string>
     */
    protected $mailers;

    protected $config;
    protected array $tos = [];
    protected array $from = [];
    protected array $replyTos = [];

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->config = $config;
        $this->mailers = $config['mailers'];
    }

    public function to(string $address, string|null $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public function from(string $address, string $name): self
    {
        $this->from = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function replyTo(string $address, string|null $name = null): self
    {
        array_push($this->replyTos, $name ? "$name <$address>" : $address);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        foreach ($this->mailers as $mailerClass) {
            try {
                $mailer = new $mailerClass($this->config);
                /**
                 * @var MailerInterface $mailer
                 */
                // Forward collected headers to whichever transport actually sends, or a
                // List-Unsubscribe would silently vanish the moment the primary failed over.
                if ($this->customHeaders) {
                    $mailer->headers($this->customHeaders);
                }

                $response = $mailer->send($this->tos[0] ?? '', $subject, $body);
                
                // `MailerResponse` is a plain object — it implements no ArrayAccess, and its
                // `__toArray()` is an ordinary method PHP never calls implicitly. This read used
                // to be `$response['success']`, which raises "Cannot use object of type
                // MailerResponse as array" on EVERY send, including successful ones: an Error,
                // so it escaped the Exception-only catch below and took the whole send down.
                // This driver could therefore never return a success.
                if ($response->success) {
                    return $response; // Return on successful send
                }
            } catch (\Throwable $e) {
                // Continue to the next mailer in case of failure.
                //
                // Throwable, not Exception: a driver blowing up with an Error — a TypeError from
                // a malformed config value, a missing SDK class — is exactly the case failover
                // exists for. Catching Exception alone let those escape and kill the send
                // outright instead of trying the next mailer, which defeats this driver.
            }
        }

        $this->clearCustomHeaders();

        return new MailerResponse(false, null, 'All failover mailers failed');
    }
}
