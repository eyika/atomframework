<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use GuzzleHttp\Client;
use Mailgun\HttpClient\HttpClientConfigurator;
use Mailgun\Hydrator\ArrayHydrator;
use Mailgun\Mailgun;

class MailgunDriver implements MailerInterface
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

    public function send($subject, $body): MailerResponse
    {
        try {
            $response = $this->client->messages()->send($this->config['mailgun']['domain'], [
                'from'    => $this->config['mailgun']['from'],
                'to'      => $this->tos,
                'subject' => $subject,
                'html'    => $body,
            ]);

            return new MailerResponse(true, $response->getId());
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
