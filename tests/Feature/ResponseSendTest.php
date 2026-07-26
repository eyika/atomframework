<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Response;

/**
 * Covers BUG-07: send() must be idempotent — a second call is a no-op rather than
 * re-emitting the body/headers.
 */
class ResponseSendTest extends IntegrationTestCase
{
    public function test_send_is_idempotent(): void
    {
        $this->bindRequest('GET', '/'); // HTML request → default body branch

        $response = (new Response())->body('hello');

        ob_start();
        $response->send();
        $response->send(); // must not echo a second time
        $output = ob_get_clean();

        $this->assertSame('hello', $output);
    }
}
