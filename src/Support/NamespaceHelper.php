<?php

namespace Eyika\Atom\Framework\Support;

class NamespaceHelper
{
    public static function getBaseNamespace(): string
    {
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
