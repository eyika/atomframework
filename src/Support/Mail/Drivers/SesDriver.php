<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class SesDriver implements MailerInterface
{
    protected $client;
    protected $config;
    protected array $tos;

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->tos = [];

        $this->config = $config;

        // Initialize the SES Client with configuration settings
        $this->client = new SesClient([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
    }

    public function to(string $address, string|null $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        try {
            // Send the email using SES
            $result = $this->client->sendEmail([
                'Source' => $this->config['from'], // Sender email
                'Destination' => [
                    'ToAddresses' => $this->tos, // Recipient email
                ],
                'Message' => [
                    'Subject' => [
                        'Data' => $subject,
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $body,
                        ],
                        'Text' => [
                            'Data' => strip_tags($body), // Fallback text body
                        ],
                    ],
                ],
            ]);

            // Return a standardized response structure
            return new MailerResponse(true, $result->get('MessageId'), null);
        } catch (AwsException $e) {
            // Return a failure response with the error message
            return new MailerResponse(false, null, $e->getAwsErrorMessage());
        }
    }
}
