<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Rule extends BaseMake
{
    public string $signature = 'make:rule';
    protected string $type = 'Rule';
    protected string $directory = 'app/Rules';
    protected string $stub = <<<'EOT'
<?php

namespace App\Rules;

class {{name}}
{
    /**
     * Determine if the validation rule passes.
     */
    public function passes(string $attribute, mixed $value): bool
    {
        return true;
    }

    /**
     * The validation error message.
     */
    public function message(): string
    {
        return 'The :attribute is invalid.';
    }
}
EOT;
}
