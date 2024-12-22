<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use Postmark\PostmarkClient;
use Eyika\Atom\Framework\Support\BaseMailer;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class PostmarkDriver implements MailerInterface
{
    protected $client;
    protected $config;
    protected array $tos;

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

    public function send($subject, $body): MailerResponse
    {
        try {
            // Send the email using Postmark
            $result = $this->client->sendEmail(
                $this->config['from'], // From email address
                $this->tos[0] ?? '',                              // To email address
                $subject,                          // Subject of the email
                $body                              // HTML body content
            );

            // Return a standardized response structure
            return new MailerResponse(true, $result['MessageID'], null);
        } catch (Exception $e) {
            // Return a failure response with the error message
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
