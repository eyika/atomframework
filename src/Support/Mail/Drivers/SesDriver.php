<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Aws\SesV2\SesV2Client;
use Aws\Exception\AwsException;
use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

/**
 * Amazon SES driver, on the SES **v2** API (`SendEmail`, api version 2019-09-27).
 *
 * v2 is used rather than v1 specifically so the driver can carry the things the v1 `SendEmail`
 * action had no field for:
 *   - **Custom headers** — `Content.Simple.Message.Headers`, a list of `{Name, Value}`. This is
 *     what makes `List-Unsubscribe` / `List-Unsubscribe-Post` deliverable, the bulk-mail
 *     compliance pair Gmail and Yahoo have required since February 2024. The v1 driver had to
 *     refuse these sends outright; v2 sends them.
 *   - **Configuration sets** — `ConfigurationSetName`, set from the `configuration_set` config key
 *     when present, so event publishing / dedicated IPs / suppression lists apply to the send.
 *
 * SES still reserves a handful of header names it sets itself (From, To, Subject, …) and rejects
 * them in the Headers list; we pass the caller's headers through and let SES be the authority on
 * which are allowed, so a disallowed header fails loudly at send time rather than being dropped.
 */
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

        // Initialize the SES v2 Client with configuration settings
        $this->client = new SesV2Client([
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

    /**
     * Map the collected state into the SES v2 `SendEmail` request shape. Kept separate from the
     * send itself so the mapping — especially custom headers and the configuration set — is
     * verifiable without an AWS round-trip.
     *
     * @return array<string, mixed>
     */
    protected function buildParams(string $subject, string $body): array
    {
        $params = [
            'FromEmailAddress' => $this->from ?? $this->config['from'],
            'Destination' => [
                'ToAddresses' => $this->tos,
            ],
            'Content' => [
                'Simple' => [
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
            ],
        ];

        if (!empty($this->replyTos)) {
            $params['ReplyToAddresses'] = $this->replyTos;
        }

        // Custom headers ride in Content.Simple.Message.Headers as a {Name, Value} list — the v2
        // field the v1 SendEmail action lacked. The trait has already rejected any CR/LF, so the
        // only remaining gatekeeper is SES's own reserved-name check.
        if ($this->customHeaders) {
            $headers = [];
            foreach ($this->customHeaders as $name => $value) {
                $headers[] = ['Name' => $name, 'Value' => $value];
            }
            $params['Content']['Simple']['Headers'] = $headers;
        }

        // Configuration set is optional; attach it only when configured so a plain account that
        // has never created one still sends.
        $configurationSet = $this->config['configuration_set'] ?? null;
        if (!empty($configurationSet)) {
            $params['ConfigurationSetName'] = $configurationSet;
        }

        return $params;
    }

    public function send($subject, $body): MailerResponse
    {
        try {
            $result = $this->client->sendEmail($this->buildParams($subject, $body));

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
