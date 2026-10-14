<?php

namespace Eyika\Atom\Framework\Tests\Unit\Mail;

use Eyika\Atom\Framework\Support\Mail\Drivers\ArrayDriver;
use Eyika\Atom\Framework\Support\Mail\Drivers\SesDriver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Reported by a downstream consumer: there was no way to set a custom message header, so
 * `List-Unsubscribe` could not be sent at all. Gmail and Yahoo have REQUIRED it, together with
 * `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, on bulk mail since February 2024 — an
 * in-body unsubscribe link satisfies a human but not the automated check, and the throttling that
 * follows lands on the sending DOMAIN, so it takes receipts and password resets down with the
 * campaign.
 */
class CustomHeadersTest extends TestCase
{
    private function driver(): ArrayDriver
    {
        // Clear the static outbox between tests.
        $rc = new ReflectionClass(ArrayDriver::class);
        $prop = $rc->getProperty('sentEmails');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        return new ArrayDriver([]);
    }

    /** @return array<string, mixed> */
    private function lastSent(): array
    {
        $rc = new ReflectionClass(ArrayDriver::class);
        $prop = $rc->getProperty('sentEmails');
        $prop->setAccessible(true);
        $sent = $prop->getValue();

        return end($sent) ?: [];
    }

    public function test_a_header_reaches_the_sent_message(): void
    {
        $driver = $this->driver();

        $driver->to('a@example.com')
               ->header('List-Unsubscribe', '<https://example.com/u/abc>')
               ->send('Subject', '<p>body</p>');

        $this->assertSame(
            ['List-Unsubscribe' => '<https://example.com/u/abc>'],
            $this->lastSent()['headers']
        );
    }

    public function test_the_one_click_pair_can_be_set_together(): void
    {
        $driver = $this->driver();

        $driver->to('a@example.com')
               ->headers([
                   'List-Unsubscribe' => '<https://example.com/u/abc>, <mailto:u@example.com>',
                   'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
               ])
               ->send('Subject', 'body');

        $headers = $this->lastSent()['headers'];

        $this->assertArrayHasKey('List-Unsubscribe', $headers);
        $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
    }

    /**
     * The Mailer keeps ONE driver instance for the life of the process, so an uncleared header
     * would ride along on the next message — a campaign's unsubscribe link landing on somebody
     * else's password reset.
     */
    public function test_headers_do_not_leak_into_the_next_send(): void
    {
        $driver = $this->driver();

        $driver->to('a@example.com')->header('List-Unsubscribe', '<https://example.com/u/abc>')
               ->send('Campaign', 'body');

        $driver->to('b@example.com')->send('Password reset', 'body');

        $this->assertSame([], $this->lastSent()['headers'], 'headers must not carry over');
    }

    public function test_setting_the_same_header_twice_replaces_it(): void
    {
        $driver = $this->driver();

        $driver->to('a@example.com')
               ->header('List-Unsubscribe', '<https://example.com/one>')
               ->header('List-Unsubscribe', '<https://example.com/two>')
               ->send('Subject', 'body');

        $this->assertSame(
            ['List-Unsubscribe' => '<https://example.com/two>'],
            $this->lastSent()['headers'],
            'a duplicate List-Unsubscribe is itself a compliance failure'
        );
    }

    /** Header injection: a newline would let a caller append headers or split the body. */
    public function test_a_newline_in_the_value_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not contain CR or LF');

        $this->driver()->header('X-Test', "value\r\nBcc: attacker@example.com");
    }

    public function test_a_newline_in_the_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->driver()->header("X-Test\nBcc", 'value');
    }

    public function test_an_empty_header_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->driver()->header('   ', 'value');
    }

    /**
     * The SES driver runs on the SES v2 `SendEmail` API precisely so it CAN carry headers: v2
     * added `Content.Simple.Message.Headers`, the field v1's SendEmail lacked. A header set on the
     * driver must land in that list as a `{Name, Value}` entry — so `List-Unsubscribe` is actually
     * sent rather than refused (the v1 driver's old behaviour) or silently dropped.
     *
     * Asserted on the request the driver builds, not on a live send, so there is no AWS round-trip.
     */
    public function test_the_ses_driver_carries_a_header_into_the_v2_request(): void
    {
        $params = $this->sesParams(
            headers: ['List-Unsubscribe' => '<https://example.com/u>'],
            subject: 'Subject',
            body: '<p>body</p>'
        );

        $this->assertSame(
            [['Name' => 'List-Unsubscribe', 'Value' => '<https://example.com/u>']],
            $params['Content']['Simple']['Headers'],
            'the header must reach Content.Simple.Message.Headers as a {Name, Value} entry'
        );
    }

    /** The one-click pair both ride in the v2 Headers list. */
    public function test_the_ses_driver_carries_the_one_click_pair(): void
    {
        $params = $this->sesParams(
            headers: [
                'List-Unsubscribe' => '<https://example.com/u>, <mailto:u@example.com>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
            subject: 'Subject',
            body: 'body'
        );

        $names = array_column($params['Content']['Simple']['Headers'], 'Value', 'Name');

        $this->assertArrayHasKey('List-Unsubscribe', $names);
        $this->assertSame('List-Unsubscribe=One-Click', $names['List-Unsubscribe-Post']);
    }

    /** No headers set → no Headers key, so a plain send is left untouched. */
    public function test_the_ses_driver_omits_the_headers_field_when_none_are_set(): void
    {
        $params = $this->sesParams(headers: [], subject: 'Subject', body: 'body');

        $this->assertArrayNotHasKey('Headers', $params['Content']['Simple']);
    }

    /** A configuration set is attached only when configured, and omitted otherwise. */
    public function test_the_ses_driver_attaches_the_configuration_set_when_configured(): void
    {
        $withSet = $this->sesParams(headers: [], subject: 'S', body: 'b', config: [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'from' => 'a@example.com',
            'configuration_set' => 'my-config-set',
        ]);
        $this->assertSame('my-config-set', $withSet['ConfigurationSetName']);

        $withoutSet = $this->sesParams(headers: [], subject: 'S', body: 'b');
        $this->assertArrayNotHasKey('ConfigurationSetName', $withoutSet);
    }

    /**
     * Build an SES v2 request the way the driver would, reaching the protected param-builder by
     * reflection so no AWS client call is made.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $config
     * @return array<string, mixed>
     */
    private function sesParams(array $headers, string $subject, string $body, ?array $config = null): array
    {
        $ses = new SesDriver($config ?? [
            'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'from' => 'a@example.com',
        ]);

        $ses->to('b@example.com');
        if ($headers) {
            $ses->headers($headers);
        }

        $method = (new ReflectionClass(SesDriver::class))->getMethod('buildParams');
        $method->setAccessible(true);

        return $method->invoke($ses, $subject, $body);
    }

    /** Every driver must satisfy the contract, or a transport swap breaks at runtime. */
    public function test_every_driver_implements_the_header_api(): void
    {
        $drivers = [
            \Eyika\Atom\Framework\Support\Mail\Drivers\SmtpDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\SendmailDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\MailgunDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\PostmarkDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\SesDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\LogDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\ArrayDriver::class,
            \Eyika\Atom\Framework\Support\Mail\Drivers\FailoverDriver::class,
        ];

        foreach ($drivers as $driver) {
            $rc = new ReflectionClass($driver);

            foreach (['header', 'headers', 'getCustomHeaders'] as $method) {
                $this->assertTrue($rc->hasMethod($method), "$driver is missing $method()");
            }
        }
    }
}
