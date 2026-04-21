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

    public function nullable(): self
    {
        $this->addModifier("NULL");
        return $this;
    }

    public function notNullable(): self
    {
        $this->addModifier("NOT NULL");
        return $this;
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
        $otherModifiers = [];
    
        foreach ($this->modifiers as $modifier) {
            if (stripos($modifier, 'COMMENT') === 0) {
                $comment = $modifier;
            } elseif (stripos($modifier, 'NULL') !== false) {
                $nullConstraint = $modifier;
            } else {
                $otherModifiers[] = $modifier;
            }
        }
    
        // Construct the final definition order
        $orderedModifiers = array_merge($otherModifiers, array_filter([$nullConstraint, $comment]));
    
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
