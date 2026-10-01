<?php
namespace Eyika\Atom\Framework\Support\Mail\Drivers;

use Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

use Exception;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class SmtpDriver implements MailerInterface
{
    use CollectsCustomHeaders;
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
    /**
     * Record a send failure, defensively.
     *
     * Wrapped because this runs on the failure path: `logger()` resolves `config()`, and an error
     * raised before configuration is loadable would otherwise fault inside the very handler meant
     * to report it (the lesson of BUG-57). A failure to log must never replace the failure itself.
     */
    private static function logFailure(string $subject, string $reason): void
    {
        try {
            logger()->error('mail: send failed', ['subject' => $subject, 'reason' => $reason]);
        } catch (\Throwable) {
            error_log("mail: send failed [{$subject}]: {$reason}");
        }
    }

    public function send(string $subject, string $body): MailerResponse
    {
        $r = false;
        try {
            foreach ($this->customHeaders as $name => $value) {
                $this->mailer->addCustomHeader($name, $value);
            }

            $this->mailer->Subject = $subject;
            // Set HTML body. No basedir: image src attributes are hosted
            // on an HTTP(S) endpoint, so PHPMailer shouldn't try to resolve
            // them against a local directory or inline-attach them.
            $this->mailer->msgHTML($body);
            $r = $this->mailer->send();

            if (!$r) {
                // PHPMailer answered false without raising. Record it for the same reason as the
                // catch below: this object is the only copy of the reason, and a caller who ignores
                // it leaves nothing behind at all.
                self::logFailure($subject, 'the mail provider refused the message');
            }

            return new MailerResponse($r, $this->mailer->getLastMessageID());
        } catch (Exception $e) {
            // The exception is deliberately NOT rethrown — the contract is a MailerResponse — but it
            // must not vanish either. `send()` returns an object, so `if ($mail->send(...))` is
            // always true, and a caller who writes that reports success for a message the server
            // REFUSED. Logging here means the failure has a symptom even then: without it the one
            // copy of the provider's reason died microseconds after it was produced, and the mail
            // log showed only our own "sending ..." line with nothing beneath it.
            self::logFailure($subject, $e->getMessage());

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
            // Same reasoning as the recipients above: one static PHPMailer for the whole process
            // means an uncleared List-Unsubscribe would ride along on the next message sent.
            $this->mailer->clearCustomHeaders();
            $this->clearCustomHeaders();
        }
    }
}
