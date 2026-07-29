<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

use Eyika\Atom\Framework\Support\Database\Connection;

/**
 * A portable, structured column definition. It records *intent* (a canonical type token plus
 * modifier flags) rather than a baked SQL string, so the active {@see \Eyika\Atom\Framework\Support\Database\Grammars\Grammar}
 * can compile it for whichever engine is connected. The fluent API mirrors the old string-based
 * one, so migrations don't change.
 */
class ColumnDefinition
{
    /** Canonical type token, e.g. 'string', 'bigInteger', 'json', 'enum', 'timestamp'. */
    public string $type;
    /** Type parameters: ['length'=>255] | ['precision'=>8,'scale'=>2] | ['allowed'=>[...]] | ['precision'=>0]. */
    public array $parameters = [];

    public string $name = '';

    /** null = caller didn't say (grammar default applies); true = NULL; false = NOT NULL. */
    public ?bool $nullable = null;

    public bool $hasDefault = false;
    public mixed $default = null;
    /** When true the default is a raw SQL keyword (e.g. CURRENT_TIMESTAMP), not a literal to quote. */
    public bool $defaultRaw = false;

    public bool $unique = false;
    public bool $primary = false;
    public bool $autoIncrement = false;
    public bool $unsigned = false;
    /** MySQL `ON UPDATE CURRENT_TIMESTAMP`; ignored by engines that don't support it. */
    public bool $onUpdateCurrent = false;

    // MySQL-only cosmetics — emitted only by the MySQL grammar, ignored elsewhere.
    public ?string $commentText = null;
    public ?string $charsetVal = null;
    public ?string $collationVal = null;
    public ?string $afterCol = null;
    public bool $first = false;
    public ?string $generatedExpr = null;
    public ?string $generatedKind = null; // 'VIRTUAL' | 'STORED'

    /** True on an ALTER when this column definition is a MODIFY rather than an ADD. */
    public bool $isChange = false;

    public function __construct(string $type, string $name = '', array $parameters = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function notNullable(): self
    {
        return $this->nullable(false);
    }

    /** Re-type as VARCHAR(len) (used by ->varChar() on a change()). */
    public function varChar(int $len = 255): self
    {
        $this->type = 'string';
        $this->parameters['length'] = $len;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->hasDefault = true;
        $this->default = $value;
        // A CURRENT_TIMESTAMP string is a raw SQL keyword, not a literal to quote.
        $this->defaultRaw = is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP';
        return $this;
    }

    /**
     * Generic ON <action> clause. Only ON UPDATE CURRENT_TIMESTAMP is portable-relevant; it maps
     * to the onUpdateCurrent flag (MySQL emits it, others ignore it).
     */
    public function on(string $action, mixed $value): self
    {
        if (strtoupper($action) === 'UPDATE'
            && is_string($value) && strtoupper($value) === 'CURRENT_TIMESTAMP') {
            $this->onUpdateCurrent = true;
        }
        return $this;
    }

    public function useCurrent(): self
    {
        return $this->default('CURRENT_TIMESTAMP');
    }

    public function useCurrentOnUpdate(): self
    {
        $this->onUpdateCurrent = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->afterCol = $column;
        return $this;
    }

    public function comment(string $text): self
    {
        $this->commentText = $text;
        return $this;
    }

    public function charset(string $charset): self
    {
        $this->charsetVal = $charset;
        return $this;
    }

    public function collation(string $collation): self
    {
        $this->collationVal = $collation;
        return $this;
    }

    public function virtualAs(string $expression): self
    {
        $this->generatedExpr = $expression;
        $this->generatedKind = 'VIRTUAL';
        return $this;
    }

    public function storedAs(string $expression): self
    {
        $this->generatedExpr = $expression;
        $this->generatedKind = 'STORED';
        return $this;
    }

    public function change(): self
    {
        $this->isChange = true;
        return $this;
    }

    /** Compile this column for the active connection's grammar. */
    public function toSql(): string
    {
        return Connection::grammar()->compileColumn($this);
    }
}
