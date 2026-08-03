<?php

namespace Eyika\Atom\Framework\Foundation\Console\Concerns;

use Eyika\Atom\Framework\Foundation\ServiceProvider;

/**
 * Shared migration-file discovery for the `migrate:*` commands.
 *
 * `migrate` honoured package directories registered with `ServiceProvider::loadMigrationsFrom()`
 * (PKG-01), but its siblings did not: `rollback` resolved a migration to
 * `base_path("database/migrations/{$class}.php")` and threw "Migration file not found" for a
 * package migration it had itself applied, and `migrate:status` globbed only the app directory so
 * package migrations never appeared. Every command now resolves through here, so a package
 * migration can be applied, listed, and rolled back consistently.
 */
trait ResolvesMigrationPaths
{
    /**
     * Every directory migrations may live in — the app's first, then package directories in
     * registration order, so app migrations keep running before package ones.
     *
     * @return string[]
     */
    protected function migrationDirectories(?string $basePath = null): array
    {
        $dirs = [rtrim($basePath ?? base_path('database/migrations'), '/\\')];

        foreach (ServiceProvider::migrationPaths() as $packagePath) {
            $packagePath = rtrim($packagePath, '/\\');

            if (!in_array($packagePath, $dirs, true)) {
                $dirs[] = $packagePath;
            }
        }

        return $dirs;
    }

    /**
     * All migration files across those directories, each directory sorted by filename.
     *
     * @return string[]
     */
    protected function gatherMigrationFiles(?string $basePath = null): array
    {
        $files = [];

        foreach ($this->migrationDirectories($basePath) as $dir) {
            $files = array_merge($files, glob($dir . '/*.php') ?: []);
        }

        return $files;
    }

    /**
     * Locate one migration by class/file name across every directory, or null if it is gone
     * (e.g. the package was removed after its migration ran).
     */
    protected function findMigrationFile(string $name): ?string
    {
        $name = basename($name, '.php');

        foreach ($this->migrationDirectories() as $dir) {
            $file = $dir . '/' . $name . '.php';

            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }
}
