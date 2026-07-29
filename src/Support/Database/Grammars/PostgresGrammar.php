<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\ColumnDefinition;

/**
 * PostgreSQL grammar — FOUNDATION ONLY. The query layer (double-quote identifiers, RANDOM(),
 * now(), column-list VALUES inserts, CASE WHEN) and the basic DDL type map are in place, but
 * Postgres-specific behaviour that this project doesn't yet exercise — `INSERT ... ON CONFLICT`
 * upserts, `RETURNING`-based last-insert-id, and exhaustive DDL (native ENUM types, partial
 * indexes) — is intentionally not finished. Complete it before using Postgres in production.
 */
class PostgresGrammar extends Grammar
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
        return 'now()';
    }

    public function insertKeyword(bool $ignore): string
    {
        // NOTE: Postgres has no INSERT-level IGNORE; duplicate-skipping is `ON CONFLICT DO
        // NOTHING`, which is not yet wired. $ignore is accepted but not honoured here.
        return 'INSERT';
    }

    // === Schema (foundation) ==============================================================

    protected function typeMap(): array
    {
        return [
            'bigInteger' => 'BIGINT', 'integer' => 'INTEGER', 'string' => 'VARCHAR', 'char' => 'CHAR',
            'text' => 'TEXT', 'tinyText' => 'TEXT', 'mediumText' => 'TEXT', 'longText' => 'TEXT',
            'json' => 'JSONB', 'decimal' => 'NUMERIC', 'timestamp' => 'TIMESTAMP', 'dateTime' => 'TIMESTAMP',
            'geometry' => 'GEOMETRY', 'blob' => 'BYTEA', 'tinyBlob' => 'BYTEA', 'mediumBlob' => 'BYTEA',
            'longBlob' => 'BYTEA',
        ];
    }

    protected function indexesInline(): bool
    {
        return false;
    }

    protected function autoIncrementSql(ColumnDefinition $c): string
    {
        return ($c->type === 'bigInteger' ? 'BIGSERIAL' : 'SERIAL') . ' PRIMARY KEY';
    }

    protected function typeBoolean(): string
    {
        return 'BOOLEAN';
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT 1 FROM information_schema.tables WHERE table_name = '"
            . str_replace("'", "''", $table) . "'";
    }

    public function compileColumnExists(string $table, string $column): string
    {
        return "SELECT 1 FROM information_schema.columns WHERE table_name = '"
            . str_replace("'", "''", $table) . "' AND column_name = '" . str_replace("'", "''", $column) . "'";
    }

    /**
     * FOUNDATION: per-operation ADD/RENAME/DROP COLUMN + CREATE/DROP INDEX only. Column change()
     * (ALTER … TYPE / SET NOT NULL) and ON CONFLICT upserts are not implemented yet.
     */
    public function compileAlter(Blueprint $blueprint): array
    {
        $table = $blueprint->getTable();
        $statements = [];

        foreach ($blueprint->getColumns() as $col) {
            if ($col instanceof ColumnDefinition && $col->isChange) {
                throw new \RuntimeException('Postgres column change() is not implemented yet (foundation grammar).');
            }
            $sql = $col instanceof ColumnDefinition ? $this->compileColumn($col) : (string) $col;
            $statements[] = 'ALTER TABLE ' . $this->wrapTable($table) . ' ADD COLUMN ' . $sql;
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
            if (isset($entry['name'])) {
                $statements[] = 'DROP INDEX IF EXISTS ' . $this->wrapValue($entry['name']);
            }
        }
        return $statements;
    }
}
