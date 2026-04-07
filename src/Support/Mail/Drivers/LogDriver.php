<?php
namespace Eyika\Atom\Framework\Support;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class LogDriver implements MailerInterface
{
    protected $logger;
    protected array $tos;

    public function __construct(array $config = [])
    {
        $this->tos = [];
        $path = $config['path'] ?? storage_path('logs/mail.log');
        $this->logger = $config['logger'] ?? logger($path, name: 'mail');
    }

    public function to(string $address, string|null $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        try {
            $this->logger->info('Sending email', [
                'to' => $this->tos,
                'subject' => $subject,
                'body' => $body,
            ]);

            $this->tos = [];
            return new MailerResponse(true, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
