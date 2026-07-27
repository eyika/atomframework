<?php
namespace Eyika\Atom\Framework\Support\Database\Schema;

class ColumnDefinition
{
    protected string $definition;
    protected array $modifiers = [];
    public bool $isChange = false;
    public string $name = '';
    public ?string $commentText = null;

    public function __construct(string $definition)
    {
        $this->definition = $definition;
    }

    /**
     * Mark the column nullable (default) or NOT NULL.
     *
     * Matches Laravel's API so ->nullable(false)->change() flips an
     * existing nullable column to NOT NULL on the way through MODIFY
     * COLUMN. Previously, the no-arg signature silently ignored the
     * caller's intent and always emitted NULL — a footgun on ->change()
     * paths where the migration appeared to do nothing.
     *
     * Strips any prior NULL / NOT NULL on the same column so chained
     * calls (or a nullable()->notNullable() flip) end with a single,
     * coherent null constraint instead of both clauses concatenated.
     */
    public function nullable(bool $value = true): self
    {
        $this->modifiers = array_values(array_filter(
            $this->modifiers,
            fn($m) => !preg_match('/^(NOT\s+)?NULL$/i', $m)
        ));
        $this->modifiers[] = $value ? 'NULL' : 'NOT NULL';
        return $this;
    }

    public function notNullable(): self
    {
        return $this->nullable(false);
    }

    public function varChar(int $len = 255): self
    {
        // If VARCHAR already exists, replace it with the new length
        if (preg_match('/VARCHAR\(\d+\)/', $this->definition)) {
            $this->definition = preg_replace('/VARCHAR\(\d+\)/', "VARCHAR($len)", $this->definition);
        } else {
            if (preg_match('/VARCHAR\(\d+\)/', implode(" ", $this->modifiers))) {
                $this->modifiers = preg_replace('/VARCHAR\(\d+\)/', "VARCHAR($len)", $this->modifiers);
            } else {
                $this->modifiers[] = "VARCHAR($len)";
            }
        }
    
        return $this;
    }

    public function unique(): self
    {
        $this->addModifier("UNIQUE");
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
        if (is_bool($value)) {
            $rendered = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $rendered = 'NULL';
        } elseif (is_int($value) || is_float($value)) {
            $rendered = (string) $value;
        } elseif (is_string($value) && $value === 'CURRENT_TIMESTAMP') {
            // Raw SQL keyword — don't quote
            $rendered = 'CURRENT_TIMESTAMP';
        } else {
            $escaped = str_replace("'", "\\'", (string) $value);
            $rendered = "'$escaped'";
        }

        // Remove any existing DEFAULT clause then append the new one
        $this->modifiers = array_values(array_filter(
            $this->modifiers,
            fn($m) => !preg_match('/^DEFAULT\b/i', $m)
        ));
        $this->modifiers[] = "DEFAULT $rendered";

        return $this;
    }

    public function on(string $action, mixed $value): self
    {
        $default = $value;
    
        // If ON already exists, replace it
        if (preg_match('/ON\s+\'?.+?\'?/', implode(" ", $this->modifiers))) {
            $this->modifiers = preg_replace('/DEFAULT\s+\'?.+?\'?/', "ON $action '$default'", $this->modifiers);
        } else {
            $this->modifiers[] = "ON $action '$default'";
        }
    
        return $this;
    }

    public function useCurrent()
    {
        return $this->default('CURRENT_TIMESTAMP');
    }

    public function useCurrentOnUpdate()
    {
        return $this->on('UPDATE', 'CURRENT_TIMESTAMP');
    }

    public function autoIncrement(): self
    {
        $this->addModifier("AUTO_INCREMENT");
        return $this;
    }

    public function primary(): self
    {
        $this->addModifier("PRIMARY KEY");
        return $this;
    }

    public function after(string $column): self
    {
        // If AFTER already exists, replace it
        if (preg_match('/AFTER\s+\'?.+?\'?/', implode(" ", $this->modifiers))) {
            $this->modifiers = preg_replace('/AFTER\s+\'?.+?\'?/', "AFTER $column", $this->modifiers);
        } else {
            $this->modifiers[] = "AFTER $column";
        }
    
        return $this;
    }

    public function comment(string $text): self
    {
        $this->commentText = $text;

        // If COMMENT already exists, replace it
        if (preg_match('/COMMENT\s+\'?.+?\'?/', implode(" ", $this->modifiers))) {
            $this->modifiers = preg_replace('/COMMENT\s+\'?.+?\'?/', "COMMENT '$text'", $this->modifiers);
        } else {
            $this->modifiers[] = "COMMENT '$text'";
        }

        return $this;
    }

    public function charset(string $charset): self
    {
        // If CHARACTER SET already exists, replace it
        if (preg_match('/CHARACTER SET\s+\'?.+?\'?/', implode(" ", $this->modifiers))) {
            $this->modifiers = preg_replace('/CHARACTER SET\s+\'?.+?\'?/', "CHARACTER SET $charset", $this->modifiers);
        } else {
            $this->modifiers[] = "CHARACTER SET $charset";
        }
    
        return $this;
    }

    public function collation(string $collation): self
    {
        // If COLLATE already exists, replace it
        if (preg_match('/COLLATE\s+\'?.+?\'?/', implode(" ", $this->modifiers))) {
            $this->modifiers = preg_replace('/COLLATE\s+\'?.+?\'?/', "COLLATE $collation", $this->modifiers);
        } else {
            $this->modifiers[] = "COLLATE $collation";
        }
    
        return $this;
    }

    public function virtualAs(string $expression): self
    {
        $modifier = "AS ($expression) VIRTUAL";

        // Remove any existing VIRTUAL definition before adding a new one
        $this->modifiers = array_filter($this->modifiers, fn($m) => !preg_match('/AS\s*\(.*?\)\s*VIRTUAL/', $m));

        $this->modifiers[] = $modifier;

        return $this;
    }

    public function storedAs(string $expression): self
    {
        $modifier = "AS ($expression) STORED";

        // Remove any existing STORED definition before adding a new one
        $this->modifiers = array_filter($this->modifiers, fn($m) => !preg_match('/AS\s*\(.*?\)\s*STORED/', $m));

        $this->modifiers[] = $modifier;

        return $this;
    }

    public function change(): self
    {
        $this->isChange = true;
        return $this;
    }

    public function getDefinition(): string
    {
        $comment = null;
        $nullConstraint = null;
        $positionClause = null;
        $otherModifiers = [];

        foreach ($this->modifiers as $modifier) {
            // Position clause (FIRST | AFTER col_name) is part of the
            // ALTER TABLE ADD/MODIFY syntax, NOT column_definition, so it
            // MUST come at the very end. MariaDB strictly enforces this
            // and 1064s on `... AFTER x NULL`; MySQL is lenient and
            // accepts either order — which is how this bug shipped.
            if (stripos($modifier, 'AFTER ') === 0 || strcasecmp($modifier, 'FIRST') === 0) {
                $positionClause = $modifier;
            } elseif (stripos($modifier, 'COMMENT') === 0) {
                $comment = $modifier;
            } elseif (preg_match('/^(NOT\s+)?NULL$/i', $modifier)) {
                $nullConstraint = $modifier;
            } else {
                $otherModifiers[] = $modifier;
            }
        }

        $orderedModifiers = array_merge(
            $otherModifiers,
            array_filter([$nullConstraint, $comment, $positionClause])
        );

        return trim("$this->definition " . implode(" ", $orderedModifiers));
    }

    protected function addModifier(string $modifier): self
    {
        if (!in_array($modifier, $this->modifiers, true)) {
            $this->modifiers[] = $modifier;
        }
        return $this;
    }

    public function toSql(): string
    {
        return $this->getDefinition();
    }
}
