<?php

namespace Eyika\Atom\Framework\Foundation;

use Dotenv\Dotenv;
use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\NamespaceHelper;

class Application implements ApplicationInterface
{
    use ServiceContainer;
    public const GLOBAL_VARS = [ 
        'base_path' => 'base_path',
        'framework_namespace' => 'framework_namespace',
        'project_namespace' => 'project_namespace'
    ];

    public function __construct(string $basepath)
    {
        $GLOBALS[self::GLOBAL_VARS['base_path']] = $basepath;
        $GLOBALS[self::GLOBAL_VARS['framework_namespace']] = NamespaceHelper::getBaseNamespace();
        $GLOBALS[self::GLOBAL_VARS['project_namespace']] = NamespaceHelper::getBaseNamespace("$basepath/composer.json");

        $dotenv = strtolower(PHP_OS_FAMILY) === 'windows' ? Dotenv::createImmutable(base_path()."\\") : Dotenv::createImmutable(base_path()."/");
        $dotenv->load();
        Facade::pushDefaultAliases();
        // $dotenv->required(['DB_USERNAME'])->notEmpty(); ///TODO: get required env keys from config if set
        // print_r($_ENV);
    }
}
