<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

class ColumnDefinition
{
    protected string $definition;
    protected array $modifiers = [];

    public function __construct(string $definition)
    {
        $this->definition = $definition;
    }

    public function nullable(): self
    {
        $this->modifiers[] = "NULL";
        return $this;
    }

    public function notNullable(): self
    {
        $this->modifiers[] = "NOT NULL";
        return $this;
    }

    public function unique(): self
    {
        $this->modifiers[] = "UNIQUE";
        return $this;
    }

    public function unsigned(): self
    {
        $this->definition = str_replace("INT", "INT UNSIGNED", $this->definition);
        $this->definition = str_replace("BIGINT", "BIGINT UNSIGNED", $this->definition);
        return $this;
    }

    public function default(mixed $value): self
    {
        $default = is_string($value) ? "'$value'" : $value;
        $this->modifiers[] = "DEFAULT $default";
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->modifiers[] = "AUTO_INCREMENT";
        return $this;
    }

    public function primary(): self
    {
        $this->modifiers[] = "PRIMARY KEY";
        return $this;
    }

    public function after(string $column): self
    {
        $this->modifiers[] = "AFTER `$column`";
        return $this;
    }

    public function comment(string $text): self
    {
        $this->modifiers[] = "COMMENT '$text'";
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->modifiers[] = "CHARACTER SET $charset";
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->modifiers[] = "COLLATE $collation";
        return $this;
    }

    public function virtualAs(string $expression): self
    {
        $this->modifiers[] = "AS ($expression) VIRTUAL";
        return $this;
    }

    public function storedAs(string $expression): self
    {
        $this->modifiers[] = "AS ($expression) STORED";
        return $this;
    }

    public function getDefinition(): string
    {
        $modifiers = implode(" ", $this->modifiers);
        return trim("$this->definition $modifiers");
    }
}
