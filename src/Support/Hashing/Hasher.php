<?php

namespace Eyika\Atom\Framework\Support\Hashing;

use InvalidArgumentException;
use RuntimeException;

/**
 * Password hashing (SEC-27).
 *
 * The framework verified passwords with `password_verify()` in its auth drivers but shipped no
 * way to *produce* a hash, leaving every app to call `password_hash()` itself — and to decide,
 * separately, which algorithm and cost to use. This wraps PHP's password API so the choice lives
 * in `config('hashing')`.
 *
 * Hashing is deliberately NOT encryption: a hash is one-way and unaffected by `APP_KEY`, so
 * rotating the application key never invalidates stored passwords.
 */
class Hasher
{
    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param array<string, mixed>|null $config Defaults to config('hashing') when available.
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (function_exists('config') ? (array) config('hashing', []) : []);
    }

    /**
     * Hash a plaintext value. Returns a self-describing crypt string that records the algorithm
     * and its parameters, so `check()` needs no configuration to verify an older hash.
     *
     * @param array<string, mixed> $options Per-call overrides of the configured options.
     */
    public function make(string $value, array $options = []): string
    {
        $algo = $this->algorithm($options['driver'] ?? null);

        $hash = password_hash($value, $algo, $this->options($algo, $options));

        // Only on a genuinely broken configuration (unknown algorithm, impossible cost). Failing
        // closed matters here: returning false would otherwise be stored as an empty password.
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Could not hash the value; check the hashing configuration.');
        }

        return $hash;
    }

    /**
     * Verify a plaintext value against a hash, in constant time.
     *
     * An empty hash returns false rather than deferring to password_verify(), so a row with no
     * password set can never be satisfied by some empty input.
     */
    public function check(string $value, ?string $hashedValue): bool
    {
        if ($hashedValue === null || $hashedValue === '') {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Whether $hashedValue was produced with different parameters than are configured now — i.e.
     * it should be re-hashed. The usual place to act on this is straight after a successful
     * check(), where the plaintext is in hand:
     *
     *     if ($hash->check($plain, $user->password) && $hash->needsRehash($user->password)) {
     *         $user->update(['password' => $hash->make($plain)]);
     *     }
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        $algo = $this->algorithm($options['driver'] ?? null);

        return password_needs_rehash($hashedValue, $algo, $this->options($algo, $options));
    }

    /** Algorithm/parameters a hash was produced with, or null if it is not a recognised hash. */
    public function info(string $hashedValue): ?array
    {
        $info = password_get_info($hashedValue);

        return ($info['algo'] ?? null) ? $info : null;
    }

    /** Resolve the configured driver name to a PHP password_* algorithm constant. */
    protected function algorithm(?string $driver = null): string
    {
        $driver ??= $this->config['driver'] ?? 'bcrypt';

        return match ($driver) {
            'bcrypt'   => PASSWORD_BCRYPT,
            'argon',
            'argon2i'  => PASSWORD_ARGON2I,
            'argon2id' => PASSWORD_ARGON2ID,
            default    => throw new InvalidArgumentException("Unsupported hashing driver [{$driver}]."),
        };
    }

    /**
     * Options for the chosen algorithm. Anything not configured is left out so PHP's own
     * defaults apply, which keeps the framework from pinning a cost that ages badly.
     *
     * @return array<string, int>
     */
    protected function options(string $algo, array $options = []): array
    {
        if ($algo === PASSWORD_BCRYPT) {
            $rounds = $options['rounds'] ?? $this->config['bcrypt']['rounds'] ?? null;

            return $rounds === null ? [] : ['cost' => (int) $rounds];
        }

        $argon = $this->config['argon'] ?? [];

        return array_filter([
            'memory_cost' => (int) ($options['memory'] ?? $argon['memory'] ?? 0),
            'time_cost'   => (int) ($options['time'] ?? $argon['time'] ?? 0),
            'threads'     => (int) ($options['threads'] ?? $argon['threads'] ?? 0),
        ]);
    }
}
