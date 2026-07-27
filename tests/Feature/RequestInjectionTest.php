<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Request;
use Eyika\Atom\Framework\Http\Route;

/**
 * Covers WRK-01: the Request is built from an injectable source (default = the PHP
 * superglobals + php://input). Injecting the source lets a worker/test supply request
 * data without touching process globals — and unblocks JSON-body tests, since
 * php://input can't be written under the CLI SAPI.
 */
class RequestInjectionTest extends IntegrationTestCase
{
    public function test_json_body_is_parsed_from_the_injected_source(): void
    {
        Route::post('/json-echo', fn (Request $req) => json_encode($req->input()));

        $res = $this->postJson('/json-echo', ['name' => 'ada', 'nested' => ['x' => 1]]);

        $res->assertBodyContains('ada');
        $res->assertBodyContains('nested');
    }

    public function test_request_reads_from_source_not_superglobals(): void
    {
        // Deliberately different globals — the injected source must win.
        $_GET = ['from' => 'globals'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $req = new Request([
            'server'  => ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/injected'],
            'query'   => ['from' => 'source'],
            'post'    => [],
            'cookies' => [],
            'files'   => [],
            'headers' => ['Content-Type' => 'application/json'],
            'rawBody' => json_encode(['msg' => 'hello']),
        ]);

        $this->assertSame('POST', $req->method());
        $this->assertSame('hello', $req->input()['msg'] ?? null);
        $this->assertSame('{"msg":"hello"}', $req->rawBody());
    }

    public function test_empty_injected_body_does_not_read_php_input(): void
    {
        // rawBody present-but-'' means "empty body", not "read php://input".
        $req = new Request([
            'server'  => ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            'headers' => [],
            'rawBody' => '',
        ]);

        $this->assertSame('', $req->rawBody());
    }
}
