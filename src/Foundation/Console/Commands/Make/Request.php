<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Request extends BaseMake
{
    public string $signature = 'make:request';
    protected string $type = 'Request';
    protected string $directory = 'app/Http/Requests';
    protected string $stub = <<<'EOT'
<?php

namespace App\Http\Requests;

class {{name}}
{
    /**
     * Validation rules for this request.
     */
    public function rules(): array
    {
        return [
            // 'field' => 'required|string',
        ];
    }
}
EOT;
}
