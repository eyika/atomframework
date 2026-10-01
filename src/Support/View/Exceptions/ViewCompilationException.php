<?php

namespace Eyika\Atom\Framework\Support\View\Exceptions;

use RuntimeException;

/**
 * Thrown when a template resolves but its compiled artifact cannot be written.
 *
 * The sibling of {@see ViewNotFoundException}, and for the same reason: the alternative is an empty
 * render that looks like success. A compile that cannot write its output used to leave a zero-byte
 * file behind, which the cache then judged fresh for ever — so the first failure was silent and
 * every render after it was silent too.
 *
 * A full disk, a read-only cache directory and a wrong-owner `storage/framework/views` all land
 * here, which is why the message carries the path: those are operator problems, and an exception
 * naming the file is the difference between a five-minute fix and a day spent reading templates.
 */
class ViewCompilationException extends RuntimeException
{
    public function __construct(string $path, string $reason = '')
    {
        $because = $reason !== '' ? ": {$reason}" : '';
        parent::__construct("View could not be compiled to [{$path}]{$because}.");
    }
}
