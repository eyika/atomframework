<?php
namespace Eyika\Atom\Framework\Support\Mail;

use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use Eyika\Atom\Framework\Support\Mail\Drivers\ArrayDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\FailoverDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\LogDriver;
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
    public function __construct(array|null $config = null, string|MailerInterface|null $driver = null)
    {
        if (self::$instantiated) {
            // Prevent multiple instantiations
            return $this;
        }

        self::$instantiated = true;
        if ($driver instanceof MailerInterface)
            return $this;

        $driver = $driver ?? config('mail.default');
        self::$config = $config ?? config('mail.mailers', [])[$driver];
        self::$config['driver'] = $driver;

        self::setDriver(self::$config['transport']);
    }

    public static function init(string|null $driver = null, array|null $config = null): self
    {
        if (!self::$instantiated) {
            return new static($config, $driver); // Only instantiate if not already instantiated
        }

        return new static;
    }

    protected static function setDriver(string $transport): void
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
        // return new static;
    }

    public static function to(string $address, string|null $name = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->to($address, $name);
        return new static(self::$config, self::$driver);
    }

    public static function replyTo(string $address, string|null $name = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->replyTo($address, $name);
        return new static(self::$config, self::$driver);
    }

    public static function from(string $address, string|null $name = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->from($address, $name ?? '');
        return new static(static::$config, static::$driver);
    }

    /**
     * Set a custom message header.
     *
     * The case this exists for is bulk-mail compliance — Gmail and Yahoo have required
     * `List-Unsubscribe` plus `List-Unsubscribe-Post` on bulk sends since February 2024, and a
     * sender without them is throttled. That matters even for a mostly-transactional domain,
     * because the throttling lands on the domain, not on the campaign:
     *
     *     Mailer::to($subscriber)
     *         ->header('List-Unsubscribe', "<{$oneClickUrl}>, <mailto:unsub@example.com>")
     *         ->header('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click')
     *         ->send('Our July newsletter');
     *
     * Headers are cleared after each send, so one message's cannot leak into the next.
     */
    public static function header(string $name, string $value): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->header($name, $value);
        return new static(self::$config, self::$driver);
    }

    /**
     * Set several headers at once.
     *
     * @param array<string, string> $headers
     */
    public static function headers(array $headers): self
    {
        if (!self::$instantiated) {
            new static;
        }
        self::$driver->headers($headers);
        return new static(self::$config, self::$driver);
    }

    public static function buildHtml(string $templateName, array $data = [], string|null $resourcePath = null): self
    {
        if (!self::$instantiated) {
            new static;
        }
        static::$html = Twig::make($templateName, $resourcePath ?? config('mail.markdown.paths'), $data, true);
        return new static(static::$config, static::$driver);
    }

    public static function send($subject, $to = null): MailerResponse
    {
        if (!self::$instantiated) {
            new static;
        }

        if ($to) {
            self::to($to);
        }

        return static::$driver->send($subject, static::$html);
    }
}
