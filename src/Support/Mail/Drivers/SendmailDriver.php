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

    public function send($to, $subject, $body): MailerResponse
    {
        try {
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $result = $this->mailer->send();

            return new MailerResponse($result, null, null);
        } catch (Exception $e) {
            return new MailerResponse(false, null, $e->getMessage(), $e);
        }
    }
}
