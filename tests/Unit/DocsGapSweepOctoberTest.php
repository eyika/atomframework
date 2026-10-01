<?php

namespace Eyika\Atom\Framework\Tests\Unit;

use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Database\Connection;
use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Mail\Contracts\MailerResponse;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * A sweep of the still-open entries in a downstream consumer's docs-gap log, each re-validated
 * against the framework before being touched. Every one of them reproduced.
 *
 * They share a shape worth naming: **none of them produced an error where the mistake was made.**
 * A cookie set in a controller emptied the response inside `send()`; a `?` in a predicate surfaced
 * as a schema error; a refused email returned a truthy object; configuration injected by a
 * container read back as its default. The common fix is not cleverness, it is making the failure
 * arrive somewhere a person is looking.
 */
class DocsGapSweepOctoberTest extends TestCase
{
    // ---------------------------------------------------------------- a body a middleware can read

    /**
     * `status()` gained `getStatusCode()` and `body` gained nothing, so a middleware wanting to
     * post-process a payload had to reflect into the response on every request.
     */
    public function test_the_response_body_can_be_read_back(): void
    {
        $this->assertSame('{"ok":true}', (new Response())->body('{"ok":true}')->getBody());
    }

    public function test_an_unset_body_reads_as_an_empty_string(): void
    {
        $this->assertSame('', (new Response())->getBody());
    }

    // ---------------------------------------------------------------- whereRaw placeholder style

    /**
     * A positional `?` bound nothing, and PDO then reported *column index out of range* — which
     * reads as a schema problem and sent the reporter to their migrations.
     */
    public function test_a_positional_binding_is_refused_by_name(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/NAMED bindings/');

        DB::table('shops')->whereRaw("REPLACE(slug, '-', '') = ?", ['acme']);
    }

    public function test_the_refusal_says_which_style_to_use(): void
    {
        try {
            DB::table('shops')->whereRaw("slug = ?", ['acme']);
            $this->fail('a positional binding was accepted');
        } catch (Exception $e) {
            $this->assertStringContainsString(':name', $e->getMessage(), 'the message does not show the right style');
        }
    }

    /** A numerically-keyed array is the same mistake spelled differently. */
    public function test_a_numerically_keyed_binding_array_is_refused(): void
    {
        $this->expectException(Exception::class);

        Connection::assertNamedRawBindings('slug = :slug', [0 => 'acme']);
    }

    public function test_named_bindings_are_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        Connection::assertNamedRawBindings("REPLACE(slug, '-', '') = :normalised", ['normalised' => 'acme']);
    }

    /** A bare `?` with no bindings may be a literal inside a string, so it is left alone. */
    public function test_a_question_mark_without_bindings_is_not_second_guessed(): void
    {
        $this->expectNotToPerformAssertions();

        Connection::assertNamedRawBindings("note LIKE '%?%'", []);
    }

    // ---------------------------------------------------------------- the mail result

    /**
     * `if ($mailer->send($subject))` is **always true** — it returns an object, and every object is
     * truthy. PHP has no `__toBool`, so the class cannot protect that caller; a method that says
     * what it means can.
     */
    public function test_a_failed_send_reports_itself(): void
    {
        $response = new MailerResponse(false, null, 'Mailbox unavailable');

        $this->assertTrue($response->failed());
        $this->assertFalse($response->successful());
        $this->assertSame('Mailbox unavailable', $response->error);

        // The shape of the trap, asserted so it stays visible.
        $this->assertTrue((bool) $response, 'a MailerResponse is truthy even when it failed');
    }

    public function test_a_successful_send_reports_itself(): void
    {
        $response = new MailerResponse(true, '<abc@example.test>');

        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
    }

    /** The throwing behaviour a caller expected from their now-dead try/catch. */
    public function test_throw_if_failed_raises_with_the_providers_reason(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Mailbox unavailable');

        (new MailerResponse(false, null, 'Mailbox unavailable'))->throwIfFailed();
    }

    public function test_throw_if_failed_is_chainable_on_success(): void
    {
        $response = new MailerResponse(true, '<abc@example.test>');

        $this->assertSame($response, $response->throwIfFailed());
    }

    /** It rethrows the driver's own exception when there is one, so the trace survives. */
    public function test_throw_if_failed_preserves_the_original_exception(): void
    {
        $original = new Exception('550 relay denied');

        try {
            (new MailerResponse(false, null, '550 relay denied', $original))->throwIfFailed();
            $this->fail('a failed response did not raise');
        } catch (Exception $e) {
            $this->assertSame($original, $e);
        }
    }
}
