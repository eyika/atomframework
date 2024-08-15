<?php
namespace Eyika\Atom\Framework\Support;

use Exception;

class Setup
{
    public static function postCreateProject($event)
    {
        // Code to be executed after the project is created
        echo "Running post-create-project tasks...\n";
        // Your setup logic here
    }

    public static function postInstall($event)
    {
        // Code to be executed after `composer install`
        echo "Running post-install tasks...\n";
        // Your install logic here
    }

    public static function postUpdate($event)
    {
        // Code to be executed after `composer update`
        echo "Running post-update tasks...\n";
        // Your update logic here
    }
}