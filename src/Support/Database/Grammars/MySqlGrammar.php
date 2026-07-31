<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\ColumnDefinition;

/**
 * MySQL / MariaDB grammar — the framework's default and the historical behaviour. Everything
 * here reproduces exactly what the DB layer emitted before grammars existed (backtick
 * identifiers, RAND(), now(), `INSERT ... SET`, IF()) so switching a MySQL app to the grammar
 * layer is a no-op.
 */
class MySqlGrammar extends Grammar
{
    public function wrapValue(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function random(): string
    {
        return 'RAND()';
    }

    public function now(): string
    {
        return 'now()';
    }

    public function supportsInsertSetSyntax(): bool
    {
        return true;
    }

    public function insertKeyword(bool $ignore): string
    {
        return 'INSERT' . ($ignore ? ' IGNORE' : '');
    }

    public function compileIf(string $condition, string $then, string $else): string
    {
        return "IF({$condition}, {$then}, {$else})";
    }

    // === Schema ===========================================================================

    protected function typeMap(): array
    {
        return [
            'bigInteger' => 'BIGINT', 'integer' => 'INT', 'string' => 'VARCHAR', 'char' => 'CHAR',
            'text' => 'TEXT', 'tinyText' => 'TINYTEXT', 'mediumText' => 'MEDIUMTEXT', 'longText' => 'LONGTEXT',
            'json' => 'JSON', 'decimal' => 'DECIMAL', 'timestamp' => 'TIMESTAMP', 'dateTime' => 'DATETIME',
            'geometry' => 'GEOMETRY', 'blob' => 'BLOB', 'tinyBlob' => 'TINYBLOB', 'mediumBlob' => 'MEDIUMBLOB',
            'longBlob' => 'LONGBLOB',
        ];
    }

    protected function applyUnsigned(string $type, ColumnDefinition $c): string
    {
        return $c->unsigned ? $type . ' UNSIGNED' : $type;
    }

    protected function typeBoolean(): string
    {
        return 'TINYINT(1)';
    }

    protected function typeEnum(ColumnDefinition $c): string
    {
        $allowed = array_map(fn ($v) => "'" . str_replace("'", "\\'", (string) $v) . "'", $c->parameters['allowed'] ?? []);
        return 'ENUM(' . implode(', ', $allowed) . ')';
    }

    protected function compileOnUpdate(ColumnDefinition $c): string
    {
        return $c->onUpdateCurrent ? ' ON UPDATE CURRENT_TIMESTAMP' : '';
    }

    /**
     * ` ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`, so every table is
     * explicitly utf8mb4 rather than inheriting the server's default charset (latin1 on many
     * MariaDB installs — a σ into such a column dies with `1366 Incorrect string value`).
     * Overridable per app via `database.connections.mysql.{engine,charset,collation}`.
     */
    protected function tableOptions(): string
    {
        $engine    = config('database.connections.mysql.engine')    ?: 'InnoDB';
        $charset   = config('database.connections.mysql.charset')   ?: 'utf8mb4';
        $collation = config('database.connections.mysql.collation') ?: 'utf8mb4_unicode_ci';

        return " ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collation}";
    }

    protected function compileColumnExtras(ColumnDefinition $c): string
    {
        $sql = '';
        if ($c->charsetVal) {
            $sql .= " CHARACTER SET {$c->charsetVal}";
        }
        if ($c->collationVal) {
            $sql .= " COLLATE {$c->collationVal}";
        }
        if ($c->commentText !== null) {
            $sql .= " COMMENT '" . str_replace("'", "\\'", $c->commentText) . "'";
        }
        if ($c->first) {
            $sql .= ' FIRST';
        } elseif ($c->afterCol) {
            $sql .= ' AFTER ' . $this->wrapValue($c->afterCol);
        }
        return $sql;
    }

    public function compileTableExists(string $table): string
    {
        return "SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'";
    }

    public function compileColumnExists(string $table, string $column): string
    {
        return 'SHOW COLUMNS FROM ' . $this->wrapTable($table) . " LIKE '" . str_replace("'", "''", $column) . "'";
    }

    /** MySQL emits every change as one ALTER TABLE with comma-joined clauses (as before). */
    public function compileAlter(Blueprint $blueprint): array
    {
        $statements = [];

        foreach ($blueprint->getColumns() as $col) {
            if ($col instanceof ColumnDefinition && $col->isChange) {
                $statements[] = 'MODIFY COLUMN ' . $this->compileColumn($col);
            } else {
                $statements[] = 'ADD ' . ($col instanceof ColumnDefinition ? $this->compileColumn($col) : (string) $col);
            }
        }
        foreach ($blueprint->getForeignKeys() as $fk) {
            $statements[] = 'ADD ' . $this->compileForeignKey($fk);
        }
        foreach ($blueprint->getIndexes() as $idx) {
            $statements[] = strtoupper($idx->type) === 'PRIMARY KEY'
                ? 'ADD ' . $this->compilePrimaryKey($idx)
                : 'ADD ' . $this->compileInlineIndex($idx);
        }
        foreach ($blueprint->getModifyColumns() as $col) {
            $statements[] = 'MODIFY COLUMN ' . $this->compileColumn($col);
        }
        foreach ($blueprint->getDropColumns() as $col) {
            $statements[] = 'DROP COLUMN ' . $this->wrapValue($col);
        }
        foreach ($blueprint->getRenameColumns() as $pair) {
            $statements[] = 'RENAME COLUMN ' . $this->wrapValue($pair['from']) . ' TO ' . $this->wrapValue($pair['to']);
        }
        foreach ($blueprint->getDropIndexes() as $entry) {
            if (($entry['kind'] ?? null) === 'PRIMARY') {
                $statements[] = 'DROP PRIMARY KEY';
                continue;
            }
            if (!isset($entry['name'])) {
                throw new \RuntimeException('Blueprint::resolveDropIndexes() must run before compiling an ALTER with column-based drops.');
            }
            $statements[] = 'DROP INDEX ' . $this->wrapValue($entry['name']);
        }

        return [sprintf("ALTER TABLE %s\n    %s", $this->wrapTable($blueprint->getTable()), implode(",\n    ", $statements))];
    }
}
