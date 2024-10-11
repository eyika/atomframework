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

    //Extend the send function
    public function send(string $subject, string $body): MailerResponse
    {
        $r = false;
        try {
            $this->mailer->Subject = $subject;
            //Set an HTML and plain-text body, import relative image references
            $this->mailer->msgHTML($body, './images/'); //TODO: images path not yet correct
            $r = $this->mailer->send();
    
            return new MailerResponse($r, $this->mailer->getLastMessageID());
        } catch (Exception $e) {
            return  new MailerResponse($r, null, $e->getMessage(), $e);
        }
    }
}
