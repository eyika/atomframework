<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Hashing\Hasher;
use PHPUnit\Framework\TestCase;

/**
 * Covers SEC-27. The framework verified passwords (password_verify() in the auth drivers) but
 * shipped no way to produce a hash, so every app called password_hash() itself and chose its own
 * algorithm and cost. Hasher wraps PHP's password API behind config('hashing').
 */
class HasherTest extends TestCase
{
    public function test_a_hash_verifies_against_its_plaintext(): void
    {
        $hasher = new Hasher([]);
        $hash = $hasher->make('correct horse battery staple');

        $this->assertTrue($hasher->check('correct horse battery staple', $hash));
        $this->assertFalse($hasher->check('wrong password', $hash));
    }

    public function test_hashes_are_salted_so_the_same_input_differs_each_time(): void
    {
        $hasher = new Hasher([]);

        $this->assertNotSame($hasher->make('same'), $hasher->make('same'));
    }

    public function test_the_hash_is_not_the_plaintext(): void
    {
        $hasher = new Hasher([]);

        $this->assertStringNotContainsString('secret', $hasher->make('secret'));
    }

    /** A row with no password must never be satisfiable, including by an empty input. */
    public function test_an_empty_or_null_hash_never_verifies(): void
    {
        $hasher = new Hasher([]);

        $this->assertFalse($hasher->check('', ''));
        $this->assertFalse($hasher->check('anything', ''));
        $this->assertFalse($hasher->check('anything', null));
    }

    public function test_bcrypt_is_the_default_driver(): void
    {
        $hasher = new Hasher([]);
        $hash   = $hasher->make('x');

        $this->assertSame('bcrypt', $hasher->info($hash)['algoName']);
        $this->assertStringStartsWith('$2y$', $hash, 'bcrypt crypt identifier');
    }

    public function test_the_configured_cost_is_applied(): void
    {
        $hasher = new Hasher(['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 5]]);

        $info = $hasher->info($hasher->make('x'));

        $this->assertSame(5, $info['options']['cost']);
    }

    public function test_needs_rehash_detects_a_changed_cost(): void
    {
        $cheap = new Hasher(['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 5]]);
        $hash  = $cheap->make('x');

        $this->assertFalse($cheap->needsRehash($hash), 'same parameters — no rehash needed');

        $stronger = new Hasher(['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 6]]);
        $this->assertTrue($stronger->needsRehash($hash), 'cost raised — should want a rehash');
    }

    public function test_a_rehashed_password_still_verifies(): void
    {
        $cheap    = new Hasher(['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 5]]);
        $stronger = new Hasher(['driver' => 'bcrypt', 'bcrypt' => ['rounds' => 6]]);

        $old = $cheap->make('pass');
        $this->assertTrue($stronger->check('pass', $old), 'an older hash must still verify');

        $new = $stronger->make('pass');
        $this->assertTrue($stronger->check('pass', $new));
        $this->assertFalse($stronger->needsRehash($new));
    }

    public function test_an_unsupported_driver_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported hashing driver');

        (new Hasher(['driver' => 'md5']))->make('x');
    }

    public function test_info_returns_null_for_a_non_hash(): void
    {
        $this->assertNull((new Hasher([]))->info('not-a-hash'));
    }

    /** Hashing must not depend on APP_KEY — rotating the key cannot invalidate passwords. */
    public function test_hashing_is_independent_of_the_application_key(): void
    {
        $hasher = new Hasher([]);

        $original = $_ENV['APP_KEY'] ?? null;
        $hash = $hasher->make('pass');

        $_ENV['APP_KEY'] = 'a-completely-different-key-000000';

        try {
            $this->assertTrue($hasher->check('pass', $hash));
        } finally {
            if ($original === null) {
                unset($_ENV['APP_KEY']);
            } else {
                $_ENV['APP_KEY'] = $original;
            }
        }
    }
}
