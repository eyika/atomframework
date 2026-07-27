<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Mail extends BaseMake
{
    public string $signature = 'make:mail';
    protected string $type = 'Mail';
    protected string $directory = 'app/Mail';
    protected string $stub = <<<'EOT'
<?php

namespace App\Mail;

class {{name}}
{
    /**
     * Build the message — return the rendered body (e.g. a view()).
     */
    public function build(): string
    {
        return '';
    }
}
EOT;
}
