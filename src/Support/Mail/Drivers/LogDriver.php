<?php
namespace Eyika\Atom\Framework\Support;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class LogDriver implements MailerInterface
{
    protected $logger;
    protected array $tos;

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->tos = [];
        $this->logger = $config['logger'] ?? new \Monolog\Logger('mail');
    }

    public function to(string $address, string $name = null): self
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

            return new MailerResponse(true, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
