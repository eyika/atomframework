<?php

namespace Eyika\Atom\Framework\Support\View;

use Eyika\Atom\Framework\Support\Arr;
use Eyika\Atom\Framework\Support\View\Exceptions\ViewCompilationException;
use Eyika\Atom\Framework\Support\View\Exceptions\ViewNotFoundException;

/**
 * A small, dependency-free template engine (the "basic" alternative to the BladeOne-backed
 * {@see Blade} engine). Used for transactional mail and as the view engine when
 * `view.use_advance_engine` is false.
 *
 * Output rules — aligned with Blade/Laravel so muscle memory is safe:
 *
 *   {{ expr }}    HTML-escaped output      (safe default — use for anything data-driven)
 *   {!! expr !!}  raw, unescaped output    (opt in explicitly; you own the safety)
 *   {{{ expr }}}  HTML-escaped output      (legacy alias, kept for back-compat)
 *   {% php %}     raw PHP                  (control flow: foreach/if/endforeach/…)
 *   {% extends f %} / {% include f %}      inline another template
 *   {% block n %}…{% endblock %} / {% yield n %}   layout sections
 *
 * Expressions inside {{ }} / {!! !!} are literal PHP — `{{ $user->name }}`, not `{{ user.name }}`.
 * (The old engine rewrote every `.` to `->`, which silently corrupted dotted strings such as
 * `config('mail.from')`; that magic is gone.)
 */
class Twig
{
    /** Per-compile block table. Reset at the start of every compile — never shared across renders. */
    protected static array $blocks = [];

    /** Absolute paths of every source touched by the current compile (template + includes), for cache invalidation. */
    protected static array $sources = [];

    protected static ?string $cache_path = null;

    /** View roots the template name is resolved against. */
    protected static array $paths = [];

    /**
     * Render a template.
     *
     * @param  string        $file        Template name, resolved against $paths.
     * @param  array|string  $paths       One or more view roots.
     * @param  array         $data        Variables extracted into template scope.
     * @param  bool          $get_output  true → return the rendered string; false → echo it.
     * @return string|null   The rendered output when $get_output is true, otherwise null.
     *
     * @throws ViewNotFoundException When the template or an include/extends target can't be resolved.
     */
    public static function make(string $file, array|string $paths = '/', array $data = [], bool $get_output = false): ?string
    {
        self::$paths = Arr::wrap($paths);
        self::$cache_path = config('view.compiled');

        return self::renderIsolated(self::cache($file), $data, $get_output);
    }

    /**
     * Execute a compiled template in a scope of its own.
     *
     * `extract()` used to run inside `make()`, where `$file`, `$paths`, `$data`, `$get_output` and
     * `$cached_file` were all live locals. With `EXTR_SKIP` the caller's values LOSE those name
     * collisions silently: a template using `{{ $file }}` rendered the template's own name, and
     * `{{ $data }}` rendered the framework's parameter array — which is not even reliably silent,
     * since `e()` then fails on an array with a TypeError pointing at `helpers.php`. `data` is a
     * perfectly ordinary thing for someone to call their view variable.
     *
     * Rendering here leaves only this method's own three parameters in scope, and they are prefixed
     * so that no plausible view variable can reach them. The guard below closes even that, because
     * the one outcome worth ruling out completely is rendering a different value than the caller
     * passed without saying so.
     *
     * **`require`, never `require_once`** — in both branches, and this is load-bearing rather than
     * stylistic. `require_once` would execute the compiled file on the first render in a process and
     * then return `true` without executing it again, so under a long-running `queue:work --daemon`
     * the FIRST email of each worker lifetime would render and every one after it would be blank.
     * That is almost exactly the symptom of the empty-artifact bug, from a completely unrelated
     * cause — and it reads like a harmless optimisation, which is what makes it worth saying out
     * loud. `ViewCacheIntegrityTest` renders the same template twice in one process to hold it.
     */
    private static function renderIsolated(string $__atom_view, array $__atom_data, bool $__atom_capture): ?string
    {
        foreach (['__atom_view', '__atom_data', '__atom_capture'] as $__atom_reserved) {
            if (array_key_exists($__atom_reserved, $__atom_data)) {
                throw new ViewCompilationException(
                    $__atom_view,
                    "[{$__atom_reserved}] is reserved by the view renderer; rename that view variable"
                );
            }
        }

        extract($__atom_data, EXTR_SKIP);

        if (!$__atom_capture) {
            require $__atom_view;
            return null;
        }

        ob_start();
        require $__atom_view;
        return ob_get_clean();
    }

    /**
     * Compile $file into a cached PHP file and return its path. Recompiles only when the cache is
     * missing or stale — where "stale" means any source that fed it (template + its includes) is
     * newer than the compiled artifact. Set `view.cache` to false to force a recompile every render.
     */
    protected static function cache(string $file): string
    {
        if (!is_dir(self::$cache_path)) {
            mkdir(self::$cache_path, 0755, true);
        }

        $cached_file = self::$cache_path . '/' . self::cacheKey($file);

        // Reset per-compile state up front so nothing bleeds across renders in a long-lived worker.
        self::$blocks = [];
        self::$sources = [];
        $code = self::includeFiles($file); // resolves + records every source, throws if any is missing

        if (self::isFresh($cached_file)) {
            return $cached_file; // cache hit — skip the compile + write entirely
        }

        $code = self::compileCode($code);
        self::writeAtomically(
            $cached_file,
            '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code
        );

        return $cached_file;
    }

    /**
     * Write the compiled artifact so no reader can ever observe a partial one.
     *
     * The previous `file_put_contents($path, $code, LOCK_EX)` looked safe and was not. `LOCK_EX`
     * excludes other *writers*; it does nothing about a reader, and the reader here is `require`,
     * which takes no lock at all. Worse, `file_put_contents` TRUNCATES before writing, so the file
     * passes through a **zero-byte** state on every recompile — and a `require` landing in that
     * window yields an empty render with no error anywhere.
     *
     * That is not theoretical. It reached production as a password-reset email that was correctly
     * addressed and correctly titled with **no body**, because the subject is set separately from
     * the rendered template; and because `isFresh()` then judged the truncated file newer than its
     * source, the empty artifact was served as valid from then on.
     *
     * Writing to a sibling temp file and renaming fixes it: `rename()` over an existing path is
     * atomic on the same filesystem, so a reader sees either the old complete file or the new
     * complete file. The temp file is deliberately created in the SAME directory — a rename across
     * filesystems is a copy, which is not atomic and would reintroduce exactly this window.
     */
    protected static function writeAtomically(string $path, string $contents): void
    {
        $temp = $path . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temp, $contents) === false) {
            @unlink($temp);
            throw new ViewCompilationException($path, 'the compiled template could not be written');
        }

        if (@rename($temp, $path)) {
            return;
        }

        // A rename can still lose on Windows if another process holds the destination open at that
        // instant. Falling back to a direct write keeps rendering working — it is the behaviour
        // that shipped before — and the zero-byte guard in isFresh() catches the artifact if this
        // write is the one that gets interrupted.
        @unlink($temp);

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new ViewCompilationException($path, 'the compiled template could not be written');
        }
    }

    /** Map a template name to a flat, collision-resistant compiled filename. */
    protected static function cacheKey(string $file): string
    {
        // Strip the source extension(s) then flatten path separators. `.blade.php` before `.php`.
        return str_replace(['/', '\\', '.blade.php', '.html', '.php'], ['_', '_', '', '', ''], $file) . '.php';
    }

    /**
     * A compiled file is fresh iff caching is on, it exists, it is not empty, and no source that
     * fed it has been touched since it was written.
     *
     * Two of those conditions were missing, and each produced a cache that never healed.
     *
     * **Empty is never valid.** The check was existence and mtime only, so a truncated artifact —
     * which a non-atomic write could leave behind — was *newer than its source* and therefore
     * "fresh" for ever. A template that compiles to nothing is not a legitimate cache state, so a
     * zero-byte artifact is treated as stale and recompiled rather than served.
     *
     * **`>=`, not `>`.** `filemtime()` has one-second resolution, so an edit made in the same
     * second as the compile is not strictly newer and the change was silently missed — reliably,
     * for the whole of that second, which is exactly how long an edit-and-refresh takes. Comparing
     * with `>=` can recompile once unnecessarily; missing an edit costs someone an afternoon.
     */
    protected static function isFresh(string $cached_file): bool
    {
        if (config('view.cache') === false) {
            return false; // explicit opt-out for debugging
        }
        if (!file_exists($cached_file)) {
            return false;
        }
        if (filesize($cached_file) === 0) {
            return false;
        }

        $compiledAt = filemtime($cached_file);
        foreach (self::$sources as $source) {
            if (filemtime($source) >= $compiledAt) {
                return false;
            }
        }
        return true;
    }

    /**
     * Manually clear compiled templates. No longer called after every render (that defeated the
     * cache); kept for explicit cache-busting (e.g. a future `view:clear` command).
     */
    public static function clearCache(?string $file = null): bool
    {
        try {
            if ($file === null) {
                foreach (glob(rtrim((string) self::$cache_path, '/') . '/*') ?: [] as $f) {
                    @unlink($f);
                }
                return true;
            }
            return @unlink($file);
        } catch (\Throwable) {
            return false;
        }
    }

    protected static function compileCode(string $code): string
    {
        $code = self::compileBlock($code);
        $code = self::compileYield($code);
        $code = self::compileRawEchos($code);     // {!! !!}  — raw
        $code = self::compileEscapedEchos($code); // {{{ }}}  — escaped (legacy alias)
        $code = self::compileEchos($code);        // {{ }}    — escaped (default)
        $code = self::compilePHP($code);          // {% %}    — raw PHP
        return $code;
    }

    /**
     * Resolve and inline {% extends %} / {% include %} targets, recording every source file so the
     * cache can be invalidated when any of them changes.
     *
     * @throws ViewNotFoundException
     */
    protected static function includeFiles(string $file): string
    {
        $resolved = self::resolve($file);
        if ($resolved === null) {
            throw new ViewNotFoundException($file, self::$paths);
        }
        self::$sources[] = $resolved;

        $code = file_get_contents($resolved);
        preg_match_all('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $code = str_replace($value[0], self::includeFiles($value[2]), $code);
        }
        return preg_replace('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code);
    }

    /** Find $file under the configured view roots, or null if it doesn't exist anywhere. */
    protected static function resolve(string $file): ?string
    {
        foreach (self::$paths as $path) {
            if (!str_ends_with($path, '/')) {
                $path .= '/';
            }
            if (is_file($path . $file)) {
                return $path . $file;
            }
        }
        return null;
    }

    protected static function compilePHP(string $code): string
    {
        return preg_replace_callback('~\{%\s*(.+?)\s*%\}~s', fn ($m) => '<?php ' . trim($m[1]) . ' ?>', $code);
    }

    /** {{ expr }} → HTML-escaped output. Expression is literal PHP (no dot→arrow rewriting). */
    protected static function compileEchos(string $code): string
    {
        return preg_replace_callback('~\{\{\s*(.+?)\s*\}\}~s', fn ($m) => '<?php echo e(' . trim($m[1]) . ') ?>', $code);
    }

    /** {!! expr !!} → raw, unescaped output. */
    protected static function compileRawEchos(string $code): string
    {
        return preg_replace_callback('~\{!!\s*(.+?)\s*!!\}~s', fn ($m) => '<?php echo ' . trim($m[1]) . ' ?>', $code);
    }

    /** {{{ expr }}} → HTML-escaped output (legacy alias; must run before {{ }}). */
    protected static function compileEscapedEchos(string $code): string
    {
        return preg_replace_callback('~\{\{\{\s*(.+?)\s*\}\}\}~s', fn ($m) => '<?php echo e(' . trim($m[1]) . ') ?>', $code);
    }

    protected static function compileBlock(string $code): string
    {
        preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], self::$blocks)) {
                self::$blocks[$value[1]] = '';
            }
            if (strpos($value[2], '@parent') === false) {
                self::$blocks[$value[1]] = $value[2];
            } else {
                self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], $value[2]);
            }
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    protected static function compileYield(string $code): string
    {
        foreach (self::$blocks as $block => $value) {
            // Callback replacement so block content (which may contain $-vars or backslashes) is
            // inserted verbatim rather than being interpreted as regex backreferences.
            $code = preg_replace_callback(
                '/{% ?yield ?' . preg_quote($block, '/') . ' ?%}/',
                fn () => $value,
                $code
            );
        }
        return preg_replace('/{% ?yield ?(.*?) ?%}/i', '', $code);
    }
}
