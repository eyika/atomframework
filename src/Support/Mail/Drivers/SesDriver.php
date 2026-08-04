<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class SesDriver implements MailerInterface
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
            // The SES v1 SendEmail action has no field for arbitrary headers — only SendRawEmail,
            // which takes a pre-built MIME message, can carry them. Fail loudly rather than
            // sending without: a silently dropped List-Unsubscribe is precisely the outcome
            // header support exists to prevent, and it would only surface later as throttling.
            if ($this->customHeaders) {
                throw new Exception(
                    'The SES driver cannot send custom headers ('
                    . implode(', ', array_keys($this->customHeaders))
                    . ') — the SendEmail API has no field for them. Use the SMTP, Mailgun or '
                    . 'Postmark driver for mail that requires List-Unsubscribe.'
                );
            }

            $params = [
                'Source' => $this->from ?? $this->config['from'],
                'Destination' => [
                    'ToAddresses' => $this->tos,
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
                            'Data' => strip_tags($body),
                        ],
                    ],
                ],
            ];
            if (!empty($this->replyTos)) {
                $params['ReplyToAddresses'] = $this->replyTos;
            }
            $result = $this->client->sendEmail($params);

            // Return a standardized response structure
            return new MailerResponse(true, $result->get('MessageId'), null);
        } catch (AwsException $e) {
            // Return a failure response with the error message
            return new MailerResponse(false, null, $e->getAwsErrorMessage());
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        } finally {
            $this->clearCustomHeaders();
        }
    }
}
