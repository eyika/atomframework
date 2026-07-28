<?php

namespace Eyika\Atom\Framework\Tests\Unit\Octane;

use Eyika\Atom\Octane\Http\HttpMessage;
use PHPUnit\Framework\TestCase;

// atom-octane is a sibling repo; pull in the class under test (framework-autoloaded deps only).
require_once dirname(__DIR__, 4) . '/atom-octane/src/Http/HttpMessage.php';

/**
 * Covers the native server's HTTP/1.1 codec: request parsing (method/uri/query/cookies +
 * keep-alive semantics per HTTP version) and response building (status line, auto
 * Content-Length + Connection). Sockets aren't involved, so it's pure + deterministic.
 */
class HttpMessageTest extends TestCase
{
    public function test_parses_a_request_into_a_source_array(): void
    {
        $raw = "POST /submit?ref=1&x=2 HTTP/1.1\r\n"
            . "Host: example.test\r\n"
            . "Content-Type: application/x-www-form-urlencoded\r\n"
            . "Cookie: sid=abc; theme=dark\r\n"
            . "Content-Length: 7\r\n\r\n"
            . "a=1&b=2";

        $parsed = HttpMessage::parse($raw);
        $src = $parsed['source'];

        $this->assertSame('POST', $src['server']['REQUEST_METHOD']);
        $this->assertSame('/submit?ref=1&x=2', $src['server']['REQUEST_URI']);
        $this->assertSame('example.test', $src['server']['HTTP_HOST']);
        $this->assertSame(['ref' => '1', 'x' => '2'], $src['query']);
        $this->assertSame(['a' => '1', 'b' => '2'], $src['post']);
        $this->assertSame('abc', $src['cookies']['sid']);
        $this->assertSame('dark', $src['cookies']['theme']);
        $this->assertSame('a=1&b=2', $src['rawBody']);
        $this->assertSame('1.1', $parsed['version']);
    }

    public function test_keep_alive_semantics_per_http_version(): void
    {
        // 1.1 defaults to keep-alive …
        $this->assertTrue(HttpMessage::parse("GET / HTTP/1.1\r\nHost: x\r\n\r\n")['keep_alive']);
        // … unless Connection: close
        $this->assertFalse(HttpMessage::parse("GET / HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n")['keep_alive']);
        // 1.0 defaults to close …
        $this->assertFalse(HttpMessage::parse("GET / HTTP/1.0\r\nHost: x\r\n\r\n")['keep_alive']);
        // … unless Connection: keep-alive
        $this->assertTrue(HttpMessage::parse("GET / HTTP/1.0\r\nHost: x\r\nConnection: keep-alive\r\n\r\n")['keep_alive']);
    }

    public function test_builds_a_response_with_auto_length_and_connection(): void
    {
        $http = HttpMessage::build(['status' => 201, 'headers' => ['Content-Type: application/json'], 'body' => '{"ok":true}'], true);

        $this->assertStringStartsWith("HTTP/1.1 201 Created\r\n", $http);
        $this->assertStringContainsString("Content-Type: application/json\r\n", $http);
        $this->assertStringContainsString("Content-Length: 11\r\n", $http);
        $this->assertStringContainsString("Connection: keep-alive\r\n", $http);
        $this->assertStringEndsWith("\r\n\r\n" . '{"ok":true}', $http);

        $closed = HttpMessage::build(['status' => 500, 'body' => 'x'], false);
        $this->assertStringContainsString("Connection: close\r\n", $closed);
        $this->assertStringStartsWith('HTTP/1.1 500 Internal Server Error', $closed);
    }

    public function test_read_pulls_headers_then_content_length_body_off_a_stream(): void
    {
        $raw = "POST /x HTTP/1.1\r\nHost: h\r\nContent-Length: 5\r\n\r\nhello";
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $this->assertSame($raw, HttpMessage::read($stream));
        fclose($stream);
    }
}
