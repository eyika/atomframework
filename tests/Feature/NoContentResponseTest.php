<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\JsonResponse;
use Eyika\Atom\Framework\Http\Response;

/**
 * Reported by a downstream consumer: `noContent()` emitted a 204 and then wrote two more bytes.
 *
 *     HTTP/1.1 204 No Content
 *     Content-Type: application/json
 *                       <-- headers end here; nothing may follow
 *     []                <-- but 2 bytes did
 *
 * 204, 304 and 1xx promise that nothing follows the headers (RFC 9110 §15). A client reading a 204
 * stops there, so trailing bytes are the start of what it believes is the next message. `curl`
 * shrugs and shows a healthy 204; Node's HTTP parser rejects the exchange with "Parse Error: Data
 * after 'Connection: close'" — so a Node proxy in front of the API turned a clean 204 into a 500,
 * with the two tools disagreeing about the same response.
 *
 * The cause was `create()`: `noContent()` passes no data, but `Arr::wrap(null)` yields `[]` and
 * `json_encode([])` is the string "[]".
 */
class NoContentResponseTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bindRequest('GET', '/');
        BaseResponse::captureOutput(true);
    }

    protected function tearDown(): void
    {
        BaseResponse::captureOutput(false);
        parent::tearDown();
    }

    private function send(BaseResponse $response): BaseResponse
    {
        ob_start();
        $response->send();
        $echoed = ob_get_clean();

        $this->assertSame('', $echoed, 'capture mode should route output through the object');

        return $response;
    }

    /** The reported defect: not one byte after the headers. */
    public function test_no_content_writes_an_empty_body(): void
    {
        $response = $this->send((new JsonResponse())->noContent());

        $this->assertSame(204, $response->sentStatus());
        $this->assertSame('', $response->sentBody());
    }

    public function test_no_content_specifically_does_not_write_the_two_byte_json_array(): void
    {
        $this->assertNotSame('[]', $this->send((new JsonResponse())->noContent())->sentBody());
    }

    /** Content headers describe content that by definition is not coming. */
    public function test_no_content_sends_no_content_type_or_content_length(): void
    {
        $response = $this->send((new JsonResponse())->noContent());

        foreach ($response->sentHeaders() as $key => $value) {
            $line = is_string($key) ? "$key: " . (is_array($value) ? '' : $value) : (string) $value;
            $this->assertStringNotContainsStringIgnoringCase('content-type', $line);
            $this->assertStringNotContainsStringIgnoringCase('content-length', $line);
        }
    }

    /**
     * Enforced at the emission boundary, not just inside noContent() — a handler that sets the
     * status itself and a body must still obey the rule.
     */
    public function test_a_manually_set_204_drops_any_body_it_was_given(): void
    {
        $response = $this->send((new Response())->status(204)->body('should not be sent'));

        $this->assertSame(204, $response->sentStatus());
        $this->assertSame('', $response->sentBody());
    }

    /** 304 carries no content either, and is reached by conditional-request handling. */
    public function test_not_modified_writes_an_empty_body(): void
    {
        $response = $this->send((new Response())->status(304)->body('cached'));

        $this->assertSame(304, $response->sentStatus());
        $this->assertSame('', $response->sentBody());
    }

    public function test_informational_statuses_write_an_empty_body(): void
    {
        $response = $this->send((new Response())->status(100)->body('continue'));

        $this->assertSame('', $response->sentBody());
    }

    /** json() is a documented way to reach 204; it must not reintroduce the body. */
    public function test_json_with_a_204_status_writes_no_body(): void
    {
        $response = $this->send((new Response())->json(['ignored' => true], 204));

        $this->assertSame(204, $response->sentStatus());
        $this->assertSame('', $response->sentBody());
    }

    // ---------------------------------------------------------------- no collateral damage

    public function test_a_200_still_carries_its_body(): void
    {
        $response = $this->send((new JsonResponse())->ok('fine', ['a' => 1]));

        $this->assertSame(200, $response->sentStatus());
        $this->assertStringContainsString('fine', $response->sentBody());
    }

    /** 205 Reset Content is adjacent to 204 but is NOT in the bodyless set here. */
    public function test_an_adjacent_status_is_unaffected(): void
    {
        $response = $this->send((new Response())->json(['still' => 'here'], 205));

        $this->assertStringContainsString('still', $response->sentBody());
    }

    public function test_an_empty_json_object_is_still_sent_on_a_200(): void
    {
        $response = $this->send((new Response())->json([], 200));

        $this->assertSame('[]', $response->sentBody(), 'a 200 may legitimately carry an empty body');
    }
}
