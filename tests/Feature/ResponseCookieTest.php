<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Response;

/**
 * Reported by a downstream consumer, the first time that application ever set a cookie from the
 * server: `setCookie(...)` returned normally and the response then sent as a **200 with an empty
 * body and none of its custom headers**. The same response without that one call sent an 896-byte
 * body and every header intact.
 *
 * `Arrayable::each()` invokes its callback as `($key, $value)` — key first, which is the convention
 * the framework's four other callers already follow — while `sendHeaders()` declared
 * `function (Cookie $cookie)` and was therefore handed the cookie's NAME, a string, where a `Cookie`
 * was required.
 *
 * What made a TypeError into an empty 200 is where it was raised. `_send()` emits status, then
 * headers, then body, in sequence and unguarded; cookies go out **before** every other header. So
 * the throw escaped after the controller's own try/catch had already returned, and took the
 * remaining headers and the entire body with it. Nothing upstream had a chance to notice, and what
 * reached the browser was a plausible 200 with nothing in it.
 *
 * That the application had never set a server-side cookie before is why it survived so long:
 * `EncryptCookies` was the only other caller, and it only rewrites cookies that are already there.
 */
class ResponseCookieTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $this->app->instance('request', new Request());

        BaseResponse::captureOutput(true);
        BaseResponse::resetCapture();
    }

    protected function tearDown(): void
    {
        BaseResponse::resetCapture();
        BaseResponse::captureOutput(false);

        parent::tearDown();
    }

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

    /** The report, in one test: a cookie must not cost you the response. */
    public function test_setting_a_cookie_does_not_empty_the_response(): void
    {
        $response = $this->send(
            (new Response())
                ->setCookie('cart_token', 'abc123', time() + 2592000, '/', '', false, false, 'Lax')
                ->setHeader('X-Custom', 'kept')
                ->body('a real body')
                ->status(BaseResponse::STATUS_OK)
        );

        $this->assertSame(BaseResponse::STATUS_OK, $response->sentStatus());
        $this->assertSame('a real body', $response->sentBody(), 'the body was lost');

        $headers = implode("\n", $response->sentHeaders());
        $this->assertStringContainsString('Set-Cookie: cart_token=abc123', $headers, 'the cookie was not sent');
        $this->assertStringContainsString('X-Custom: kept', $headers, 'a header after the cookie was lost');
    }

    public function test_the_cookies_attributes_survive(): void
    {
        $response = $this->send(
            (new Response())
                ->setCookie('session', 'xyz', time() + 3600, '/app', '', true, true, 'Strict')
                ->body('x')
        );

        $cookie = '';
        foreach ($response->sentHeaders() as $header) {
            if (str_starts_with($header, 'Set-Cookie:')) {
                $cookie = $header;
            }
        }

        $this->assertStringContainsString('Path=/app', $cookie);
        $this->assertStringContainsString('Secure', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('SameSite=Strict', $cookie);
    }

    /** Each cookie gets its own line — the emitter passes `false` for replace deliberately. */
    public function test_several_cookies_each_get_their_own_header(): void
    {
        $response = $this->send(
            (new Response())->setCookie('one', 'a')->setCookie('two', 'b')->body('x')
        );

        $cookies = array_values(array_filter(
            $response->sentHeaders(),
            fn ($h) => str_starts_with($h, 'Set-Cookie:')
        ));

        $this->assertCount(2, $cookies);
        $this->assertStringContainsString('one=a', $cookies[0]);
        $this->assertStringContainsString('two=b', $cookies[1]);
    }

    /** A response with no cookie is unaffected — the fix must not change the ordinary path. */
    public function test_a_response_without_cookies_still_sends_normally(): void
    {
        $response = $this->send((new Response())->setHeader('X-Plain', '1')->body('untouched'));

        $this->assertSame('untouched', $response->sentBody());
        $this->assertStringContainsString('X-Plain: 1', implode("\n", $response->sentHeaders()));
    }
}
