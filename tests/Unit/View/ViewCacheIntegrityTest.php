<?php

namespace Eyika\Atom\Framework\Tests\Unit\View;

use Eyika\Atom\Framework\Support\Config;
use Eyika\Atom\Framework\Support\View\Exceptions\ViewCompilationException;
use Eyika\Atom\Framework\Support\View\Twig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Reported by a downstream consumer, traced from a production symptom: **a compiled template that
 * lands empty is cached as valid for ever, and the render fails silently.**
 *
 * Their account of the mechanism is exactly right. `cache()` wrote with
 * `file_put_contents($path, $code, LOCK_EX)`, which looks safe and is not: `LOCK_EX` excludes other
 * *writers*, and the reader here is `require`, which takes no lock at all. `file_put_contents` also
 * TRUNCATES before writing, so the artifact passes through a zero-byte state on every recompile —
 * and `isFresh()` compared only existence and mtime, so a truncated file was *newer than its
 * source* and counted as fresh from then on. `make()` `require`s it inside `ob_start()` and returns
 * `''` with nothing raised.
 *
 * Downstream that sent a correctly-addressed, correctly-titled password-reset email with **no
 * body**, because the subject is set separately from the rendered template — and it burnt a real
 * merchant's throttle allowance on empty retries.
 *
 * Both fixes they proposed are implemented, and a third defect in the same four lines is fixed with
 * them: `filemtime($source) > $compiledAt` used a strict `>`, and `filemtime()` has one-second
 * resolution, so an edit made in the same second as the compile was silently missed — reliably, for
 * the whole of that second, which is how long an edit-and-refresh cycle takes.
 */
class ViewCacheIntegrityTest extends TestCase
{
    private string $viewDir = '';
    private string $cacheDir = '';
    private array $configSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configSnapshot = Config::snapshot();

        $base = sys_get_temp_dir() . '/atom_view_cache_' . getmypid() . '_' . bin2hex(random_bytes(3));
        $this->viewDir = $base . '/views';
        $this->cacheDir = $base . '/compiled';
        mkdir($this->viewDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);

        Config::set('view.compiled', $this->cacheDir);
        Config::set('view.cache', true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->cacheDir, $this->viewDir] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
        @rmdir(dirname($this->viewDir));

        Config::restore($this->configSnapshot);
        parent::tearDown();
    }

    private function writeView(string $name, string $contents): string
    {
        $path = $this->viewDir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    private function render(string $name): string
    {
        return (string) Twig::make($name, $this->viewDir . '/', [], true);
    }

    private function compiledPath(string $name): string
    {
        $method = new ReflectionMethod(Twig::class, 'cacheKey');
        $method->setAccessible(true);

        return $this->cacheDir . '/' . $method->invoke(null, $name);
    }

    // ---------------------------------------------------------------- the reported bug

    public function test_a_template_renders(): void
    {
        $this->writeView('hello.php', 'Hello world');

        $this->assertSame('Hello world', $this->render('hello.php'));
    }

    /**
     * The heart of it: a zero-byte artifact must never be served as a cache hit, however new it is.
     */
    public function test_a_zero_byte_compiled_template_is_recompiled_rather_than_served(): void
    {
        $this->writeView('reset.php', 'Your reset link: {!! $link !!}');
        Twig::make('reset.php', $this->viewDir . '/', ['link' => 'https://example.test/r/first'], true);

        // Stand in for an interrupted write: truncate the artifact and make it the newest thing
        // on disk, which is precisely the state the old code called "fresh".
        $compiled = $this->compiledPath('reset.php');
        file_put_contents($compiled, '');
        touch($compiled, time() + 10);
        $this->assertSame(0, filesize($compiled), 'the artifact was not actually emptied');

        $output = (string) Twig::make('reset.php', $this->viewDir . '/', ['link' => 'https://example.test/r/abc'], true);

        $this->assertNotSame('', $output, 'an empty artifact was served as a valid cache hit');
        $this->assertStringContainsString('https://example.test/r/abc', $output);
    }

    /** And the empty artifact must not survive — the cache has to heal, not re-fail next time. */
    public function test_the_cache_heals_after_an_empty_artifact(): void
    {
        $this->writeView('body.php', 'a real body');
        $this->render('body.php');

        $compiled = $this->compiledPath('body.php');
        file_put_contents($compiled, '');
        touch($compiled, time() + 10);

        $this->render('body.php');

        $this->assertGreaterThan(0, filesize($compiled), 'the artifact is still empty after a render');
        $this->assertSame('a real body', $this->render('body.php'));
    }

    // ---------------------------------------------------------------- the write is atomic

    /**
     * No reader may observe a partial file, which means the destination must never be truncated in
     * place. Asserted structurally: the write goes to a temp path and is renamed over.
     */
    public function test_the_compiled_artifact_is_written_by_rename(): void
    {
        $this->assertTrue(
            method_exists(Twig::class, 'writeAtomically'),
            'compiled templates are not written through an atomic seam'
        );

        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Support/View/Twig.php');

        $this->assertStringContainsString('rename($temp, $path)', $source);
        $this->assertStringNotContainsString(
            "file_put_contents(\n            \$cached_file,",
            $source,
            'the compiled file is still written in place, so a reader can see it truncated'
        );
    }

    /** The temp file must be a sibling: a rename across filesystems is a copy, and not atomic. */
    public function test_the_temp_file_is_written_beside_its_destination(): void
    {
        $this->writeView('sibling.php', 'content');
        $this->render('sibling.php');

        $this->assertSame(
            [],
            glob($this->cacheDir . '/*.tmp') ?: [],
            'a temp file was left behind in the cache directory'
        );
    }

    // ---------------------------------------------------------------- same-second edits

    /**
     * `filemtime()` has one-second resolution, so a strict `>` missed any edit made in the same
     * second as the compile — which is the normal speed of edit-and-refresh.
     */
    public function test_an_edit_in_the_same_second_is_not_missed(): void
    {
        $view = $this->writeView('page.php', 'first version');
        $this->assertSame('first version', $this->render('page.php'));

        // Same second, both files: the old comparison called this fresh.
        $now = time();
        file_put_contents($view, 'second version');
        touch($view, $now);
        touch($this->compiledPath('page.php'), $now);

        $this->assertSame('second version', $this->render('page.php'), 'a same-second edit was missed');
    }

    /** An untouched template must still hit the cache — the fix must not disable caching. */
    public function test_an_unchanged_template_is_still_cached(): void
    {
        $view = $this->writeView('stable.php', 'unchanged');
        $this->render('stable.php');

        $compiled = $this->compiledPath('stable.php');

        // Source older than the artifact: the ordinary steady state.
        touch($view, time() - 60);
        touch($compiled, time());
        $before = filemtime($compiled);

        $this->render('stable.php');

        $this->assertSame($before, filemtime($compiled), 'an unchanged template was recompiled');
    }

    // ---------------------------------------------------------------- the render scope

    /**
     * `extract()` ran where `$file`, `$paths`, `$data`, `$get_output` and `$cached_file` were live
     * locals, and `EXTR_SKIP` means the CALLER loses those collisions — silently.
     *
     * `data` is an ordinary name for a view variable. `{{ $data }}` rendered the framework's own
     * parameter array, which is not even reliably quiet: `e()` then fails on an array with a
     * TypeError pointing at `helpers.php`, so the engine's bug reads as the caller's template.
     */
    public function test_a_view_variable_is_not_shadowed_by_the_renderers_locals(): void
    {
        $this->writeView('scope.php', 'data=[{{ $data }}] file=[{{ $file }}] paths=[{{ $paths }}] out=[{{ $get_output }}]');

        $output = (string) Twig::make('scope.php', $this->viewDir . '/', [
            'data' => 'MY-DATA',
            'file' => 'MY-FILE',
            'paths' => 'MY-PATHS',
            'get_output' => 'MY-OUT',
        ], true);

        $this->assertSame('data=[MY-DATA] file=[MY-FILE] paths=[MY-PATHS] out=[MY-OUT]', $output);
    }

    /** The renderer's own parameters are reserved, and saying so beats rendering the wrong value. */
    public function test_a_reserved_variable_name_is_refused_by_name(): void
    {
        $this->writeView('reserved.php', '{{ $__atom_view }}');

        $this->expectException(ViewCompilationException::class);
        $this->expectExceptionMessageMatches('/__atom_view.*reserved/');

        Twig::make('reserved.php', $this->viewDir . '/', ['__atom_view' => 'x'], true);
    }

    // ---------------------------------------------------------------- require, not require_once

    /**
     * A guard rather than a regression: `make()` must use `require`, in both branches.
     *
     * `require_once` would execute the compiled file on the first render in a process and then
     * return `true` without executing it again — so under a long-running `queue:work --daemon` the
     * FIRST email of each worker lifetime would render and every one after it would be blank. That
     * is almost exactly the empty-artifact symptom, from an unrelated cause, which is why it is
     * worth pinning: it reads like a safe optimisation.
     */
    public function test_the_same_template_renders_twice_in_one_process(): void
    {
        $this->writeView('twice.php', 'rendered: {{ $n }}');

        $first = (string) Twig::make('twice.php', $this->viewDir . '/', ['n' => 'one'], true);
        $second = (string) Twig::make('twice.php', $this->viewDir . '/', ['n' => 'two'], true);

        $this->assertSame('rendered: one', $first, 'the first render was wrong');
        $this->assertNotSame('', $second, 'the second render was BLANK — require_once would do this');
        $this->assertSame('rendered: two', $second, 'the second render did not see its own data');
    }

    /** The same template, same data, twice — both non-empty and equal. */
    public function test_repeated_renders_are_stable(): void
    {
        $this->writeView('stable-render.php', 'always the same');

        $first = (string) Twig::make('stable-render.php', $this->viewDir . '/', [], true);
        $second = (string) Twig::make('stable-render.php', $this->viewDir . '/', [], true);

        $this->assertNotSame('', $first);
        $this->assertSame($first, $second);
    }

    public function test_the_renderer_uses_require_rather_than_require_once(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/src/Support/View/Twig.php');

        $this->assertStringNotContainsString(
            'require_once $__atom_view',
            $source,
            'require_once would render only the first time in a long-running process'
        );
        $this->assertStringContainsString('require $__atom_view;', $source);
    }

    /** A changed include must invalidate too — that was already true and must stay true. */
    public function test_a_changed_include_still_invalidates_the_cache(): void
    {
        $this->writeView('partial.php', 'original partial');
        $this->writeView('host.php', "{% include 'partial.php' %}");
        $this->assertStringContainsString('original partial', $this->render('host.php'));

        sleep(1);
        $this->writeView('partial.php', 'edited partial');

        $this->assertStringContainsString('edited partial', $this->render('host.php'));
    }
}
