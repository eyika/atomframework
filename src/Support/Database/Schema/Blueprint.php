<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use InvalidArgumentException;

class Blueprint
{
    protected string $table;
    protected array $columns = [];
    public array $indexes = [];

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

    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` TEXT");
    }

    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` JSON");
    }

    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn("`$column` TINYINT(1)");
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

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreignKey = new ForeignKeyDefinition($column, $this);
        $this->indexes[] = $foreignKey;
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

    public function toSql(): string
    {
        $columns = implode(",\n  ", array_map(fn($col) => $col->getDefinition(), $this->columns));
        $indexes = implode(",\n  ", array_map(fn($idx) => (string) $idx, $this->indexes));

        return "CREATE TABLE `$this->table` (\n  $columns" . ($indexes ? ",\n  $indexes" : "") . "\n);";
    }

    protected function addColumn(string $definition): ColumnDefinition
    {
        $column = new ColumnDefinition($definition);
        $this->columns[] = $column;
        return $column;
    }
}
