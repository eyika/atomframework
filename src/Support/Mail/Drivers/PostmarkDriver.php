<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Exception;
use Postmark\PostmarkClient;
use Eyika\Atom\Framework\Support\BaseMailer;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class PostmarkDriver implements MailerInterface
{
    use CollectsCustomHeaders;
    protected $client;
    protected $config;
    protected array $tos;
    protected ?string $from = null;
    protected array $replyTos = [];

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->config = $config;
        $this->tos = [];

        // Initialize the Postmark client with the provided server token
        $this->client = new PostmarkClient($config['token']);
    }

    public function to(string $address, string|null $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public function from(string $address, string $name): self
    {
        $this->from = "$name <$address>";
        return $this;
    }

    public function replyTo(string $address, string|null $name = null): self
    {
        array_push($this->replyTos, $name ? "$name <$address>" : $address);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        try {
            // Named arguments, because these were previously positional and the reply-to landed
            // in the TENTH slot — which is $bcc, not $replyTo. So replies were not routed AND
            // the reply-to address silently received a blind copy of every message.
            $result = $this->client->sendEmail(
                from: $this->from ?? $this->config['from'],
                to: $this->tos[0] ?? '',
                subject: $subject,
                htmlBody: $body,
                replyTo: !empty($this->replyTos) ? implode(', ', $this->replyTos) : null,
                headers: $this->customHeaders ?: null,
            );

            // Return a standardized response structure
            return new MailerResponse(true, $result['MessageID'], null);
        } catch (Exception $e) {
            // Return a failure response with the error message
            return new MailerResponse(false, null, $e->getMessage(), $e);
        } finally {
            $this->clearCustomHeaders();
        }
    }
}
