<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use InvalidArgumentException;

/**
 * Resolves a driver name (as found in `config('database.default')`) to its {@see Grammar}.
 *
 * Resolution order, so custom dialects (or a replacement for a built-in) can be plugged in the
 * same way a custom auth guard is — via the kernel/a provider, or via config:
 *
 *   1. Programmatic registrations from {@see extend()} (e.g. a service provider's register()).
 *   2. `config('database.grammars')` — a `['<driver>' => Grammar::class]` map.
 *   3. The built-in mysql / sqlite / pgsql grammars.
 */
class GrammarFactory
{
    /**
     * Driver => grammar class-string or factory closure, registered via extend().
     *
     * @var array<string, class-string<Grammar>|callable(): Grammar>
     */
    protected static array $extensions = [];

    /**
     * Register a custom grammar for a driver. Call from a service provider's register()/boot()
     * or the console/HTTP kernel:
     *
     *     GrammarFactory::extend('firebird', FirebirdGrammar::class);
     *     GrammarFactory::extend('pgsql', fn () => new MyCompletePostgresGrammar()); // override a built-in
     *
     * @param class-string<Grammar>|callable(): Grammar $grammar
     */
    public static function extend(string $driver, string|callable $grammar): void
    {
        static::$extensions[$driver] = $grammar;
    }

    /** Forget all programmatic registrations (mainly for tests). */
    public static function flushExtensions(): void
    {
        static::$extensions = [];
    }

    public static function make(string $driver): Grammar
    {
        // 1. Programmatic registrations (kernel / service provider).
        if (isset(static::$extensions[$driver])) {
            $ext = static::$extensions[$driver];
            return static::verify(is_callable($ext) ? $ext() : new $ext(), $driver);
        }

        // 2. Config-registered grammars — mirrors auth's `driver_classes`. Guarded so it never
        //    throws when the config subsystem isn't available (e.g. an isolated unit test).
        if (($configured = static::fromConfig($driver)) !== null) {
            return static::verify(new $configured(), $driver);
        }

        // 3. Built-in drivers.
        return match ($driver) {
            'mysql', 'mariadb'                => new MySqlGrammar(),
            'sqlite'                          => new SqliteGrammar(),
            'pgsql', 'postgres', 'postgresql' => new PostgresGrammar(),
            default => throw new InvalidArgumentException(
                "No SQL grammar registered for database driver [{$driver}]. Register one with "
                . "GrammarFactory::extend('{$driver}', YourGrammar::class) or via config('database.grammars')."
            ),
        };
    }

    /** Look up config('database.grammars')[$driver], tolerating an unbooted config subsystem. */
    protected static function fromConfig(string $driver): ?string
    {
        if (!function_exists('config')) {
            return null;
        }
        try {
            $grammars = config('database.grammars', []);
        } catch (\Throwable) {
            return null;
        }
        return is_array($grammars) ? ($grammars[$driver] ?? null) : null;
    }

    private static function verify(object $grammar, string $driver): Grammar
    {
        if (!$grammar instanceof Grammar) {
            throw new InvalidArgumentException(
                "Grammar registered for driver [{$driver}] must extend " . Grammar::class . "."
            );
        }
        return $grammar;
    }
}
