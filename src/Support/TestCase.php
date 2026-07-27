<?php
namespace Eyika\Atom\Framework\Support;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Facade\Facade;

require_once __DIR__."/../helpers.php";

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Manually bootstrap the application
        $this->app = require dirname(__DIR__) . '/bin/console_bootstrap/app';

        // Set the application instance for Facades
        Facade::setFacadeApplication($this->app);

        // Ensure service providers and configurations are loaded
        // $this->app->boot();
    }

    /**
     * This may be needed to parse the input command line args into current process
     * 
     * Will be removed eventually if everthing works well without it
     */
    private function extractArgs()
    {
        $env = getenv();

        foreach (Application::GLOBAL_VARS as $key => $var) {
            if ($_var = getenv($var)) {
                $GLOBALS[$var] = $_var;
            }
        }
        // Explicitly push environment variables into $_ENV
        foreach ($env as $key => $value) {
            if (isset($_ENV[$key]) === false) {
                $_ENV[$key] = $value;
            }
        }
    }
}
