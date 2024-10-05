<?php
namespace Eyika\Atom\Framework\Support;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Mailer extends PHPMailer
{
    /**
     * BaseMailer constructor.
     *
     * @param bool|null $exceptions
     * @param string $user
     * @param string $pass
     * @param string    $body A default HTML message body
     */
    public function __construct($exceptions, $user, $pass, $body = '')
    {
        $host = env('EMAIL_HOST');
        $port = env('EMAIL_PORT');
        //Don't forget to do this or other things may not be set correctly!
        parent::__construct($exceptions);
        //Set a default 'From' address
        $this->isSMTP();
        $this->Host = $host;
        $this->Port = $port;
        //Send via SMTP
        $this->SMTPAuth = true;
        $this->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        //Equivalent to setting `Host`, `Port` and `SMTPSecure` all at once
        // $this->Host = "tls://$host:$port";
        $this->Password = $pass;
        $this->Username = $user;
        //Set an HTML and plain-text body, import relative image references
        $this->msgHTML($body, './images/');
        //Show debug output
        $this->SMTPDebug = config('app.env') === 'local' ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;

        //Inject a new debug output handler
        $this->Debugoutput = static function ($str, $level) {
            consoleLog($level, $str);
        };
    }

    //Extend the send function
    public function send()
    {
        $this->Subject = $this->Subject;
        $r = parent::send();
        if (config('app.env') === 'local')
            // logger(storage_path()."logs/email.log")->info('I sent a message with subject ' . $this->Subject);

        return $r;
    }
}
