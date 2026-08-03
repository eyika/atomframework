<?php

namespace Eyika\Atom\Framework\Tests\Unit\Support;

use Eyika\Atom\Framework\Support\Facade\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Reported by Claude A: the `new Encrypter()` fallback in encrypt()/decrypt() was unreachable.
 *
 *     if (!$encrypter = app()->make('encrypter')) { $encrypter = new Encrypter(); }
 *
 * `app()` is Facade::getFacadeApplication(), nullable by design — setFacadeApplication() accepts
 * null so a harness can restore a "none set" state. With no application bound, `app()->make()`
 * fatals with "Call to a member function make() on null" BEFORE the `if` can evaluate, so the
 * fallback could only ever fail in precisely the situation it was written for.
 *
 * It matters because ModelHelpers::encryptValues()/decryptValues() call these globals rather than
 * the Encrypter facade, so any model declaring `const encrypted` was unusable without a booted
 * container — it cost Claude A 13 test errors and forced their DatabaseTestCase to construct a
 * minimal Application purely so 'encrypter' would resolve.
 */
class EncryptHelperTest extends TestCase
{
    private mixed $priorApp = null;

    protected function setUp(): void
    {
        $this->priorApp = Facade::getFacadeApplication();
    }

    protected function tearDown(): void
    {
        Facade::setFacadeApplication($this->priorApp);
        parent::tearDown();
    }

    public function test_encrypt_round_trips_without_a_facade_application(): void
    {
        Facade::setFacadeApplication(null);

        $cipher = encrypt('secret');

        $this->assertNotSame('secret', $cipher);
        $this->assertSame('secret', decrypt($cipher));
    }

    public function test_the_encrypter_helper_falls_back_when_no_application_is_bound(): void
    {
        Facade::setFacadeApplication(null);

        $this->assertInstanceOf(\Eyika\Atom\Framework\Support\Encrypter::class, encrypter());
    }

    public function test_serialized_values_round_trip_without_a_container(): void
    {
        Facade::setFacadeApplication(null);

        $value = ['a' => 1, 'b' => [2, 3]];

        $this->assertSame($value, decrypt(encrypt($value, true), true));
    }

    /**
     * The container instance still wins when one IS bound — the fallback must not shadow it, or
     * an app-configured encrypter would be silently bypassed. Binds its own application rather
     * than depending on whatever the surrounding suite happened to leave set, so it never skips.
     */
    /**
     * Claude A's second point: because ModelHelpers calls these helpers rather than the facade,
     * `Encrypter::swap()` could not substitute the encrypter used for model-level encryption
     * whenever no application was bound — swap() only fills the facade's own resolved cache in
     * that case. Resolving through the facade makes the swap authoritative.
     */
    public function test_a_swapped_encrypter_is_honoured_without_an_application(): void
    {
        Facade::setFacadeApplication(null);

        $sentinel = new \Eyika\Atom\Framework\Support\Encrypter(str_repeat('z', 32));

        try {
            \Eyika\Atom\Framework\Support\Facade\Encrypter::swap($sentinel);

            $this->assertSame($sentinel, encrypter(), 'a swapped instance must win');

            // And it is genuinely the one doing the work: only the sentinel's key opens this.
            $this->assertSame('payload', $sentinel->decrypt(encrypt('payload')));
        } finally {
            \Eyika\Atom\Framework\Support\Facade\Encrypter::clearResolvedInstance('encrypter');
        }
    }

    public function test_a_bound_application_still_supplies_the_encrypter(): void
    {
        $app = new \Eyika\Atom\Framework\Foundation\Application($GLOBALS['base_path'], true);

        $sentinel = new \Eyika\Atom\Framework\Support\Encrypter(str_repeat('k', 32));
        $app->instance('encrypter', $sentinel);

        Facade::setFacadeApplication($app);

        $this->assertSame($sentinel, encrypter(), 'the container binding must take precedence');
    }
}
