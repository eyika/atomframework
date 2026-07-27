<?php

namespace Eyika\Atom\Framework\Foundation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * opcache.preload helper (PERF-15). Compiles the framework core + app classes into
 * OPcache's shared memory at server start, so every request/worker skips parsing +
 * compiling them. Use from a preload.php that php.ini points at:
 *
 *   ; php.ini
 *   opcache.preload = /path/to/app/preload.php
 *   opcache.preload_user = www-data
 *
 *   // preload.php
 *   require __DIR__.'/vendor/autoload.php';
 *   (new Preloader())
 *       ->paths([__DIR__.'/vendor/eyika/atom-framework/src', __DIR__.'/app'])
 *       ->ignore(['helpers.php', '/tests/'])
 *       ->load();
 */
class Preloader
{
    /** @var string[] */
    protected array $paths = [];
    /** @var string[] */
    protected array $ignores = [];
    protected int $compiled = 0;

    /** Directories (or files) to preload. */
    public function paths(array $paths): self
    {
        $this->paths = array_merge($this->paths, $paths);
        return $this;
    }

    /** Substrings/glob patterns; any matching file is skipped. */
    public function ignore(array $patterns): self
    {
        $this->ignores = array_merge($this->ignores, $patterns);
        return $this;
    }

    /**
     * Compile every discovered PHP file into OPcache. No-op (returns 0) when OPcache
     * isn't available (e.g. CLI without opcache). Returns the number compiled.
     */
    public function load(): int
    {
        if (!function_exists('opcache_compile_file') || !ini_get('opcache.enable')) {
            return 0;
        }

        foreach ($this->files() as $file) {
            try {
                // A file whose parent/interface isn't compiled yet raises a warning
                // ("Can't preload unlinked class") — suppress + skip; it still gets
                // compiled on first use.
                @opcache_compile_file($file);
                $this->compiled++;
            } catch (Throwable $e) {
                // skip un-preloadable files
            }
        }

        return $this->compiled;
    }

    /**
     * The PHP files that would be preloaded (used for testing / inspection).
     *
     * @return string[]
     */
    public function files(): array
    {
        $files = [];
        foreach ($this->paths as $path) {
            foreach ($this->phpFilesIn($path) as $file) {
                if (!$this->isIgnored($file)) {
                    $files[] = $file;
                }
            }
        }
        return $files;
    }

    /** @return string[] */
    protected function phpFilesIn(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with(strtolower($path), '.php') ? [$path] : [];
        }
        if (!is_dir($path)) {
            return [];
        }

        $out = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (strtolower($file->getExtension()) === 'php') {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }

    protected function isIgnored(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        foreach ($this->ignores as $pattern) {
            $p = str_replace('\\', '/', $pattern);
            if (str_contains($normalized, $p) || fnmatch($p, $normalized)) {
                return true;
            }
        }
        return false;
    }
}
