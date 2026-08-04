<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class LogDriver implements MailerInterface
{
    use CollectsCustomHeaders;
    protected $logger;
    protected array $tos;
    protected array $from = [];
    protected array $replyTos = [];

    public function __construct(array $config = [])
    {
        $this->tos = [];
        $path = $config['path'] ?? storage_path('logs/mail.log');

        if (isset($config['logger'])) {
            $this->logger = $config['logger'];
            return;
        }

        // Build a Monolog logger that writes each email on a SINGLE line so the
        // log viewer's line-based regex parser can read the whole JSON context
        // (including the HTML body) as one entry.
        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($format, 'Y-m-d H:i:s', false, true);
        $handler = new StreamHandler($path, Level::Debug);
        $handler->setFormatter($formatter);

        $this->logger = (new Logger('mail'))->pushHandler($handler);
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
                'headers' => $this->customHeaders,
            ]);

            $this->tos = [];
            $this->from = [];
            $this->replyTos = [];
            $this->clearCustomHeaders();
            return new MailerResponse(true, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
