<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class FailoverDriver implements MailerInterface
{
    /**
     * @var array<string>
     */
    protected $mailers;

    protected $config;
    protected array $tos;

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->config = $config;
        $this->mailers = $config['mailers'];
    }

    public function to(string $address, string $name = null): self
    {
        array_push($this->tos, $address);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        foreach ($this->mailers as $mailerClass) {
            try {
                $mailer = new $mailerClass($this->config);
                /**
                 * @var MailerInterface $mailer
                 */
                $response = $mailer->send($this->tos[0] ?? '', $subject, $body);
                
                if ($response['success']) {
                    return $response; // Return on successful send
                }
            } catch (Exception $e) {
                // Continue to the next mailer in case of failure
            }
        }

        return new MailerResponse(false, null, 'All failover mailers failed');
    }
}
