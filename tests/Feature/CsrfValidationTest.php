<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Http\Csrf;

/**
 * Covers SEC-07/08/09/10: single session key, correct token source (not the
 * undefined-$token/query-only path), ALL unsafe verbs verified (not POST-only),
 * constant-time comparison, fail-closed when no session token exists.
 */
class CsrfValidationTest extends IntegrationTestCase
{
    public function test_read_methods_are_exempt(): void
    {
        $this->bindSession([]);
        $this->bindRequest('GET');

        $this->assertTrue(Csrf::csrfIsValid());
    }

    public function test_valid_token_via_input_passes(): void
    {
        $this->bindSession(['csrf_token' => 'secrettoken']);
        $this->bindRequest('POST', '/', ['_token' => 'secrettoken']);

        $this->assertTrue(Csrf::csrfIsValid());
    }

    public function test_valid_token_via_header_passes(): void
    {
        $this->bindSession(['csrf_token' => 'secrettoken']);
        $this->bindRequest('POST', '/', [], ['X-CSRF-TOKEN' => 'secrettoken']);

        $this->assertTrue(Csrf::csrfIsValid());
    }

    public function test_wrong_token_fails(): void
    {
        $this->bindSession(['csrf_token' => 'secrettoken']);
        $this->bindRequest('POST', '/', ['_token' => 'nope']);

        $this->assertFalse(Csrf::csrfIsValid());
    }

    public function test_missing_token_fails(): void
    {
        $this->bindSession(['csrf_token' => 'secrettoken']);
        $this->bindRequest('POST', '/');

        $this->assertFalse(Csrf::csrfIsValid());
    }

    public function test_no_session_token_fails_closed(): void
    {
        $this->bindSession([]);
        $this->bindRequest('POST', '/', ['_token' => 'anything']);

        $this->assertFalse(Csrf::csrfIsValid());
    }

    public function test_unsafe_verbs_are_all_verified_not_bypassed(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->bindSession(['csrf_token' => 'tok']);
            $this->bindRequest($method, '/', ['_token' => 'wrong']);

            $this->assertFalse(Csrf::csrfIsValid(), "$method must be CSRF-verified");
        }
    }
}
