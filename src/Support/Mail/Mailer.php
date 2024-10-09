<?php
namespace Eyika\Atom\Framework\Support\Mail;

use BadMethodCallException;
use Exception;
use Eyika\Atom\Framework\Support\Arr;
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
    protected static MailerInterface $driver;
    protected static array $config;
    protected static string $html;
    private static $instantiated = false;

    /**
     * @param array $config  The config data of the intended mailer driver
     */
    public function __construct(array $config = null, string $driver = null)
    {
        if (self::$instantiated) {
            // Prevent multiple instantiations
            return;
        }

        self::$instantiated = true;
        $driver = $driver ?? config('mail.default');
        self::$config = $config ?? config('mail.mailers', [])[$driver];
        self::$config['driver'] = $driver;

        self::setDriver(self::$config['transport']);
    }

    public static function init(string $driver = null, array $config = null): self
    {
        if (!self::$instantiated) {
            return new static($config, $driver); // Only instantiate if not already instantiated
        }

        return new static;
    }

    public static function setDriver(string $transport): self
    {
        switch ($transport) {
            case 'smtp':
                self::$driver = new SmtpDriver(self::$config ?? []);
                break;
            case 'ses':
                self::$driver = new SesDriver(self::$config ?? []);
                break;
            case 'mailgun':
                self::$driver = new MailgunDriver(self::$config ?? []);
                break;
            case 'postmark':
                self::$driver = new PostmarkDriver(self::$config ?? []);
                break;
            case 'sendmail':
                self::$driver = new SendmailDriver(self::$config ?? []);
                break;
            case 'log':
                self::$driver = new LogDriver(self::$config ?? []);
                break;
            case 'array':
                self::$driver = new ArrayDriver(self::$config ?? []);
                break;
            case 'failover':
                self::$driver = new FailoverDriver(self::$config ?? []);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported mail driver: $transport");
        }
        return new static;
    }

    public static function to(string $address, string $name = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->to($address, $name);
        return new static;
    }

    public static function from(string $address, string $name = null): self
    {
        if (!self::$instantiated) {
            new static;
        }

        if (!self::$driver instanceof SmtpDriver && !self::$driver instanceof SendmailDriver) {
            throw new BadMethodCallException('This method only exists for smtp and sendmail drivers');
        }

        self::$driver->from($address, $name);
        return new static;
    }

    public static function buildHtml(string $templateName, array $data = [], string $resourcePath = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$html = Twig::make($templateName, $resourcePath ?? config('mail.markdown.paths'), $data, true);
        return new static;
    }

    public static function send($subject, $to = null): MailerResponse
    {
        if (!self::$instantiated) {
            new static;
        }

        if ($to) {
            self::to($to);
        }

        return self::$driver->send($subject, self::$html);
    }
}
