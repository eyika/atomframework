<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use InvalidArgumentException;

/**
 * Resolves a driver name (as found in `config('database.default')`) to its {@see Grammar}.
 */
class GrammarFactory
{
    public static function make(string $driver): Grammar
    {
        return match ($driver) {
            'mysql', 'mariadb'                 => new MySqlGrammar(),
            'sqlite'                           => new SqliteGrammar(),
            'pgsql', 'postgres', 'postgresql'  => new PostgresGrammar(),
            default => throw new InvalidArgumentException(
                "No SQL grammar registered for database driver [{$driver}]."
            ),
        };
    }
}
