<?php

namespace Eyika\Atom\Framework\Foundation;

/**
 * Auto-discovers package service providers (and facade aliases) from installed
 * packages' composer.json `extra.atom` block (PKG-02) — the "composer require and
 * it just works" experience. A package opts in with:
 *
 *   "extra": { "atom": { "providers": ["Vendor\\Pkg\\PkgServiceProvider"],
 *                        "aliases":   { "Pkg": "Vendor\\Pkg\\Facades\\Pkg" } } }
 *
 * The manifest is compiled from vendor/composer/installed.json and cached to
 * bootstrap/cache/packages.php by `package:discover` (run on deploy / post-install);
 * without the cache it is built in-memory once per process.
 */
class PackageManifest
{
    protected string $vendorPath;
    protected string $manifestPath;
    protected ?array $manifest = null;

    public function __construct(?string $basePath = null)
    {
        $basePath = rtrim($basePath ?? (function_exists('base_path') ? base_path() : getcwd()), '/\\');
        $this->vendorPath = $basePath . '/vendor';
        $this->manifestPath = $basePath . '/bootstrap/cache/packages.php';
    }

    /** Discovered provider class-strings (deduped, in discovery order). */
    public function providers(): array
    {
        $providers = [];
        foreach ($this->getManifest() as $package) {
            foreach ((array) ($package['providers'] ?? []) as $provider) {
                $providers[] = $provider;
            }
        }
        return array_values(array_unique($providers));
    }

    /** Discovered facade aliases (alias => class), later packages overriding earlier. */
    public function aliases(): array
    {
        $aliases = [];
        foreach ($this->getManifest() as $package) {
            foreach ((array) ($package['aliases'] ?? []) as $alias => $class) {
                $aliases[$alias] = $class;
            }
        }
        return $aliases;
    }

    /**
     * The compiled manifest keyed by package name. Prefers the cached artifact and
     * falls back to building it in-memory (memoized per process).
     *
     * @return array<string,array{providers:string[],aliases:array<string,string>}>
     */
    public function getManifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (is_file($this->manifestPath)) {
            $cached = require $this->manifestPath;
            if (is_array($cached)) {
                return $this->manifest = $cached;
            }
        }

        return $this->manifest = $this->build();
    }

    /**
     * Build the manifest from installed.json (does not write it).
     *
     * @return array<string,array{providers:string[],aliases:array<string,string>}>
     */
    public function build(): array
    {
        $installed = $this->vendorPath . '/composer/installed.json';
        if (!is_file($installed)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($installed), true) ?: [];
        // Composer 2 nests under "packages"; Composer 1 is a flat list.
        $packages = $decoded['packages'] ?? $decoded;

        $manifest = [];
        foreach ($packages as $package) {
            $name = $package['name'] ?? null;
            $atom = $package['extra']['atom'] ?? null;
            if ($name === null || !is_array($atom)) {
                continue;
            }
            $manifest[$name] = [
                'providers' => array_values((array) ($atom['providers'] ?? [])),
                'aliases' => (array) ($atom['aliases'] ?? []),
            ];
        }

        return $manifest;
    }

    /**
     * Compile and write the manifest cache (package:discover). Returns it.
     *
     * @return array<string,array{providers:string[],aliases:array<string,string>}>
     */
    public function writeManifest(): array
    {
        $manifest = $this->build();

        $dir = dirname($this->manifestPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Unable to create package cache directory: $dir");
        }

        file_put_contents($this->manifestPath, "<?php\n\nreturn " . var_export($manifest, true) . ";\n", LOCK_EX);
        $this->manifest = $manifest;

        return $manifest;
    }

    public function deleteManifest(): void
    {
        if (is_file($this->manifestPath)) {
            @unlink($this->manifestPath);
        }
        $this->manifest = null;
    }

    public function manifestPath(): string
    {
        return $this->manifestPath;
    }
}
