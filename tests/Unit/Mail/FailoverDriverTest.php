<?php

namespace Eyika\Atom\Framework\Tests\Unit\Mail;

use Eyika\Atom\Framework\Support\Mail\Contracts\MailerInterface;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use Eyika\Atom\Framework\Support\Mail\Drivers\FailoverDriver;
use PHPUnit\Framework\TestCase;

/**
 * `FailoverDriver` caught `Exception`, so a driver failing with an `Error` — a `TypeError` from a
 * malformed config value, a missing SDK class — escaped the loop and killed the send outright
 * instead of trying the next mailer. That is precisely the case failover exists for, so the driver
 * was least reliable exactly when it was most needed.
 *
 * Found while sweeping `catch (Exception)` sites after Claude A raised the Exception-vs-Error
 * distinction on their own code.
 */
class FailoverDriverTest extends TestCase
{
    private function driver(array $mailers): FailoverDriver
    {
        return new FailoverDriver(['mailers' => $mailers]);
    }

    public function test_it_fails_over_when_a_driver_throws_an_exception(): void
    {
        $response = $this->driver([ThrowsExceptionMailer::class, SucceedingMailer::class])
            ->to('someone@example.com')
            ->send('subject', 'body');

        $this->assertTrue($response->success);
        $this->assertSame('sent-by-fallback', $response->message_id);
    }

    /** The regression: an Error is not an Exception, and must not abort the failover chain. */
    public function test_it_fails_over_when_a_driver_throws_an_error(): void
    {
        $response = $this->driver([ThrowsErrorMailer::class, SucceedingMailer::class])
            ->to('someone@example.com')
            ->send('subject', 'body');

        $this->assertTrue(
            $response->success,
            'a driver throwing an Error aborted the chain instead of failing over'
        );
        $this->assertSame('sent-by-fallback', $response->message_id);
    }

    /** A driver whose constructor blows up (bad config) must also be skipped. */
    public function test_it_fails_over_when_a_driver_cannot_even_be_constructed(): void
    {
        $response = $this->driver([FatalConstructorMailer::class, SucceedingMailer::class])
            ->to('someone@example.com')
            ->send('subject', 'body');

        $this->assertTrue($response->success);
    }

    public function test_it_reports_failure_when_every_driver_fails(): void
    {
        $response = $this->driver([ThrowsErrorMailer::class, ThrowsExceptionMailer::class])
            ->to('someone@example.com')
            ->send('subject', 'body');

        $this->assertFalse($response->success);
        $this->assertSame('All failover mailers failed', $response->error);
    }

    public function test_the_first_working_driver_wins_and_later_ones_are_not_tried(): void
    {
        SucceedingMailer::$sends = 0;

        $this->driver([SucceedingMailer::class, SucceedingMailer::class])
            ->to('someone@example.com')
            ->send('subject', 'body');

        $this->assertSame(1, SucceedingMailer::$sends);
    }
}

abstract class FakeMailer implements MailerInterface
{
    use \Eyika\Atom\Framework\Support\Mail\Concerns\CollectsCustomHeaders;

    public function __construct(protected array $config = [])
    {
    }

    public function to(string $address, string|null $name = null): self
    {
        return $this;
    }

    public function from(string $address, string $name): self
    {
        return $this;
    }

    public function replyTo(string $address, string|null $name = null): self
    {
        return $this;
    }
}

class SucceedingMailer extends FakeMailer
{
    public static int $sends = 0;

    public function send($subject, $body): MailerResponse
    {
        self::$sends++;

        return new MailerResponse(true, 'sent-by-fallback', 'ok');
    }
}

class ThrowsExceptionMailer extends FakeMailer
{
    public function send($subject, $body): MailerResponse
    {
        throw new \RuntimeException('transport refused the connection');
    }
}

class ThrowsErrorMailer extends FakeMailer
{
    public function send($subject, $body): MailerResponse
    {
        // e.g. a config value of the wrong type reaching an SDK call.
        throw new \TypeError('Argument #1 must be of type string, array given');
    }
}

class FatalConstructorMailer extends FakeMailer
{
    public function __construct(protected array $config = [])
    {
        throw new \Error('missing SDK class');
    }

    public function send($subject, $body): MailerResponse
    {
        return new MailerResponse(false, null, 'never reached');
    }
}
