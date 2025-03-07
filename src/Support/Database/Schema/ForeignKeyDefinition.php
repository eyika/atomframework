<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

class ForeignKeyDefinition
{
    protected string $column;
    protected ?string $references = null;
    protected ?string $on = null;
    protected ?string $onDelete = null;
    protected ?string $onUpdate = null;

    public function __construct(string $column, Blueprint $blueprint)
    {
        $this->column = $column;
        $blueprint->foreignKeys[] = $this;
    }

    public function references(string $references): self
    {
        $this->references = $references;
        return $this;
    }

    public function on(string $on): self
    {
        $this->on = $on;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function toSql(): string
    {
        $sql = sprintf(
            'FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
            $this->column,
            $this->on,
            $this->references
        );
        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }
        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }
        return $sql;
    }
}
