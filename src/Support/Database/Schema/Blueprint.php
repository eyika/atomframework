<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Closure;
use IndexDefinition;
use InvalidArgumentException;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    public array $indexes = [];
    public array $foreignKeys = [];

    protected array $plugins = [];
    protected array $afterCreateHooks = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): ColumnDefinition
    {
        return $this->bigIncrements('id');
    }

    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY");
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn("`$column` VARCHAR($length)");
    }

    public function integer(string $column, bool $unsigned = false): ColumnDefinition
    {
        return $this->addColumn("`$column` INT" . ($unsigned ? " UNSIGNED" : ""));
    }
    
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` BIGINT UNSIGNED");
    }

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` TEXT");
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` JSON");
    }

    public function uuid(string $name): ColumnDefinition
    {
        return $this->addColumn('CHAR(36)', $name)->unique();
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn("DECIMAL({$precision},{$scale})", $name);
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` TINYINT(1)");
    }

    public function geometry(string $name): ColumnDefinition
    {
        return $this->addColumn('geometry', $name);
    }

    public function enum(string $name, array $values): ColumnDefinition
    {
        $valuesString = implode("', '", $values);
        return $this->addColumn("ENUM('$valuesString')", $name);
    }

    public function fulltext(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex('FULLTEXT', (array) $columns, $name);
    }

    public function spatialIndex(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndex('SPATIAL', (array) $columns, $name);
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

    public function timestamp(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` TIMESTAMP");
    }

    public function timestamps(): self
    {
        $this->addColumn("`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        $this->addColumn("`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        return $this;
    }

    public function auditColumns(): void
    {
        $this->timestamp('created_at')->nullable();
        $this->timestamp('updated_at')->nullable();
        $this->unsignedBigInteger('created_by')->nullable();
        $this->unsignedBigInteger('updated_by')->nullable();
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($column, $this);
        $this->foreignKeys[] = $foreignKey;
        return $foreignKey;
    }

    public function unique(string $column): self
    {
        $this->indexes[] = "UNIQUE(`$column`)";
        return $this;
    }

    public function primary(string $column): self
    {
        $this->indexes[] = "PRIMARY KEY(`$column`)";
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

    // public function toSql(): string
    // {
    //     $columns = implode(",\n  ", array_map(fn($col) => $col->getDefinition(), $this->columns));
    //     $indexes = implode(",\n  ", array_map(fn($idx) => (string) $idx, $this->indexes));

    //     return "CREATE TABLE `$this->table` (\n  $columns" . ($indexes ? ",\n  $indexes" : "") . "\n);";
    // }

    public function toSql(): string
    {
        $columnsSql = array_map(fn($column) => $column->toSql(), $this->columns);
        $foreignKeysSql = array_map(fn($foreign) => $foreign->toSql(), $this->foreignKeys);
        $indexesSql = array_map(fn($index) => $index->toSql(), $this->indexes);

        $allDefinitions = array_merge($columnsSql, $foreignKeysSql, $indexesSql);
        $definitions = implode(",\n    ", $allDefinitions);

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
            $metadata[$column->name] = $column->comment ?? '';
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

    protected function addColumn(string $definition): ColumnDefinition
    {
        $column = new ColumnDefinition($definition);
        $this->columns[] = $column;
        return $column;
    }
}
