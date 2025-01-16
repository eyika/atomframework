<?php
namespace Eyika\Atom\Framework\Support\View\BladeHelpers;

use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\View\BladeHelpers\Contracts\AuthorizationLogic;

class ValidatePermissions
{
    private array $permissions;
    private AuthorizationLogic|null $authorizationLogic;

    public function __construct(array $permissions, AuthorizationLogic|null $authorizationLogic = null)
    {
        $this->permissions = $permissions;
        $this->authorizationLogic = $authorizationLogic;
    }

    public function __invoke(string $action, $subject = null): bool
    {
        if ($this->authorizationLogic) {
            return ($this->authorizationLogic)($action, $subject);
        }

        // Default validation logic: check if the action exists in permissions
        return Arr::exists($this->permissions, $action);
    }
}
