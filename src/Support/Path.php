<?php

namespace Eyika\Atom\Framework\Support;

/**
 * Lexical path handling — string arithmetic only, never touching the filesystem.
 *
 * The distinction this class exists to draw: **traversal is a lexical property of the request**
 * (`..` segments climbing out of a root), while **following a symlink is a deployment decision the
 * operator made** — by running `storage:link`, or by pointing a directory at a media volume.
 *
 * `realpath()` conflates the two. It collapses `..` *and* resolves symlinks in one step, so a guard
 * built on it cannot refuse the first without also forbidding the second. That is not hypothetical:
 * confining served assets with `realpath()` refused `public/storage`, the symlink this framework's
 * own `storage:link` command creates, so every uploaded file 404'd under `artisan serve` while
 * Apache and LiteSpeed — which follow symlinks by default — served them fine in production.
 *
 * Deciding containment lexically and only then resolving the path to open it refuses traversal
 * *before* any link is involved, and leaves the operator's symlinks working. It is also what the
 * web servers in front of this code already do, so development and production agree.
 */
class Path
{
    /**
     * Collapse `.` and `..` in a request path, rejecting anything that climbs above the root.
     *
     * Returns the normalised path with a leading `/` and no trailing one, or **null** when the
     * path escapes — which is the caller's signal to refuse the request.
     *
     * Backslashes are folded to `/` first, so a `..\..\` attempt is normalised rather than
     * surviving as an opaque segment on a platform that treats it as a separator.
     */
    public static function normalizeUri(string $uri): ?string
    {
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $uri)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (!$segments) {
                    return null; // climbed above the root — refuse, before any link is resolved
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }

    /**
     * Collapse `.` and `..` in a filesystem path, preserving its root (leading separator, or a
     * Windows drive). Unlike `realpath()` this neither resolves symlinks nor requires the path to
     * exist, which is what makes it usable as a containment check.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $prefix = '';
        if (preg_match('#^([a-zA-Z]:)/#', $path, $m)) {
            $prefix = $m[1];                 // Windows drive
            $path = substr($path, strlen($m[1]));
        } elseif (str_starts_with($path, '/')) {
            $prefix = '';
        }

        $absolute = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments && end($segments) !== '..') {
                    array_pop($segments);
                } elseif (!$absolute) {
                    $segments[] = '..';      // a relative path may legitimately start above itself
                }
                continue;
            }

            $segments[] = $segment;
        }

        $joined = implode(DIRECTORY_SEPARATOR, $segments);

        return $prefix . ($absolute ? DIRECTORY_SEPARATOR . $joined : $joined);
    }

    /**
     * Whether `$path` sits inside `$root`, compared lexically.
     *
     * Both sides are normalised first, so `..` cannot smuggle its way past. The comparison is
     * case-insensitive on Windows, where two spellings of the same directory are the same
     * directory and a case-sensitive prefix test would reject a legitimate path.
     */
    public static function isWithin(string $path, string $root): bool
    {
        $path = self::normalize($path);
        $root = rtrim(self::normalize($root), DIRECTORY_SEPARATOR);

        if ($root === '') {
            return false;
        }

        $prefix = $root . DIRECTORY_SEPARATOR;

        return DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($path, $prefix, strlen($prefix)) === 0
            : str_starts_with($path, $prefix);
    }
}
