<?php

namespace Eyika\Atom\Framework\Support\View\Exceptions;

use RuntimeException;

/**
 * Thrown when the Twig engine cannot resolve a template — the root view or any
 * {% extends %} / {% include %} target. Replaces the old silent-blank behaviour so a
 * missing template surfaces as a logged error (mail path) or a 500 (response path)
 * instead of an empty body that looks like success.
 */
class ViewNotFoundException extends RuntimeException
{
    public function __construct(string $view, array $paths = [])
    {
        $searched = $paths ? ' (searched: ' . implode(', ', $paths) . ')' : '';
        parent::__construct("View [{$view}] not found{$searched}.");
    }
}
