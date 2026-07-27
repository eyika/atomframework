<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class SmtpDriver implements MailerInterface
{
    protected PHPMailer $mailer;
    /**
     * BaseMailer constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->mailer = new PHPMailer($config['exception'] ?? true);
        $host = $config['host'];
        $port = $config['port'];
        //Set a default 'From' address
        $this->mailer->Host = $host;
        $this->mailer->Port = $port;
        //Send via SMTP
        $this->mailer->isSMTP();
        $this->mailer->SMTPSecure = $config['encryption'];
        if (isset($config['password']) && isset($config['username'])) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Password = $config['password'];
            $this->mailer->Username = $config['username'];
        }
        //Show debug output
        $this->mailer->SMTPDebug = config('app.env') === 'local' ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;

        //Inject a new debug output handler
        $this->mailer->Debugoutput = static function ($str, $level) {
            consoleLog($level, $str);
        };
    }

    public function to(string $address, string|null $name = null): self
    {
        $this->mailer->addAddress($address, $name ?? '');
        return $this;
    }

    public function replyTo(string $address, string|null $name = null): self
    {
        $this->mailer->addReplyTo($address, $name ?? '');
        return $this;
    }

    public function from(string $address, string $name): self
    {
        $this->mailer->setFrom($address, $name);
        return $this;
    }

    //Extend the send function
    public function send(string $subject, string $body): MailerResponse
    {
        $r = false;
        try {
            $this->mailer->Subject = $subject;
            // Set HTML body. No basedir: image src attributes are hosted
            // on an HTTP(S) endpoint, so PHPMailer shouldn't try to resolve
            // them against a local directory or inline-attach them.
            $this->mailer->msgHTML($body);
            $r = $this->mailer->send();

            return new MailerResponse($r, $this->mailer->getLastMessageID());
        } catch (Exception $e) {
            return new MailerResponse($r, null, $e->getMessage(), $e);
        } finally {
            // The Mailer facade keeps a single static PHPMailer instance
            // across the lifetime of the PHP process. Without clearing, each
            // to()/replyTo() call accumulates, so subsequent sends within
            // the same queue:work run try to send to every prior recipient
            // plus the new one — multiplying actual send attempts and
            // blowing through provider hourly quotas.
            $this->mailer->clearAllRecipients();
            $this->mailer->clearReplyTos();
            $this->mailer->clearAttachments();
        }
    }
}
