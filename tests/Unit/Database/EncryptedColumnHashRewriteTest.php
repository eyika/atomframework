<?php

namespace Eyika\Atom\Framework\Tests\Unit\Database;

use Eyika\Atom\Framework\Support\Database\Model;
use PHPUnit\Framework\TestCase;

class HashRewriteProbe extends Model
{
    public $table = 'users';
    public $softdeletes = false;

    protected const fillable = ['id', 'email', 'phone', 'name'];
    protected const encrypted = ['email', 'phone', 'bvn'];
    protected const ignore_hash_replica = ['bvn'];
}

/**
 * Unit coverage for the encrypted-column -> hash-replica query rewrite
 * (ModelHelpers::useHashForEncryptedColumnComparisonQueries).
 *
 * The rewrite is what keeps an encrypted column queryable by value: the ciphertext can never be
 * compared, so an equality filter on the column is redirected onto its `<col>_hash` HMAC replica.
 *
 * The bug this pins: where() runs its column through _parseColumn(), which qualifies an own-table
 * column to `table.column`, while the `encrypted` list holds BARE names. The rewrite used to match
 * the key against the bare list verbatim, so a qualified key never matched and the filter stayed on
 * the encrypted column — comparing ciphertext against a plaintext value and matching nothing. This
 * is DB-free: it exercises the key reduction directly, so it runs without MySQL.
 */
class EncryptedColumnHashRewriteTest extends TestCase
{
    private bool $hadAppKey;
    private string $previousAppKey;

    protected function setUp(): void
    {
        parent::setUp();
        // getHash() falls back to env('APP_KEY'); pin it so the expected HMAC is deterministic and
        // we never pass null to hash_hmac. env() reads $_ENV only (framework gotcha). Save/restore
        // the ambient value — the suite shares $_ENV, and clobbering APP_KEY breaks later tests.
        $this->hadAppKey = array_key_exists('APP_KEY', $_ENV);
        $this->previousAppKey = $_ENV['APP_KEY'] ?? '';
        $_ENV['APP_KEY'] = 'unit-test-hmac-key';
    }

    protected function tearDown(): void
    {
        if ($this->hadAppKey) {
            $_ENV['APP_KEY'] = $this->previousAppKey;
        } else {
            unset($_ENV['APP_KEY']);
        }
        parent::tearDown();
    }

    private function hash(string $plain): string
    {
        return getHash($plain, 'sha256', $_ENV['APP_KEY']);
    }

    /** The reported bug: a where()-qualified key must be rewritten, keeping the table qualifier. */
    public function test_qualified_key_from_where_is_rewritten(): void
    {
        $q = ['users.email' => 'a@b.com'];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertArrayNotHasKey('users.email', $q);
        $this->assertArrayHasKey('users.email_hash', $q, 'qualified encrypted key must rewrite to its replica');
        $this->assertSame($this->hash('a@b.com'), $q['users.email_hash']);
    }

    /** Regression: the bare-key path (findBy/findByArray) must keep working exactly as before. */
    public function test_bare_key_is_still_rewritten(): void
    {
        $q = ['email' => 'a@b.com'];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertArrayNotHasKey('email', $q);
        $this->assertArrayHasKey('email_hash', $q);
        $this->assertSame($this->hash('a@b.com'), $q['email_hash']);
    }

    /** A repeated column carries a __dupN marker from __where(); it must survive the rewrite. */
    public function test_dup_marker_is_preserved(): void
    {
        $q = ['users.email' => 'a@b.com', 'users.email__dup1' => 'c@d.com'];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertSame(
            ['users.email_hash', 'users.email_hash__dup1'],
            array_keys($q),
            'both the qualifier and the __dupN marker must be preserved so SQL + bind names stay unique'
        );
        $this->assertSame($this->hash('a@b.com'), $q['users.email_hash']);
        $this->assertSame($this->hash('c@d.com'), $q['users.email_hash__dup1']);
    }

    /** A non-encrypted column must be left completely untouched, qualified or not. */
    public function test_non_encrypted_column_is_untouched(): void
    {
        $q = ['users.name' => 'Ada', 'name' => 'Ada'];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertSame(['users.name' => 'Ada', 'name' => 'Ada'], $q);
    }

    /** A column listed in ignore_hash_replica is encrypted but has no replica — never rewrite it. */
    public function test_ignore_hash_replica_column_is_not_rewritten(): void
    {
        $q = ['users.bvn' => '12345678901'];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertSame(['users.bvn' => '12345678901'], $q);
    }

    /** Mixed bag: encrypted + ignored + plain, qualified, in one filter. */
    public function test_mixed_filter_rewrites_only_the_hashable_columns(): void
    {
        $q = [
            'users.email' => 'a@b.com',
            'users.phone' => '08030000000',
            'users.bvn'   => '12345678901',
            'users.id'    => 7,
        ];
        (new HashRewriteProbe())->useHashForEncryptedColumnComparisonQueries($q);

        $this->assertSame(
            ['users.email_hash', 'users.phone_hash', 'users.bvn', 'users.id'],
            array_keys($q)
        );
        $this->assertSame($this->hash('a@b.com'), $q['users.email_hash']);
        $this->assertSame($this->hash('08030000000'), $q['users.phone_hash']);
        $this->assertSame('12345678901', $q['users.bvn']);
        $this->assertSame(7, $q['users.id']);
    }
}
