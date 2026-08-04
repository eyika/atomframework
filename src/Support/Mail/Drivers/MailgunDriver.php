<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use GuzzleHttp\Client;
use Mailgun\HttpClient\HttpClientConfigurator;
use Mailgun\Hydrator\ArrayHydrator;
use Mailgun\Mailgun;

class MailgunDriver implements MailerInterface
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
        $configurator = new HttpClientConfigurator();
        $configurator->setHttpClient(new Client());
        $configurator->setApiKey($config['key']);
        $configurator->setDebug(config('app.env') === 'local');

        $this->client = new Mailgun($configurator, new ArrayHydrator); // Assuming Guzzle as the HTTP client
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
            $payload = [
                'from'    => $this->from ?? $this->config['mailgun']['from'],
                'to'      => $this->tos,
                'subject' => $subject,
                'html'    => $body,
            ];
            if (!empty($this->replyTos)) {
                $payload['h:Reply-To'] = implode(', ', $this->replyTos);
            }

            // Mailgun carries arbitrary headers as `h:` prefixed payload keys — the same
            // mechanism the Reply-To above already uses.
            foreach ($this->customHeaders as $name => $value) {
                $payload['h:' . $name] = $value;
            }

            $response = $this->client->messages()->send($this->config['mailgun']['domain'], $payload);

            return new MailerResponse(true, $response->getId());
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        } finally {
            $this->clearCustomHeaders();
        }
    }
}
