<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Closure;
use Eyika\Atom\Framework\Support\Arr;
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
        return $this->addColumn("BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY", $column);
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn("VARCHAR", $column, [$length]);
    }

    public function integer(string $column, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn("INT" . ($unsigned ? " UNSIGNED" : ""), $column);
    }

    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->integer($column, true);
    }
    
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn("BIGINT UNSIGNED", $column);
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn("TEXT", $column);
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn("JSON", $column);
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn('CHAR(36)', $name)->unique();
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn("DECIMAL", $name, [$precision, $scale]);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn("TINYINT", $column, [1]);
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
        return $this->addColumn('TINYBLOB', $name);
    }

    public function blob(string $name): ColumnDefinition
    {
        return $this->addColumn('BLOB', $name);
    }

    public function mediumBlob(string $name): ColumnDefinition
    {
        return $this->addColumn('MEDIUMBLOB', $name);
    }

    public function longBlob(string $name): ColumnDefinition
    {
        return $this->addColumn('LONGBLOB', $name);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        return $this->addColumn("ENUM", $name, $values);
    }
    
    public function dateTime(string $column, int $precision = 0)
    {
        return $this->addColumn('datetime', $column, compact('precision'));
    }

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn("TIMESTAMP", $column);
    }

    public function timestamps(): self
    {
        $this->addColumn("TIMESTAMP DEFAULT CURRENT_TIMESTAMP", "created_at");
        $this->addColumn("TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", "updated_at");
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
        return sprintf('DROP TABLE IF EXISTS `%s`;', $this->table);
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($column, $this);
        // $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    public function foreignId(string $column): ForeignKeyDefinition
    {
        $this->addColumn("BIGINT UNSIGNED", $column);
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

    public function toSql(): string
    {
        if ($this->alter) {
            $statements = [];

            // Partition columns into ADD and MODIFY COLUMN
            foreach ($this->columns as $col) {
                if ($col instanceof ColumnDefinition && $col->isChange) {
                    $statements[] = "MODIFY COLUMN " . $col->toSql();
                } else {
                    $sql = $col instanceof ColumnDefinition ? $col->toSql() : (string) $col;
                    $statements[] = "ADD " . $sql;
                }
            }

            foreach ($this->foreignKeys as $foreign) {
                $statements[] = "ADD " . $foreign->toSql();
            }

            foreach ($this->indexes as $index) {
                $statements[] = "ADD " . $index->toSql();
            }

            foreach ($this->modifyColumns as $col) {
                $statements[] = "MODIFY COLUMN " . $col->toSql();
            }

            foreach ($this->dropColumns as $col) {
                $statements[] = "DROP COLUMN `$col`";
            }

            foreach ($this->renameColumns as $pair) {
                $statements[] = "RENAME COLUMN `{$pair['from']}` TO `{$pair['to']}`";
            }

            $alterStatements = implode(",\n    ", $statements);
            return sprintf("ALTER TABLE `%s`\n    %s;", $this->table, $alterStatements);
        }

        // Otherwise, it's a CREATE TABLE
        $columnsSql = array_map(fn(ColumnDefinition $column) => $column->toSql(), $this->columns);
        $foreignKeysSql = array_map(fn(ForeignKeyDefinition $foreign) => $foreign->toSql(), $this->foreignKeys);
        $indexesSql = array_map(fn(IndexDefinition $index) => $index->toSql(), $this->indexes);

        $definitions = implode(",\n    ", array_merge($columnsSql, $foreignKeysSql, $indexesSql));
        return sprintf("CREATE TABLE `%s` (\n    %s\n);", $this->table, $definitions);
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

    protected function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $str_params = [];
        $number_params = [];
        foreach ($parameters as $parameter) {
            if (is_string($parameter)) {
                $str_params[] = $parameter;
                continue;
            }
            $number_params[] = $parameter;
        }
        if (count($parameters)) {
            $separator = (bool)count($number_params) ? "', " : "'";
            $param_str = count($str_params) ? "'". implode("', '", $str_params). $separator : "";
            $param_num = implode(",", $number_params);
            $type = "$type({$param_str}{$param_num})";
        }
        $column = new ColumnDefinition("`$name` $type");
        $column->name = $name;
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

    public function modifyColumn(string $name, string $type, array $options = []): static
    {
        $definition = new ColumnDefinition($name, $type, $options);
        $this->modifyColumns[] = $definition;
        return $this;
    }

    public function renameColumn(string $from, string $to): static
    {
        $this->renameColumns[] = compact('from', 'to');
        return $this;
    }
}
