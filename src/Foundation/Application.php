<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class Application implements ApplicationInterface
{
    use ServiceContainer;

    public function __construct(string $basepath)
    {
        $GLOBALS['base_path'] = $basepath;
        $GLOBALS['framework_namespace'] = NamespaceHelper::getBaseNamespace();
        $GLOBALS['project_namespace'] = NamespaceHelper::getBaseNamespace("$basepath/composer.json");
    }
}
