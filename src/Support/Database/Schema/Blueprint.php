<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Closure;
use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\Database\Connection;
use InvalidArgumentException;

class Blueprint
{
    protected string $table;
    /** @var ColumnDefinition[] */
    protected array $columns = [];
    /** @var IndexDefinition[] */
    public array $indexes = [];
    /** @property ForeignKeyDefinition[] */
    public array $foreignKeys = [];

    protected array $plugins = [];
    protected array $afterCreateHooks = [];
    protected array $dropColumns = [];
    protected array $modifyColumns = [];
    protected array $renameColumns = [];
    /**
     * Pending index drops. Each entry is one of:
     *   ['kind' => 'PRIMARY']
     *   ['kind' => 'INDEX', 'name' => '<explicit-name>']
     *   ['kind' => 'INDEX', 'columns' => [...], 'unique' => bool]
     * The third form is resolved to a name via INFORMATION_SCHEMA in
     * resolveDropIndexes() before toSql() emits the ALTER.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $dropIndexes = [];
    protected bool $alter;

    public function __construct(string $table, $alter = false)
    {
        $this->table = $table;
        $this->alter = $alter;
    }

    public function id(): ColumnDefinition
    {
        return $this->bigIncrements('id');
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column)->unsigned()->autoIncrement()->primary();
    }

    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column)->unsigned()->autoIncrement()->primary();
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, ['length' => $length]);
    }

    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, ['length' => $length]);
    }

    public function integer(string $column, bool $unsigned = false): ColumnDefinition
    {
        $col = $this->addColumn('integer', $column);
        return $unsigned ? $col->unsigned() : $col;
    }

    public function bigInteger(string $column, bool $unsigned = false): ColumnDefinition
    {
        $col = $this->addColumn('bigInteger', $column);
        return $unsigned ? $col->unsigned() : $col;
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->integer($column, true);
    }

    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column)->unsigned();
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn('char', $name, ['length' => 36])->unique();
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $name, compact('precision', 'scale'));
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    public function geometry(string $name): ColumnDefinition
    {
        return $this->addColumn('geometry', $name);
    }

    public function binary(string $name): ColumnDefinition
    {
        return $this->blob($name);
    }

    public function tinyBlob(string $name): ColumnDefinition
    {
        return $this->addColumn('tinyBlob', $name);
    }

    public function blob(string $name): ColumnDefinition
    {
        return $this->addColumn('blob', $name);
    }

    public function mediumBlob(string $name): ColumnDefinition
    {
        return $this->addColumn('mediumBlob', $name);
    }

    public function longBlob(string $name): ColumnDefinition
    {
        return $this->addColumn('longBlob', $name);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $name, ['allowed' => $values]);
    }

    public function dateTime(string $column, int $precision = 0): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column);
    }

    public function timestamps(): self
    {
        $this->addColumn('timestamp', 'created_at')->useCurrent();
        $this->addColumn('timestamp', 'updated_at')->useCurrent()->useCurrentOnUpdate();
        return $this;
    }

    public function softDeletes(): self
    {
        $this->timestamp('deleted_at')->nullable();
        return $this;
    }

    public function auditColumns(): void
    {
        $this->timestamps();
        $this->unsignedBigInteger('created_by')->nullable();
        $this->unsignedBigInteger('updated_by')->nullable();
    }

    public function rawSql(string $sql): void
    {
        $this->columns[] = $sql;
    }

    public function transformColumn(string $name, Closure $callback): void
    {
        $column = $this->getColumn($name);
        $callback($column);
    }

    public function rollback(): string
    {
        return Connection::grammar()->compileDropIfExists($this->table);
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($column, $this);
        // $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    public function foreignId(string $column): ForeignKeyDefinition
    {
        $this->addColumn('bigInteger', $column)->unsigned();
        $foreignKey = new ForeignKeyDefinition($column, $this);
        // $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    public function unique(string|array $column): self
    {
        // $this->indexes[] = is_string($column) ? "UNIQUE(`$column`)" : "UNIQUE(`" . implode("`, `", $column) . "`)";
        $this->addIndex('UNIQUE', Arr::wrap($column));
        return $this;
    }

    public function fulltext(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex('FULLTEXT', Arr::wrap($columns), $name);
    }

    public function spatialIndex(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex('SPATIAL', Arr::wrap($columns), $name);
    }

    public function addIndexWithStorage(
        string $type,
        array $columns,
        string $storageType,
        ?string $name = null
    ): IndexDefinition {
        $index = $this->addIndex($type, $columns, $name);
        $index->storageType = $storageType;
        return $index;
    }

    public function primary(string|array $column): self
    {
        $this->addIndex("PRIMARY KEY", Arr::wrap($column));
        return $this;
    }

    public function index(string|array $column, ?string $name = null): self
    {
        $this->addIndex("INDEX", Arr::wrap($column), $name);
        return $this;
    }

    /**
     * Queue a unique index drop. Pass column name(s) (the framework will
     * look up the actual index name covering exactly those columns) OR
     * an explicit string to drop a specific named index.
     */
    public function dropUnique(string|array $columns): self
    {
        if (is_string($columns)) {
            $this->dropIndexes[] = ['kind' => 'INDEX', 'name' => $columns];
        } else {
            $this->dropIndexes[] = ['kind' => 'INDEX', 'columns' => array_values($columns), 'unique' => true];
        }
        return $this;
    }

    /**
     * Queue a non-unique index drop. Same column-or-string semantics as
     * dropUnique().
     */
    public function dropIndex(string|array $columns): self
    {
        if (is_string($columns)) {
            $this->dropIndexes[] = ['kind' => 'INDEX', 'name' => $columns];
        } else {
            $this->dropIndexes[] = ['kind' => 'INDEX', 'columns' => array_values($columns), 'unique' => false];
        }
        return $this;
    }

    public function dropPrimary(): self
    {
        $this->dropIndexes[] = ['kind' => 'PRIMARY'];
        return $this;
    }

    /**
     * Look up real index names from INFORMATION_SCHEMA for any column-based
     * drop entries queued by dropUnique()/dropIndex(). Called by Schema
     * before toSql() so the generated ALTER references actual index names
     * — this is what lets dropUnique() work against legacy uniqid-suffixed
     * indexes without requiring the caller to know the suffix.
     *
     * Throws if a queued drop has no matching index in the live schema, so
     * a typo in the column list fails loudly instead of silently no-oping.
     */
    public function resolveDropIndexes(): void
    {
        foreach ($this->dropIndexes as $i => $entry) {
            if (($entry['kind'] ?? null) !== 'INDEX') {
                continue;
            }
            if (isset($entry['name'])) {
                continue;
            }

            $columns = $entry['columns'] ?? [];
            $unique = (bool)($entry['unique'] ?? false);
            $name = $this->lookupIndexNameForColumns($columns, $unique);

            if ($name === null) {
                throw new \RuntimeException(sprintf(
                    'No %s index on `%s` covering exactly: [%s]',
                    $unique ? 'unique' : 'non-unique',
                    $this->table,
                    implode(', ', $columns)
                ));
            }
            $this->dropIndexes[$i] = ['kind' => 'INDEX', 'name' => $name];
        }
    }

    /**
     * Find the index whose covered columns match $columns exactly (in
     * order). Returns null if none match. PRIMARY is excluded since it's
     * dropped via dropPrimary()/DROP PRIMARY KEY, not by name.
     */
    protected function lookupIndexNameForColumns(array $columns, bool $unique): ?string
    {
        // Delegated to the active grammar: the catalogue that knows index names is
        // driver-specific (INFORMATION_SCHEMA on MySQL, PRAGMA on SQLite, pg_index on
        // Postgres), and the query previously hard-coded here ran only on MySQL.
        return Connection::grammar()->indexNameForColumns($this->table, $columns, $unique);
    }

    public function addIndex(string $type, array $columns, ?string $name = null): IndexDefinition
    {
        $indexName = $name ?? $this->generateIndexName($type, $columns);
        $index = new IndexDefinition($type, $columns, $indexName);
        $this->indexes[] = $index;
        return $index;
    }

    protected function generateIndexName(string $type, array $columns): string
    {
        return strtolower(sprintf('%s_%s_%s', $type, implode('_', $columns), uniqid()));
    }

    /**
     * SQL statement(s) for this blueprint, compiled for the connection's active grammar.
     * CREATE may yield several statements (e.g. SQLite emits secondary indexes separately);
     * ALTER likewise. Schema::create()/table() execute each in order.
     *
     * @return string[]
     */
    public function compile(): array
    {
        $grammar = Connection::grammar();
        return $this->alter ? $grammar->compileAlter($this) : $grammar->compileCreate($this);
    }

    /** Back-compat single-string form (statements joined by ";\n"). */
    public function toSql(): string
    {
        return implode(";\n", $this->compile());
    }

    // --- Accessors used by the grammar to compile this blueprint --------------------------

    public function getTable(): string
    {
        return $this->table;
    }

    public function isAlter(): bool
    {
        return $this->alter;
    }

    /** @return array<int, ColumnDefinition|string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /** @return ForeignKeyDefinition[] */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /** @return IndexDefinition[] */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /** @return ColumnDefinition[] */
    public function getModifyColumns(): array
    {
        return $this->modifyColumns;
    }

    /** @return string[] */
    public function getDropColumns(): array
    {
        return $this->dropColumns;
    }

    /** @return array<int, array{from:string,to:string}> */
    public function getRenameColumns(): array
    {
        return $this->renameColumns;
    }

    /** @return array<int, array<string,mixed>> */
    public function getDropIndexes(): array
    {
        return $this->dropIndexes;
    }

    public function afterCreate(Closure $callback): void
    {
        $this->afterCreateHooks[] = $callback;
    }

    protected function executeAfterCreate(): void
    {
        foreach ($this->afterCreateHooks as $hook) {
            $hook($this->table);
        }
    }

    public function extractMetadata(): array
    {
        $metadata = [];
        foreach ($this->columns as $column) {
            $metadata[$column->name] = $column->commentText ?? '';
        }
        return $metadata;
    }

    public function registerPlugin(Closure $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    protected function executePlugins(): void
    {
        foreach ($this->plugins as $plugin) {
            $plugin($this);
        }
    }

    protected function getColumn(string $name): ColumnDefinition
    {
        return $this->columns[$name];
    }

    /**
     * Record a portable column. $type is a canonical token ('string', 'bigInteger', 'enum', …)
     * and $parameters carries type params (length / precision+scale / allowed / precision). The
     * active grammar compiles the token to dialect SQL at toSql()/compile() time.
     */
    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition($type, $name, $parameters);
        $this->columns[] = $column;
        return $column;
    }

    public function dropColumn(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->dropColumns[] = $column;
        }
        return $this;
    }

    /**
     * Queue a column change on an ALTER. $type is a portable token (e.g. 'string'); $options
     * carries type params. The column is flagged isChange so the grammar compiles it as a
     * MODIFY (MySQL) or a table-rebuild (SQLite).
     */
    public function modifyColumn(string $name, string $type, array $options = []): static
    {
        $definition = new ColumnDefinition($type, $name, $options);
        $definition->isChange = true;
        $this->modifyColumns[] = $definition;
        return $this;
    }

    public function renameColumn(string $from, string $to): static
    {
        $this->renameColumns[] = compact('from', 'to');
        return $this;
    }
}
