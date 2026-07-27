<?php

namespace Eyika\Atom\Framework\Foundation\Console\Commands\Make;

class Middleware extends BaseMake
{
    public string $signature = 'make:middleware';
    protected string $type = 'Middleware';
    protected string $directory = 'app/Http/Middlewares';
    protected string $stub = <<<'EOT'
<?php

namespace App\Http\Middlewares;

use Closure;
use Eyika\Atom\Framework\Http\BaseResponse;
use Eyika\Atom\Framework\Http\Contracts\MiddlewareInterface;
use Eyika\Atom\Framework\Http\Request;

class {{name}} implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): BaseResponse
    {
        return $next($request);
    }
}
EOT;
}
