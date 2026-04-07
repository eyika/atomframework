<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class LogDriver implements MailerInterface
{
    protected $logger;
    protected array $tos;
    protected array $from = [];
    protected array $replyTos = [];

    public function __construct(array $config = [])
    {
        $this->tos = [];
        $path = $config['path'] ?? storage_path('logs/mail.log');
        $this->logger = $config['logger'] ?? logger($path, name: 'mail');
    }

    public function to(string $address, string|null $name = null): self
    {
        array_push($this->tos, $name ? "$name <$address>" : $address);
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
        try {
            $this->logger->info('Sending email', [
                'from' => $this->from ? "{$this->from['name']} <{$this->from['address']}>" : null,
                'to' => $this->tos,
                'reply_to' => $this->replyTos,
                'subject' => $subject,
                'body' => $body,
            ]);

            $this->tos = [];
            $this->from = [];
            $this->replyTos = [];
            return new MailerResponse(true, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
