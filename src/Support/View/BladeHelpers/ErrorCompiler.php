<?php
namespace Eyika\Atom\Framework\Support\View\BladeHelpers;

use Eyika\Atom\Framework\Support\Arr;

class ErrorCompiler
{
    private array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    public function __invoke($key = null)
    {
        if (Arr::keyExists($this->errors, $key)) {
            return $this->errors[$key];
        }
    
        return false;
    }
}
