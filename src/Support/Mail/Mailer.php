<?php
namespace Eyika\Atom\Framework\Support\Mail;

use Eyika\Atom\Framework\Support\ArrayDriver;
use Eyika\Atom\Framework\Support\LogDriver;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use Eyika\Atom\Framework\Support\Mail\Drivers\FailoverDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\MailgunDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\PostmarkDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\SendmailDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\SesDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\SmtpDriver;
use Eyika\Atom\Framework\Support\View\Twig;

class Mailer
{
    protected MailerInterface $driver;
    protected array $config;
    protected string $html;

    public function __construct(array $config = null)
    {
        $this->config = $config ?? config('mail.mailers', [])[config('mail.default')];
        $this->setDriver($config['driver'] ?? 'smtp');
    }

    public function setDriver(string $driver)
    {
        switch ($driver) {
            case 'smtp':
                $this->driver = new SmtpDriver($this->config['smtp'] ?? []);
                break;
            case 'ses':
                $this->driver = new SesDriver($this->config['ses'] ?? []);
                break;
            case 'mailgun':
                $this->driver = new MailgunDriver($this->config['mailgun'] ?? []);
                break;
            case 'postmark':
                $this->driver = new PostmarkDriver($this->config['postmark'] ?? []);
                break;
            case 'sendmail':
                $this->driver = new SendmailDriver($this->config['sendmail'] ?? []);
                break;
            case 'log':
                $this->driver = new LogDriver($this->config['log'] ?? []);
                break;
            case 'array':
                $this->driver = new ArrayDriver($this->config['array'] ?? []);
                break;
            case 'failover':
                $this->driver = new FailoverDriver($this->config['failover'] ?? []);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported mail driver: $driver");
        }
    }

    public function buildHtml(string $templateName, array $data = [], string $resourcePath = null)
    {
        $this->html = Twig::make($templateName, $resourcePath ?? config('mail.markdown.paths'), $data, true);
    }

    public function send($to, $subject): MailerResponse
    {
        return $this->driver->send($to, $subject, $this->html);
    }
}
