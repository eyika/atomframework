<?php
namespace Eyika\Atom\Framework\Support\View\BladeHelpers\Contracts;

interface AuthorizationLogic
{
    public function __invoke(string $action, $subject = null): bool;
}
