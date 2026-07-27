<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Response;

/**
 * Covers BUG-07 (send() idempotent) and WRK-02 (response output routed through the
 * object so a worker can CAPTURE status/headers/body instead of emitting them).
 */
class ResponseSendTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        BaseResponse::captureOutput(false);
        parent::tearDown();
    }

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

    public function test_capture_mode_collects_output_instead_of_emitting(): void
    {
        $this->bindRequest('GET', '/');
        BaseResponse::captureOutput(true);

        $response = (new Response())->body('captured-body');

        ob_start();
        $response->send();
        $echoed = ob_get_clean();

        $this->assertSame('', $echoed);                          // nothing emitted
        $this->assertSame('captured-body', $response->sentBody()); // captured on the object
        $this->assertNotNull($response->sentStatus());
    }
}
