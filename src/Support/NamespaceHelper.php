<?php

namespace Eyika\Atom\Framework\Support;

class NamespaceHelper
{
    public static function getBaseNamespace(string|null $composerJsonPath = null): string
    {
        if (!$composerJsonPath)
            $composerJsonPath = self::findComposerJsonPath();

        if ($composerJsonPath && file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);

            if (isset($composerJson['autoload']['psr-4'])) {
                $namespaces = array_keys($composerJson['autoload']['psr-4']);
                return rtrim($namespaces[0], '\\');
            }
        }

        throw new \RuntimeException("Base namespace could not be determined.");
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
