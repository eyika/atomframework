<?php
namespace Eyika\Atom\Framework\Support;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class LogDriver implements MailerInterface
{
    protected $logger;

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->logger = $config['logger'] ?? new \Monolog\Logger('mail');
    }

    public function send($to, $subject, $body): MailerResponse
    {
        try {
            $this->logger->info('Sending email', [
                'to' => $to,
                'subject' => $subject,
                'body' => $body,
            ]);

            return new MailerResponse(true, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
