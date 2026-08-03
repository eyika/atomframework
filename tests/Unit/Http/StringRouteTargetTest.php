<?php

namespace Eyika\Atom\Framework\Tests\Unit\Http;

use Eyika\Atom\Framework\Exceptions\Http\NotFoundHttpException;
use Eyika\Atom\Framework\Http\Route;
use PHPUnit\Framework\TestCase;

/** Exposes the protected resolver so the path handling can be tested directly. */
class StringRouteTargetProbe extends Route
{
    public static function render(string $callback): mixed
    {
        return static::includeRouteTarget($callback);
    }
}

/**
 * Covers BUG-15 and SEC-29. A string route target was rendered with
 * `include_once __DIR__ . "/$callback"`, where __DIR__ is the framework's own src/Http — so the
 * file was looked up inside vendor/, where an application's file can never be. `include_once`
 * also returned true instead of re-rendering on a second hit (invisible under FPM, fatal under a
 * persistent worker), and the path was interpolated with no traversal guard.
 */
class StringRouteTargetTest extends TestCase
{
    private string $base;
    private mixed $origBasePath;

    protected function setUp(): void
    {
        $this->origBasePath = $GLOBALS['base_path'] ?? null;

        $this->base = sys_get_temp_dir() . '/atom_strroute_' . uniqid();
        @mkdir($this->base . '/views', 0777, true);
        $GLOBALS['base_path'] = $this->base;

        file_put_contents($this->base . '/views/home.php', '<?php return "rendered";');

        // A file OUTSIDE the app base, to prove traversal is refused.
        file_put_contents(dirname($this->base) . '/atom_strroute_outside.php', '<?php return "escaped";');
    }

    protected function tearDown(): void
    {
        @unlink($this->base . '/views/home.php');
        @rmdir($this->base . '/views');
        @rmdir($this->base);
        @unlink(dirname($this->base) . '/atom_strroute_outside.php');

        if ($this->origBasePath === null) {
            unset($GLOBALS['base_path']);
        } else {
            $GLOBALS['base_path'] = $this->origBasePath;
        }

        parent::tearDown();
    }

    public function test_a_target_resolves_against_the_application_base(): void
    {
        $this->assertSame('rendered', StringRouteTargetProbe::render('views/home.php'));
    }

    public function test_a_leading_slash_is_tolerated(): void
    {
        $this->assertSame('rendered', StringRouteTargetProbe::render('/views/home.php'));
    }

    /**
     * include_once returned true for every hit after the first. Under a persistent worker that
     * means the page renders once and then silently stops rendering.
     */
    public function test_the_same_target_renders_every_time(): void
    {
        $this->assertSame('rendered', StringRouteTargetProbe::render('views/home.php'));
        $this->assertSame('rendered', StringRouteTargetProbe::render('views/home.php'));
        $this->assertSame('rendered', StringRouteTargetProbe::render('views/home.php'));
    }

    public function test_traversal_outside_the_application_is_refused(): void
    {
        $this->expectException(NotFoundHttpException::class);

        StringRouteTargetProbe::render('../atom_strroute_outside.php');
    }

    public function test_a_missing_target_is_a_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        StringRouteTargetProbe::render('views/does-not-exist.php');
    }

    public function test_a_directory_is_not_renderable(): void
    {
        $this->expectException(NotFoundHttpException::class);

        StringRouteTargetProbe::render('views');
    }
}
