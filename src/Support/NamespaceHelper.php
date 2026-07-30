<?php

namespace Eyika\Atom\Framework\Support;

class NamespaceHelper
{
    /**
     * Parsed composer.json keyed by path (PERF-17). The Application constructor
     * calls getBaseNamespace() three times per boot (app/test/database namespaces),
     * each of which previously re-read and re-decoded the file.
     *
     * @var array<string,array>
     */
    protected static array $composerCache = [];

    public static function getBaseNamespace(?string $composerJsonPath = null, ?string $folderName = null): string
    {
        if (!$composerJsonPath) {
            $composerJsonPath = self::findComposerJsonPath();
        }

        if ($composerJsonPath && file_exists($composerJsonPath)) {
            if (!isset(self::$composerCache[$composerJsonPath])) {
                self::$composerCache[$composerJsonPath] = json_decode(file_get_contents($composerJsonPath), true) ?: [];
            }
            $composerJson = self::$composerCache[$composerJsonPath];

            if (isset($composerJson['autoload']['psr-4'])) {
                $namespaces = array_keys($composerJson['autoload']['psr-4']);
                $folders = array_values($composerJson['autoload']['psr-4']);
                if ($folderName !== null) {
                    foreach ($namespaces as $index => $namespace) {
                        if (str_contains($folders[$index], $folderName) || str_contains($folders[$index], Str::plural($folderName))) {
                            return rtrim($namespaces[$index], '\\');
                        }
                    }
                } else {
                    return rtrim($namespaces[0], '\\');
                }
            }
            if (isset($composerJson['autoload-dev']['psr-4'])) {
                $namespaces = array_keys($composerJson['autoload-dev']['psr-4']);
                $folders = array_values($composerJson['autoload-dev']['psr-4']);
                foreach ($namespaces as $index => $namespace) {
                    if (str_contains($folders[$index], $folderName) || str_contains($folders[$index], Str::plural($folderName))) {
                        return rtrim($namespaces[$index], '\\');
                    }
                }
            }
        }

        throw new \RuntimeException("Base namespace could not be determined.");
    }

    /**
     * Resolve the APPLICATION's base namespace independent of test-mode. Standard apps keep their
     * code in app/ (`App\`); the framework repo itself uses src/ (`Eyika\Atom\Framework\`). Try both,
     * then fall back to the first autoload psr-4 entry. This lets DatabaseTestCase / the test-env
     * Application boot an app whose code lives in app/ (previously it forced "src" under test and
     * threw for standard apps).
     */
    public static function getProjectNamespace(?string $composerJsonPath = null): string
    {
        foreach (['app', 'src'] as $folder) {
            try {
                return self::getBaseNamespace($composerJsonPath, $folder);
            } catch (\RuntimeException) {
                // not this folder — try the next candidate
            }
        }
        return self::getBaseNamespace($composerJsonPath); // last resort: first autoload psr-4
    }

    /**
     * Drop the memoized composer.json cache (test isolation / worker reload).
     */
    public static function flushComposerCache(): void
    {
        self::$composerCache = [];
    }

    public static function loadAndPerformActionOnClasses(string $namespace, string $fullPath, callable $method, $base_folder = 'src')
    {
        $listObject = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($listObject as $fileinfo) {
            if (!$fileinfo->isDir() && strtolower(pathinfo($fileinfo->getRealPath(), PATHINFO_EXTENSION)) == explode('.', '.php')[1]) {
                $facade = classFromFile($fileinfo, $namespace, $base_folder);
                $class_name = explode("\\", $facade);
                $class_name = $class_name[count($class_name) - 1];

                if ($method($class_name, $facade)) {
                    break;
                }
            }
        }
    }

    private static function findComposerJsonPath(): ?string
    {
        $currentDir = __DIR__;

        while (!file_exists($currentDir . '/composer.json')) {
            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                return null;
            }
            $currentDir = $parentDir;
        }

        return $currentDir . '/composer.json';
    }
}
