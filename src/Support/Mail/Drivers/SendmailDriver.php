<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;

class SendmailDriver implements MailerInterface
{
    protected $mailer;

    public function __construct(array $config)
    {
        if (empty($config)) {
            throw new Exception('bad configuration data');
        }
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSendmail();
        $this->mailer->Sendmail = $config['path'] ?? config('mail.sendmail');
    }

    public function to(string $address, string $name = null): self
    {
        $this->mailer->addAddress($address, $name ?? '');
        return $this;
    }

    public function replyTo(string $address, string $name = null): self
    {
        $this->mailer->addReplyTo($address, $name ?? '');
        return $this;
    }

    public function from(string $address, string $name): self
    {
        $this->mailer->setFrom($address, $name);
        return $this;
    }

    public function send($subject, $body): MailerResponse
    {
        try {
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $result = $this->mailer->send();

            return new MailerResponse($result, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
