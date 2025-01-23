<?php

class IndexDefinition
{
    public string $type;
    public array $columns;
    public string $name;
    public ?string $storageType = null;
    public ?string $method = null;
    public bool $concurrently = false;
    public bool $invisible = false;

    public function __construct(string $type, array $columns, string $name)
    {
        $this->type = $type;
        $this->columns = $columns;
        $this->name = $name;
    }

    public function using(string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function withStorage(string $storageType): self
    {
        $this->storageType = $storageType;
        return $this;
    }

    public function concurrently(): self
    {
        $this->concurrently = true;
        return $this;
    }

    public function invisible(): self
    {
        $this->invisible = true;
        return $this;
    }

    public function toSql(): string
    {
        $concurrentlyPart = $this->concurrently ? ' CONCURRENTLY' : '';
        $methodPart = $this->method ? " USING {$this->method}" : '';
        $invisiblePart = $this->invisible ? ' INVISIBLE' : '';
        $columns = implode(',', $this->columns);

        return sprintf(
            '%s%s INDEX `%s` (%s)%s%s',
            strtoupper($this->type),
            $concurrentlyPart,
            $this->name,
            $columns,
            $methodPart,
            $invisiblePart
        );
    }
}
