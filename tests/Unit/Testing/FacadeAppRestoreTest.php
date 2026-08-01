<?php

namespace Eyika\Atom\Framework\Tests\Unit\Testing;

use Eyika\Atom\Framework\Foundation\Application;
use Eyika\Atom\Framework\Support\Facade\Facade;
use Eyika\Atom\Framework\Support\Testing\TestCase as FrameworkTestCase;
use PHPUnit\Framework\TestCase;

/**
 * A probe that extends the SHIPPED framework base but stubs bootApplication() (so we don't need a
 * bootstrap/app.php fixture), preserving the real contract: booting points the global facade app
 * at the booted container. drive()/undrive() expose the protected lifecycle hooks.
 */
class FacadeRestoreProbe extends FrameworkTestCase
{
    public Application $bootApp;

    protected function bootApplication(): Application
    {
        Facade::setFacadeApplication($this->bootApp); // mirror the real bootApplication()
        return $this->bootApp;
    }

    public function drive(): void
    {
        $this->setUp();
    }

    public function undrive(): void
    {
        $this->tearDown();
    }
}

/**
 * Regression: Support\Testing\TestCase pointed the global (static) facade app at its own booted
 * container and never restored it. A test running AFTER one (e.g. a DB-only base that sets the
 * facade app only on its first build) then had App::make()/facades resolving from the wrong
 * container — $this->app->instance(...) overrides were silently ignored (order-dependent). The
 * base must restore the prior facade app on teardown.
 */
class FacadeAppRestoreTest extends TestCase
{
    private ?Application $originalFacadeApp = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Don't leak our own mutations into sibling tests.
        $this->originalFacadeApp = Facade::getFacadeApplication();
    }

    protected function tearDown(): void
    {
        Facade::setFacadeApplication($this->originalFacadeApp);
        parent::tearDown();
    }

    public function test_teardown_restores_the_previous_facade_application(): void
    {
        $before = new Application($GLOBALS['base_path'], true);
        Facade::setFacadeApplication($before);

        $probe = new FacadeRestoreProbe('drive');
        $probe->bootApp = new Application($GLOBALS['base_path'], true);

        $probe->drive(); // setUp(): captures $before, then boots → facade app = probe->bootApp
        $this->assertSame($probe->bootApp, Facade::getFacadeApplication(), 'booting points the facade at the booted app');

        $probe->undrive(); // tearDown(): restores the app that was active before the probe ran
        $this->assertSame($before, Facade::getFacadeApplication(), 'teardown must restore the prior facade app');
    }

    public function test_teardown_can_restore_a_null_prior_state(): void
    {
        // If NOTHING had set a facade app before the test, teardown must clear it again rather
        // than leave the probe's app dangling (proves setFacadeApplication accepts null).
        Facade::setFacadeApplication(null);

        $probe = new FacadeRestoreProbe('drive');
        $probe->bootApp = new Application($GLOBALS['base_path'], true);

        $probe->drive();
        $this->assertSame($probe->bootApp, Facade::getFacadeApplication());

        $probe->undrive();
        $this->assertNull(Facade::getFacadeApplication(), 'teardown must restore the null prior state');
    }
}
