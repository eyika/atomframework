<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Middlewares\ServePublicAssets;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;
use Eyika\Atom\Framework\Support\Config;
use Eyika\Atom\Framework\Support\Facade\Facade;
use ReflectionMethod;

/**
 * Records the FULL argument list `sendHeaders()` hands to `emitHeader()`.
 *
 * `func_get_args()` and a variadic tail rather than named parameters, on purpose: `emitHeader()` no
 * longer declares a status-code parameter, and PHP passes extra arguments to a userland method
 * without complaint. So a reintroduced `$this->emitHeader($h, $replace, $code)` would compile, run,
 * and reach `header()` silently — and `func_get_args()` is what still sees it. The variadic also
 * keeps the spy loadable if the parameter ever comes back, so the failure reads as an assertion
 * rather than as a fatal signature mismatch.
 */
class HeaderSpyResponse extends Response
{
    /** @var list<array{header: string, args: array}> */
    public array $emitted = [];

    protected function emitHeader(string $header, bool $replace = true, ...$rest): void
    {
        $this->emitted[] = ['header' => $header, 'args' => func_get_args()];
        parent::emitHeader($header, $replace);
    }
}

/**
 * Reported by a downstream consumer: range replies went out as **200** with every range header
 * beside them correct — right offsets, right suffix semantics, right clamping, right total size.
 *
 * `setHeader($key, $content, $code)` stored the code alongside the header, and `sendHeaders()`
 * replayed it into PHP's `header($h, $replace, $code)`, whose third argument FORCES the response
 * code. `emitStatus(206)` ran first; replaying `Content-Type` — built with `STATUS_OK` — set it
 * straight back. The emitted status was whichever coded header happened to be written last, not
 * what `status()` was given.
 *
 * It is worth being precise about the damage, because it reads as cosmetic and is not: a range
 * reply answered 200 is a COMPLETE representation as far as any cache is concerned, so a 500-byte
 * "whole video" can be stored against that URL and served to everyone after.
 *
 * Two details confirm the mechanism rather than the symptom. 304 was unaffected only because the
 * bodyless branch skips exactly the two headers carrying a stale code; and `plain($body, 404)`
 * landed on 404 by luck, its code baked into the header it emitted.
 *
 * **Why the range tests already here did not catch it.** They assert `getStatusCode()`, the
 * object's own property, which was always right — the divergence appears only at the moment of
 * emission. And `captureOutput` never calls `header()` at all, so the one code path that was broken
 * is the one a captured test does not execute. CLI cannot observe it either: `header()` is inert
 * there, and `http_response_code()` stays put. A test that catches this has to assert on what is
 * HANDED to the emitter, which is what the first test below does.
 *
 * Five of the tests here fail against the pre-fix code: the two on the emitter's contract, the one
 * on where a coded header's status goes, and the two on redirects — which set their status ONLY by
 * baking it into the Location header, so they depended on the very override being removed. That is
 * why `setHeader()` now writes `$statusCode` instead of the third argument simply being dropped.
 *
 * The rest cannot discriminate: `emitStatus()` was always given the right number, and only the
 * header replay that followed took it away. They are here to pin those paths while the mechanism
 * beneath them changed.
 */
class EmittedStatusTest extends IntegrationTestCase
{
    private string $publicDir;
    private array $configSnapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = Config::snapshot();
        Config::set('app.serve_public_assets', true);

        BaseResponse::captureOutput(true);
        BaseResponse::resetCapture();

        $this->publicDir = public_path();
        if (!is_dir($this->publicDir)) {
            @mkdir($this->publicDir, 0777, true);
        }
        file_put_contents($this->publicDir . '/status-probe.mp4', str_repeat('V', 1024));

        // send() consults the request (content negotiation picks the emit branch), and the base
        // class binds only the response side.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->app->instance('request', new Request());
    }

    protected function tearDown(): void
    {
        @unlink($this->publicDir . '/status-probe.mp4');

        BaseResponse::resetCapture();
        BaseResponse::captureOutput(false);
        Config::restore($this->configSnapshot);
        parent::tearDown();
    }

    /**
     * Send, swallowing the body.
     *
     * The buffer is unwound in a `finally` so a throw inside `send()` surfaces as itself rather
     * than as PHPUnit's "did not close its own output buffers", which is what it looked like the
     * first time this ran.
     */
    private function send(BaseResponse $response): BaseResponse
    {
        $level = ob_get_level();
        ob_start();

        try {
            $response->send();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        return $response;
    }

    /**
     * Serve an asset through the middleware, from a FRESH response instance.
     *
     * Both halves matter. Rebinding alone is not enough — the facade caches what it resolved, so a
     * second request would keep answering from the first request's response object, which by then
     * is marked sent and emits nothing at all. That is a real request boundary being drawn here,
     * not tidiness: without it the 304 below reads back the 200 from the call that produced the
     * ETag, and looks like a status bug rather than a stale instance.
     */
    private function serveAsset(string $uri, array $headers = []): BaseResponse
    {
        Facade::clearResolvedInstances();
        $this->app->instance('response', new Response());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';
        unset($_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'], $_SERVER['HTTP_RANGE']);
        foreach ($headers as $name => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return (new ServePublicAssets())->handle(
            new Request(),
            fn ($req) => (new Response())->body('passed through')
        );
    }

    /** @return array<string, string> header name (lower) => value, read off the EMITTED lines */
    private function emittedHeaders(BaseResponse $response): array
    {
        $flat = [];
        foreach ($response->sentHeaders() as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $flat[strtolower(trim($name))] = trim($value);
        }

        return $flat;
    }

    // ---------------------------------------------------------------- the emitter's contract

    /**
     * The invariant that makes the whole class of bug impossible: the status is emitted once, by
     * `emitStatus()`, and nothing that follows may carry one.
     */
    public function test_no_header_is_emitted_carrying_a_status_code(): void
    {
        $response = new HeaderSpyResponse();
        $response->setHeader('Content-Type', 'video/mp4', BaseResponse::STATUS_OK)
            ->setHeader('Content-Range', 'bytes 0-99/1024')
            ->body('x')
            ->status(BaseResponse::STATUS_PARTIAL_CONTENT);

        $this->send($response);

        $this->assertNotEmpty($response->emitted, 'no headers were emitted at all');

        foreach ($response->emitted as $emission) {
            $this->assertCount(
                2,
                $emission['args'],
                "[{$emission['header']}] was emitted with a third argument, which PHP's header() "
                    . 'treats as a status code and which would override emitStatus()'
            );
        }
    }

    /** The seam itself must not offer the foot-gun back. */
    public function test_the_header_emitter_declares_no_status_parameter(): void
    {
        $parameters = (new ReflectionMethod(BaseResponse::class, 'emitHeader'))->getParameters();

        $this->assertCount(2, $parameters, 'emitHeader() grew a parameter; a status code must not be one');
        $this->assertSame(['header', 'replace'], array_map(fn ($p) => $p->getName(), $parameters));
    }

    // ---------------------------------------------------------------- where the code goes instead

    /** A code passed to setHeader() must reach the status THROUGH $statusCode, not around it. */
    public function test_a_code_given_to_set_header_sets_the_status(): void
    {
        $response = (new Response())->setHeader('Location', '/elsewhere', BaseResponse::STATUS_FOUND);

        $this->assertSame(BaseResponse::STATUS_FOUND, $response->getStatusCode());
    }

    /** ...and a later status() still wins, because both now write one field, in call order. */
    public function test_a_later_status_call_wins_over_an_earlier_coded_header(): void
    {
        $response = (new Response())
            ->setHeader('Content-Type', 'video/mp4', BaseResponse::STATUS_OK)
            ->status(BaseResponse::STATUS_PARTIAL_CONTENT);

        $this->assertSame(BaseResponse::STATUS_PARTIAL_CONTENT, $response->getStatusCode());
        $this->assertSame(BaseResponse::STATUS_PARTIAL_CONTENT, $this->send($response)->sentStatus());
    }

    /** A header set without a code leaves the status alone. */
    public function test_an_uncoded_header_does_not_touch_the_status(): void
    {
        $response = (new Response())
            ->status(BaseResponse::STATUS_PARTIAL_CONTENT)
            ->setHeader('Content-Range', 'bytes 0-99/1024');

        $this->assertSame(BaseResponse::STATUS_PARTIAL_CONTENT, $response->getStatusCode());
    }

    // ---------------------------------------------------------------- the reported symptom

    public function test_a_range_reply_emits_206(): void
    {
        $response = $this->send($this->serveAsset('/status-probe.mp4', ['Range' => 'bytes=0-499']));

        $this->assertSame(BaseResponse::STATUS_PARTIAL_CONTENT, $response->sentStatus());
        $this->assertSame('bytes 0-499/1024', $this->emittedHeaders($response)['content-range'] ?? null);
    }

    public function test_a_suffix_range_reply_emits_206(): void
    {
        $response = $this->send($this->serveAsset('/status-probe.mp4', ['Range' => 'bytes=-100']));

        $this->assertSame(BaseResponse::STATUS_PARTIAL_CONTENT, $response->sentStatus());
    }

    public function test_an_unsatisfiable_range_emits_416(): void
    {
        $response = $this->send($this->serveAsset('/status-probe.mp4', ['Range' => 'bytes=99999-']));

        $this->assertSame(BaseResponse::STATUS_RANGE_NOT_SATISFIABLE, $response->sentStatus());
        $this->assertSame('bytes */1024', $this->emittedHeaders($response)['content-range'] ?? null);
    }

    public function test_a_whole_file_reply_still_emits_200(): void
    {
        $response = $this->send($this->serveAsset('/status-probe.mp4'));

        $this->assertSame(BaseResponse::STATUS_OK, $response->sentStatus());
    }

    /** 304 escaped the bug only because the bodyless branch skips the coded headers. Keep it so. */
    public function test_a_not_modified_reply_still_emits_304(): void
    {
        $etag = $this->emittedHeaders($this->send($this->serveAsset('/status-probe.mp4')))['etag'] ?? null;
        $this->assertNotNull($etag, 'no ETag was emitted');

        $response = $this->send($this->serveAsset('/status-probe.mp4', ['If-None-Match' => $etag]));

        $this->assertSame(BaseResponse::STATUS_NOT_MODIFIED, $response->sentStatus());
    }

    // ---------------------------------------------------------------- the paths that worked by luck

    /**
     * `redirect()` set its status ONLY by baking it into the Location header, so it depended
     * entirely on the override being removed here — which is why `setHeader()` now writes
     * `$statusCode` rather than the third argument simply being dropped.
     */
    public function test_a_redirect_emits_its_status(): void
    {
        $response = $this->send((new Response())->redirect('/elsewhere'));

        $this->assertSame(BaseResponse::STATUS_FOUND, $response->sentStatus());
        $this->assertSame('/elsewhere', $this->emittedHeaders($response)['location'] ?? null);
    }

    public function test_a_redirect_with_an_explicit_code_emits_it(): void
    {
        $response = $this->send((new Response())->redirect('/elsewhere', BaseResponse::STATUS_MOVED_PERMANENTLY));

        $this->assertSame(BaseResponse::STATUS_MOVED_PERMANENTLY, $response->sentStatus());
    }

    /** `plain()` landed on the right status by luck; it must still land on it by design. */
    public function test_plain_emits_its_status(): void
    {
        $response = $this->send((new Response())->plain('File Not Found', BaseResponse::STATUS_NOT_FOUND));

        $this->assertSame(BaseResponse::STATUS_NOT_FOUND, $response->sentStatus());
    }
}
