<?php

namespace Eyika\Atom\Framework\Support\Database\Grammars;

use Eyika\Atom\Framework\Support\Database\Schema\Blueprint;
use Eyika\Atom\Framework\Support\Database\Schema\ColumnDefinition;
use Eyika\Atom\Framework\Support\Database\Schema\ForeignKeyDefinition;
use Eyika\Atom\Framework\Support\Database\Schema\IndexDefinition;

/**
 * Base SQL dialect grammar. A grammar owns every decision that differs between database
 * engines — identifier quoting, dialect functions (random/now), INSERT syntax, conditionals —
 * so the {@see \Eyika\Atom\Framework\Support\Database\Connection} query layer and the
 * {@see \Eyika\Atom\Framework\Support\Database\Schema\Blueprint} DDL layer can stay
 * engine-agnostic. Concrete grammars: {@see MySqlGrammar} (the default / current behaviour),
 * {@see SqliteGrammar}, {@see PostgresGrammar}.
 *
 * The single-identifier escaping (wrapValue) is the SQL-injection defence for column/table
 * names interpolated into SQL — it MUST double the quote character so a crafted identifier
 * can never break out of the quoting. Every subclass preserves that guarantee.
 */
abstract class Grammar
{
    /**
     * Escape one identifier segment (a bare column or table name) by wrapping it in the
     * dialect's quote character and doubling any embedded quote char. Injection-safe.
     */
    abstract public function wrapValue(string $value): string;

    /**
     * Wrap a possibly-qualified identifier ("table.col" -> `table`.`col`). "*" passes
     * through unquoted, as does a trailing ".*".
     */
    public function wrap(string $value): string
    {
        if ($value === '*') {
            return '*';
        }
        if (str_contains($value, '.')) {
            return implode('.', array_map(
                fn ($part) => $part === '*' ? '*' : $this->wrapValue($part),
                explode('.', $value)
            ));
        }
        return $this->wrapValue($value);
    }

    /** Wrap a table name (kept distinct from wrap() so a grammar can add a schema prefix). */
    public function wrapTable(string $table): string
    {
        return $this->wrap($table);
    }

    /** ORDER BY expression that yields rows in random order. */
    abstract public function random(): string;

    /** Expression for "the current timestamp", usable in a WHERE comparison or a VALUES list. */
    public function now(): string
    {
        return 'now()';
    }

    // --- INSERT ---------------------------------------------------------------------------

    /**
     * Does this dialect support MySQL's `INSERT ... SET col = val` form? MySQL does; the
     * standard `INSERT INTO t (cols) VALUES (...)` form is used everywhere else.
     */
    public function supportsInsertSetSyntax(): bool
    {
        return false;
    }

    /** The leading INSERT keyword, including the dialect's "ignore duplicates" modifier. */
    public function insertKeyword(bool $ignore): string
    {
        return 'INSERT' . ($ignore ? ' OR IGNORE' : '');
    }

    /**
     * Compile a standard column-list INSERT. $columns are bare names; $exprs are the matching
     * value expressions (a bound placeholder like ":name", or a raw token like NULL /
     * CURRENT_TIMESTAMP). Used for every dialect that isn't MySQL's SET form.
     */
    public function compileInsert(string $table, array $columns, array $exprs, bool $ignore): string
    {
        $cols = implode(', ', array_map([$this, 'wrapValue'], $columns));
        $vals = implode(', ', $exprs);

        return $this->insertKeyword($ignore) . ' INTO ' . $this->wrapTable($table) . " ({$cols}) VALUES ({$vals})";
    }

    /** A conditional expression: MySQL uses IF(), standard SQL uses CASE WHEN. */
    public function compileIf(string $condition, string $then, string $else): string
    {
        return "CASE WHEN {$condition} THEN {$then} ELSE {$else} END";
    }

    /** Value expression that sets a column to the current timestamp (INSERT/UPDATE VALUES). */
    public function currentTimestamp(): string
    {
        return 'current_timestamp';
    }

    /**
     * Statement that opens a transaction. MySQL/Postgres accept 'START TRANSACTION';
     * SQLite requires 'BEGIN'. COMMIT/ROLLBACK are standard SQL across all three.
     */
    public function compileBeginTransaction(): string
    {
        return 'START TRANSACTION';
    }

    /**
     * Pessimistic row-lock suffix for SELECT ... (within a transaction). MySQL/Postgres
     * use ' FOR UPDATE'; SQLite has no row locks (the transaction itself serializes) so
     * it returns an empty string.
     */
    public function compileForUpdate(): string
    {
        return ' FOR UPDATE';
    }

    // === Schema / DDL =====================================================================

    /** Portable type token -> this dialect's base SQL type (no length/params). */
    abstract protected function typeMap(): array;

    /** Whether secondary indexes are declared inline in CREATE TABLE (MySQL) vs as separate CREATE INDEX (sqlite/pgsql). */
    protected function indexesInline(): bool
    {
        return true;
    }

    /** Compile a full column clause: `name` TYPE modifiers…  */
    public function compileColumn(ColumnDefinition $c): string
    {
        // An auto-incrementing primary key is a single indivisible type phrase per engine
        // (`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, `INTEGER PRIMARY KEY AUTOINCREMENT`, …).
        if ($c->autoIncrement && $c->primary) {
            return $this->wrapValue($c->name) . ' ' . $this->autoIncrementSql($c);
        }
        return $this->wrapValue($c->name) . ' ' . $this->getType($c) . $this->compileModifiers($c);
    }

    /** Full type phrase for an auto-increment PK. MySQL default; overridden by sqlite/pgsql. */
    protected function autoIncrementSql(ColumnDefinition $c): string
    {
        $base = $c->type === 'bigInteger' ? 'BIGINT' : 'INT';
        return $base . ' UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    }

    protected function getType(ColumnDefinition $c): string
    {
        $type = match ($c->type) {
            'enum'    => $this->typeEnum($c),
            'boolean' => $this->typeBoolean(),
            default   => $this->typeWithParams($c),
        };
        return $this->applyUnsigned($type, $c);
    }

    protected function typeWithParams(ColumnDefinition $c): string
    {
        $map = $this->typeMap();
        $base = $map[$c->type] ?? strtoupper($c->type);
        $p = $c->parameters;

        return match ($c->type) {
            'string'  => $base . '(' . ($p['length'] ?? 255) . ')',
            'char'    => $base . '(' . ($p['length'] ?? 36) . ')',
            'boolean' => $base . '(1)',
            'decimal' => $base . '(' . ($p['precision'] ?? 8) . ',' . ($p['scale'] ?? 2) . ')',
            default   => $base,
        };
    }

    /** MySQL appends UNSIGNED to integer types; portable engines have no unsigned. */
    protected function applyUnsigned(string $type, ColumnDefinition $c): string
    {
        return $type;
    }

    protected function typeBoolean(): string
    {
        return 'BOOLEAN';
    }

    /** Portable enum = a plain string column; MySQL overrides to native ENUM(...). */
    protected function typeEnum(ColumnDefinition $c): string
    {
        return 'VARCHAR(255)';
    }

    protected function compileModifiers(ColumnDefinition $c): string
    {
        $sql = '';
        if ($c->generatedExpr !== null) {
            $sql .= " AS ({$c->generatedExpr}) {$c->generatedKind}";
        }
        if ($c->nullable === false) {
            $sql .= ' NOT NULL';
        } elseif ($c->nullable === true) {
            $sql .= ' NULL';
        }
        if ($c->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($c->default, $c->defaultRaw);
        }
        $sql .= $this->compileOnUpdate($c);
        if ($c->unique) {
            $sql .= ' UNIQUE';
        }
        if ($c->primary && !$c->autoIncrement) {
            $sql .= ' PRIMARY KEY';
        }
        return $sql . $this->compileColumnExtras($c);
    }

    protected function formatDefault(mixed $value, bool $raw): string
    {
        if ($raw) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return "'" . str_replace("'", "\\'", (string) $value) . "'";
    }

    /** MySQL `ON UPDATE CURRENT_TIMESTAMP`; no-op elsewhere. */
    protected function compileOnUpdate(ColumnDefinition $c): string
    {
        return '';
    }

    /** MySQL-only column cosmetics (charset/collation/comment/after); no-op elsewhere. */
    protected function compileColumnExtras(ColumnDefinition $c): string
    {
        return '';
    }

    /**
     * Trailing table-level options appended after the closing `)` of CREATE TABLE — engine,
     * default charset, collation. Empty for standard-SQL dialects (sqlite/pgsql have no such
     * clause); MySQL overrides it so tables are created utf8mb4 instead of inheriting the
     * server default (which is latin1 on many MariaDB installs and rejects multi-byte UTF-8).
     */
    protected function tableOptions(): string
    {
        return '';
    }

    /** Compile CREATE TABLE. Returns 1+ statements (sqlite emits secondary indexes separately). */
    public function compileCreate(Blueprint $blueprint): array
    {
        $lines = [];
        foreach ($blueprint->getColumns() as $col) {
            $lines[] = $col instanceof ColumnDefinition ? $this->compileColumn($col) : (string) $col;
        }
        foreach ($blueprint->getForeignKeys() as $fk) {
            $lines[] = $this->compileForeignKey($fk);
        }

        $separate = [];
        foreach ($blueprint->getIndexes() as $idx) {
            if (strtoupper($idx->type) === 'PRIMARY KEY') {
                $lines[] = $this->compilePrimaryKey($idx);
            } elseif ($this->indexesInline()) {
                $lines[] = $this->compileInlineIndex($idx);
            } else {
                $separate[] = $idx;
            }
        }

        $body = implode(",\n    ", $lines);
        $statements = [sprintf("CREATE TABLE %s (\n    %s\n)%s", $this->wrapTable($blueprint->getTable()), $body, $this->tableOptions())];

        foreach ($separate as $idx) {
            if (($sql = $this->compileCreateIndex($idx, $blueprint->getTable())) !== null) {
                $statements[] = $sql;
            }
        }
        return $statements;
    }

    protected function compilePrimaryKey(IndexDefinition $idx): string
    {
        return 'PRIMARY KEY (' . $this->columnize($idx->columns) . ')';
    }

    /** Inline index (MySQL): `UNIQUE INDEX \`name\` (\`a\`,\`b\`)`. */
    protected function compileInlineIndex(IndexDefinition $idx): string
    {
        $type = strtoupper($idx->type);
        $keyword = match ($type) {
            'UNIQUE'   => 'UNIQUE INDEX',
            'FULLTEXT' => 'FULLTEXT INDEX',
            'SPATIAL'  => 'SPATIAL INDEX',
            default    => 'INDEX',
        };
        return "{$keyword} " . $this->wrapValue($idx->name) . ' (' . $this->columnize($idx->columns) . ')';
    }

    /** Separate index (sqlite/pgsql): `CREATE [UNIQUE] INDEX name ON table (...)`; null = unsupported. */
    protected function compileCreateIndex(IndexDefinition $idx, string $table): ?string
    {
        $type = strtoupper($idx->type);
        if (in_array($type, ['FULLTEXT', 'SPATIAL'], true)) {
            return null; // not supported outside MySQL
        }
        $unique = $type === 'UNIQUE' ? 'UNIQUE ' : '';
        return "CREATE {$unique}INDEX " . $this->wrapValue($idx->name)
            . ' ON ' . $this->wrapTable($table) . ' (' . $this->columnize($idx->columns) . ')';
    }

    public function compileForeignKey(ForeignKeyDefinition $fk): string
    {
        $sql = 'FOREIGN KEY (' . $this->wrapValue($fk->getColumn()) . ') REFERENCES '
            . $this->wrapTable((string) $fk->getOn()) . ' (' . $this->wrapValue((string) $fk->getReferences()) . ')';
        if ($fk->getOnUpdate()) {
            $sql .= ' ON UPDATE ' . $fk->getOnUpdate();
        }
        if ($fk->getOnDelete()) {
            $sql .= ' ON DELETE ' . $fk->getOnDelete();
        }
        return $sql;
    }

    public function compileDropIfExists(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->wrapTable($table);
    }

    /** Catalog query returning >0 rows iff $table exists. */
    abstract public function compileTableExists(string $table): string;

    /** Catalog query returning >0 rows iff $table.$column exists. */
    abstract public function compileColumnExists(string $table, string $column): string;

    /** Compile ALTER TABLE. Returns 1+ statements. */
    abstract public function compileAlter(Blueprint $blueprint): array;

    /** Comma-join wrapped identifiers. */
    protected function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrapValue'], $columns));
    }
}
