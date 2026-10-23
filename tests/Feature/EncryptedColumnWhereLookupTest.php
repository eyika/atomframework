<?php

namespace Eyika\Atom\Framework\Tests\Feature;

use Eyika\Atom\Framework\Support\Database\DB;
use Eyika\Atom\Framework\Support\Database\Model;
use Eyika\Atom\Framework\Support\Encrypter;
use Eyika\Atom\Framework\Support\Facade\Encrypter as EncrypterFacade;

class HashReplicaUser extends Model
{
    public $table = 'atomtest_hash_replica_users';
    public $softdeletes = false;

    protected const fillable = ['id', 'name', 'email'];
    protected const encrypted = ['email'];
}

/**
 * End-to-end cover for querying an encrypted column by value through the FULL read path.
 *
 * `email` is stored as ciphertext, with a keyed-HMAC `email_hash` replica written beside it so the
 * column stays queryable. The regression: where('email', …) qualifies the key to `users.email` via
 * _parseColumn(), the replica rewrite matched only the bare `email`, so the filter stayed on the
 * encrypted column and matched NOTHING — which is why every login through the Eloquent auth driver
 * (it does `where('email', …)->first(false)`) failed for a user who plainly existed.
 *
 * This asserts the rewrite now fires for where()-built filters, that the bare-key findBy() path
 * still works, and that the failure is not masked (wrong value → no row), across the read methods an
 * app actually uses — not just the login path.
 */
class EncryptedColumnWhereLookupTest extends DatabaseTestCase
{
    private string $email = 'ada@example.com';
    private string $previousAppKey;
    private bool $hadAppKey;

    protected function setUp(): void
    {
        parent::setUp();

        // The replica hash is keyed by env('APP_KEY') on BOTH write and read; pin it so the value
        // written on create matches the value the lookup hashes against. env() reads $_ENV only,
        // and no .env is loaded during tests.
        $this->hadAppKey = array_key_exists('APP_KEY', $_ENV);
        $this->previousAppKey = $_ENV['APP_KEY'] ?? '';
        $_ENV['APP_KEY'] = 'feature-test-hmac-key';

        // The minimal DatabaseTestCase boot does not bind the Encrypter, so model-level encryption
        // (encrypt()/decrypt() -> encrypter()) has nothing to resolve. Swap in a fixed-key instance;
        // the encryption key is independent of the HMAC replica key above.
        EncrypterFacade::swap(new Encrypter(str_repeat('k', 32)));
    }

    protected function tearDown(): void
    {
        EncrypterFacade::clearResolvedInstance('encrypter');

        if ($this->hadAppKey) {
            $_ENV['APP_KEY'] = $this->previousAppKey;
        } else {
            unset($_ENV['APP_KEY']);
        }

        parent::tearDown();
    }

    protected function createSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_hash_replica_users');
        $this->raw('CREATE TABLE atomtest_hash_replica_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NULL,
            email TEXT NULL,
            email_hash VARCHAR(255) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    protected function dropSchema(): void
    {
        $this->raw('DROP TABLE IF EXISTS atomtest_hash_replica_users');
    }

    private function seedOne(): int
    {
        $created = (new HashReplicaUser())->create(['name' => 'Ada', 'email' => $this->email]);
        $this->assertNotFalse($created, 'seed insert should succeed');
        return (int) $created->id;
    }

    /** The write path must store ciphertext for `email` and a getHash() replica in `email_hash`. */
    public function test_create_stores_ciphertext_and_populates_the_hash_replica(): void
    {
        $id = $this->seedOne();

        $row = DB::table('atomtest_hash_replica_users')->where('id', $id)->first();
        $this->assertIsArray($row);
        $this->assertNotSame($this->email, $row['email'], 'email column must be ciphertext, not plaintext');
        $this->assertSame(
            getHash($this->email, 'sha256', $_ENV['APP_KEY']),
            $row['email_hash'],
            'email_hash must be the keyed HMAC of the plaintext'
        );
    }

    /** The bug: where() on the encrypted column must find the row (this is the auth-driver path). */
    public function test_where_on_encrypted_column_finds_the_row(): void
    {
        $id = $this->seedOne();

        $user = (new HashReplicaUser())->where('email', $this->email)->first(false);

        $this->assertNotNull($user, 'where() on an encrypted column must resolve via the hash replica');
        $this->assertSame($id, (int) $user->id);
        $this->assertSame($this->email, $user->email, 'the row read back must decrypt to the plaintext');
    }

    /** Regression: the direct-assignment path (findBy) kept the bare key and must still work. */
    public function test_findBy_on_encrypted_column_still_works(): void
    {
        $id = $this->seedOne();

        $user = (new HashReplicaUser())->findBy('email', $this->email, false);

        $this->assertNotNull($user);
        $this->assertSame($id, (int) $user->id);
    }

    /** Scope beyond login: firstWhere() and where()->get() go through the same qualified path. */
    public function test_firstWhere_and_get_resolve_the_encrypted_column(): void
    {
        $id = $this->seedOne();

        $byFirstWhere = (new HashReplicaUser())->firstWhere('email', $this->email, is_protected: false);
        $this->assertNotNull($byFirstWhere);
        $this->assertSame($id, (int) $byFirstWhere->id);

        $rows = (new HashReplicaUser())->where('email', $this->email)->get(false);
        $this->assertCount(1, $rows);
    }

    /** The failure must not be masked: a non-matching value returns null, not a stale/false hit. */
    public function test_wrong_value_returns_null(): void
    {
        $this->seedOne();

        $user = (new HashReplicaUser())->where('email', 'nobody@example.com')->first(false);

        $this->assertNull($user);
    }

    /** A plain (non-encrypted) column filtered alongside must be unaffected by the rewrite. */
    public function test_non_encrypted_column_filter_still_works(): void
    {
        $id = $this->seedOne();

        $user = (new HashReplicaUser())->where('name', 'Ada')->first(false);

        $this->assertNotNull($user);
        $this->assertSame($id, (int) $user->id);
    }
}
