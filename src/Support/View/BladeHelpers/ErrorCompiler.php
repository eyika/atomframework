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

    public function __invoke($key = null, $errorValueType = '_string')
    {
        if (Arr::keyExists($this->errors, $key)) {
            $this->errors[$key][] = $this->errors[$key][0];
            if ($errorValueType == '_string') {
                return is_array($this->errors[$key]) ? implode(', ', $this->errors[$key]) : $this->errors[$key];
            }

            return Arr::wrap($this->errors[$key]);
        }
    
        return false;
    }
}
