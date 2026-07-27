<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Http\Response;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers SEC-24: the redirect target must not carry CR/LF into the Location header
 * (response-header injection).
 */
class RedirectTest extends TestCase
{
    private function locationHeader(Response $response): ?string
    {
        $prop = new ReflectionProperty($response, 'headers');
        $prop->setAccessible(true);
        foreach ((array) $prop->getValue($response) as $entry) {
            foreach ($entry as $key => $val) {
                if ($key === 'Location') {
                    return $val[0];
                }
            }
        }
        return null;
    }

    public function test_redirect_strips_crlf_from_location(): void
    {
        $response = new Response();
        $response->redirect("https://ok.example/path\r\nSet-Cookie: pwned=1");

        $location = $this->locationHeader($response);
        $this->assertNotNull($location);
        $this->assertStringNotContainsString("\r", $location);
        $this->assertStringNotContainsString("\n", $location);
    }
}
