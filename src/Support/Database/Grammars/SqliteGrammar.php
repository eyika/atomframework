<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\ColumnDefinition;
use Eyika\Atom\Framework\Support\Facade\DatabaseConnection;
use PDO;

/**
 * SQLite grammar. Identifiers use standard double-quote quoting (SQLite also accepts backticks,
 * but double quotes are portable). SQLite has no MySQL `INSERT ... SET`, no IF(), and no RAND();
 * it uses column-list VALUES inserts, CASE WHEN, and RANDOM(). Primarily the engine used for
 * fast, disposable application tests (an in-memory `sqlite::memory:` database).
 */
class SqliteGrammar extends Grammar
{
    public function wrapValue(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function random(): string
    {
        return 'RANDOM()';
    }

    public function now(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    public function currentTimestamp(): string
    {
        return 'CURRENT_TIMESTAMP';
    }

    /** SQLite opens a transaction with BEGIN, not MySQL's 'START TRANSACTION'. */
    public function compileBeginTransaction(): string
    {
        return 'BEGIN';
    }

    /** SQLite has no row-level locks — the transaction serializes writes on its own. */
    public function compileForUpdate(): string
    {
        return '';
    }

    public function insertKeyword(bool $ignore): string
    {
        return 'INSERT' . ($ignore ? ' OR IGNORE' : '');
    }

    // === Schema ===========================================================================

    protected function typeMap(): array
    {
        // SQLite uses type affinity, so exact names barely matter — these keep affinities sane.
        return [
            'bigInteger' => 'INTEGER', 'integer' => 'INTEGER', 'string' => 'VARCHAR', 'char' => 'CHAR',
            'text' => 'TEXT', 'tinyText' => 'TEXT', 'mediumText' => 'TEXT', 'longText' => 'TEXT',
            'json' => 'TEXT', 'decimal' => 'NUMERIC', 'timestamp' => 'DATETIME', 'dateTime' => 'DATETIME',
            'geometry' => 'TEXT', 'blob' => 'BLOB', 'tinyBlob' => 'BLOB', 'mediumBlob' => 'BLOB',
            'longBlob' => 'BLOB',
        ];
    }

    protected function indexesInline(): bool
    {
        return false; // secondary indexes are separate CREATE INDEX statements
    }

    protected function autoIncrementSql(ColumnDefinition $c): string
    {
        // Only `INTEGER PRIMARY KEY AUTOINCREMENT` gives a rowid alias auto-increment in SQLite.
        return 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }

    protected function typeBoolean(): string
    {
        return 'INTEGER';
    }

    protected function typeEnum(ColumnDefinition $c): string
    {
        // No native ENUM; a plain text column. (A CHECK constraint could enforce it, but SQLite's
        // type affinity + test usage don't need it.)
        return 'TEXT';
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name = '"
            . str_replace("'", "''", $table) . "'";
    }

    public function compileColumnExists(string $table, string $column): string
    {
        return "SELECT 1 FROM pragma_table_info('" . str_replace("'", "''", $table) . "') WHERE name = '"
            . str_replace("'", "''", $column) . "'";
    }

    /**
     * SQLite ALTER support is per-operation (no comma-joined clauses) and limited: native
     * ADD/RENAME/DROP COLUMN, separate CREATE/DROP INDEX, and a full table-rebuild for any
     * column change() (MODIFY). Adding a FOREIGN KEY via ALTER isn't supported and is skipped.
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $table = $blueprint->getTable();
        $statements = [];
        $changes = $blueprint->getModifyColumns();

        foreach ($blueprint->getColumns() as $col) {
            if ($col instanceof ColumnDefinition && $col->isChange) {
                $changes[] = $col;
            } else {
                $sql = $col instanceof ColumnDefinition ? $this->compileColumn($col) : (string) $col;
                $statements[] = 'ALTER TABLE ' . $this->wrapTable($table) . ' ADD COLUMN ' . $sql;
            }
        }
        foreach ($blueprint->getRenameColumns() as $pair) {
            $statements[] = 'ALTER TABLE ' . $this->wrapTable($table)
                . ' RENAME COLUMN ' . $this->wrapValue($pair['from']) . ' TO ' . $this->wrapValue($pair['to']);
        }
        foreach ($blueprint->getDropColumns() as $col) {
            $statements[] = 'ALTER TABLE ' . $this->wrapTable($table) . ' DROP COLUMN ' . $this->wrapValue($col);
        }
        foreach ($blueprint->getIndexes() as $idx) {
            if (($sql = $this->compileCreateIndex($idx, $table)) !== null) {
                $statements[] = $sql;
            }
        }
        foreach ($blueprint->getDropIndexes() as $entry) {
            if (($entry['kind'] ?? null) === 'PRIMARY') {
                continue; // dropping a primary key needs a full rebuild — out of scope
            }
            if (isset($entry['name'])) {
                $statements[] = 'DROP INDEX IF EXISTS ' . $this->wrapValue($entry['name']);
            }
        }

        if ($changes) {
            array_push($statements, ...$this->rebuildForChanges($table, $changes));
        }
        return $statements;
    }

    /**
     * The SQLite "12-step" table rebuild: recreate the table with the changed column
     * definitions, copy every row across, drop the old table, rename the new one into place,
     * and recreate its indexes. Data-safe and constraint-safe. Introspects the live table via
     * PRAGMA (the connection is open during a migration).
     *
     * @param ColumnDefinition[] $changes
     * @return string[]
     */
    protected function rebuildForChanges(string $table, array $changes): array
    {
        $byName = [];
        foreach ($changes as $c) {
            $byName[$c->name] = $c;
        }

        $info = DatabaseConnection::exec($this->compilePragmaTableInfo($table))->fetchAll(PDO::FETCH_ASSOC);
        if (!$info) {
            throw new \RuntimeException("Cannot rebuild unknown SQLite table [{$table}].");
        }

        $defs = [];
        $copy = [];
        foreach ($info as $col) {
            $name = $col['name'];
            $copy[] = $this->wrapValue($name);
            $defs[] = isset($byName[$name])
                ? $this->compileColumn($byName[$name])
                : $this->reconstructColumn($col);
        }

        $tmp = $table . '__atom_rebuild';
        $cols = implode(', ', $copy);
        $statements = [
            sprintf("CREATE TABLE %s (\n    %s\n)", $this->wrapTable($tmp), implode(",\n    ", $defs)),
            'INSERT INTO ' . $this->wrapTable($tmp) . " ({$cols}) SELECT {$cols} FROM " . $this->wrapTable($table),
            'DROP TABLE ' . $this->wrapTable($table),
            'ALTER TABLE ' . $this->wrapTable($tmp) . ' RENAME TO ' . $this->wrapValue($table),
        ];

        // Recreate the table's own indexes (they were dropped with the old table).
        $indexes = DatabaseConnection::exec(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = '"
            . str_replace("'", "''", $table) . "' AND sql IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($indexes as $idx) {
            $statements[] = $idx['sql'];
        }
        return $statements;
    }

    protected function compilePragmaTableInfo(string $table): string
    {
        return "PRAGMA table_info('" . str_replace("'", "''", $table) . "')";
    }

    /** Rebuild a column definition from PRAGMA table_info for columns that aren't being changed. */
    protected function reconstructColumn(array $info): string
    {
        $def = $this->wrapValue($info['name']) . ' ' . ($info['type'] ?: 'TEXT');
        if ((int) $info['pk'] === 1) {
            // Preserve an integer rowid primary key (best-effort — autoincrement flag is lost).
            $def .= ' PRIMARY KEY';
        }
        if ((int) $info['notnull'] === 1) {
            $def .= ' NOT NULL';
        }
        if ($info['dflt_value'] !== null) {
            $def .= ' DEFAULT ' . $info['dflt_value'];
        }
        return $def;
    }
}
