<?php

namespace Eyika\Atom\Framework\Support\Facade;

use Eyika\Atom\Framework\Support\View\Blade as ViewBlade;
use Eyika\Atom\Framework\Support\View\BladeHelpers\Contracts\AuthorizationLogic;

/**
 * @method static ViewBlade instance()
 * @method static array atomErrors()
 * @method static ViewBlade atomSetErrors(array $errors)
 * @method static array atomOldInputs()
 * @method static ViewBlade atomSetOldInputs(array $oldInputs)
 * @method static ViewBlade atomSetValidationErrors(array $errors)
 * @method static ViewBlade atomSetPermissions(array $permissions, AuthorizationLogic|null $customAuthorizationLogic = null)
 */
class Blade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'view.blade';
    }
}
