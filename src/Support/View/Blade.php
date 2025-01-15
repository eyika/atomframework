<?php
namespace Eyika\Atom\Framework\Support\View;

use eftec\bladeone\BladeOne;
use eftec\bladeone\BladeOneCache;
use eftec\bladeonehtml\BladeOneHtml;
use Eyika\Atom\Framework\Http\Csrf;
use Eyika\Atom\Framework\Support\View\BladeHelpers\Contracts\AuthorizationLogic;
use Eyika\Atom\Framework\Support\View\BladeHelpers\ErrorCompiler;
use Eyika\Atom\Framework\Support\View\BladeHelpers\ValidatePermissions;

class Blade extends BladeOne
{
    use BladeOneCache, BladeOneHtml;

    public array $oldInputs;
    /**
     * Bob the constructor.
     * The folder at $compiledPath is created in case it doesn't exist.
     *
     * @param string|array $templatePath If null then it uses (caller_folder)/views
     * @param string       $compiledPath If null then it uses (caller_folder)/compiles
     * @param int          $mode         =[BladeOne::MODE_AUTO,BladeOne::MODE_DEBUG,BladeOne::MODE_FAST,BladeOne::MODE_SLOW][$i]
     */
    public function __construct($templatePath = null, $compiledPath = null, array $oldInputs = [])
    {
        if (!$mode = config('view.mode')) {
            $mode = env('APP_ENV') == 'local' ? BladeOne::MODE_DEBUG : BladeOne::MODE_FAST;
        }
        $templatePath = $templatePath ?? config('view.paths');
        $compiledPath = $compiledPath ?? config('view.compiled');

        if (!file_exists($compiledPath)) {
            mkdir($compiledPath, 0744, true);
        }

        $this->setBaseUrl(config('app.url'));
        $this->oldInputs = $oldInputs;
        logger()->info("constructor inputs are: ", $this->oldInputs);

        parent::__construct($templatePath, $compiledPath, $mode);
    }

    public function atomSetValidationErrors(array $errors)
    {
        $this->setErrorFunction((new ErrorCompiler($errors)));
    }

    public function atomSetPermissions(array $permissions, AuthorizationLogic|null $customAuthorizationLogic = null)
    {
        $this->setCanFunction((new ValidatePermissions($permissions, $customAuthorizationLogic)));
    }

    public function compileCsrf_Token(): string
    {
        return Csrf::setCsrfToken();
    }

    public function compileDebugbarHead(): string
    {
        return debugbar()->renderHead();
    }

    public function compileDebugbarBody(): string
    {
        return debugbar()->render();
    }

    public function runtimeOld(array $names): string
    {
        $name = $names[0] ?? '';
        logger()->info("name is $name and old inputs are: ", $this->oldInputs);

        return array_key_exists($name, $this->oldInputs) ? (string)$this->oldInputs[$name] : '';
    }
}
