<?php

namespace Eyika\Atom\Framework\Support\View;

use Eyika\Atom\Framework\Support\Arr;
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

        $cached_file = self::cache($file);

        extract($data, EXTR_SKIP);
        if (!$get_output) {
            require $cached_file;
            return null;
        }

        ob_start();
        require $cached_file;
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
        file_put_contents(
            $cached_file,
            '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code,
            LOCK_EX
        );

        return $cached_file;
    }

    /** Map a template name to a flat, collision-resistant compiled filename. */
    protected static function cacheKey(string $file): string
    {
        // Strip the source extension(s) then flatten path separators. `.blade.php` before `.php`.
        return str_replace(['/', '\\', '.blade.php', '.html', '.php'], ['_', '_', '', '', ''], $file) . '.php';
    }

    /** A compiled file is fresh iff caching is on, it exists, and it is newer than every source that fed it. */
    protected static function isFresh(string $cached_file): bool
    {
        if (config('view.cache') === false) {
            return false; // explicit opt-out for debugging
        }
        if (!file_exists($cached_file)) {
            return false;
        }
        $compiledAt = filemtime($cached_file);
        foreach (self::$sources as $source) {
            if (filemtime($source) > $compiledAt) {
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
