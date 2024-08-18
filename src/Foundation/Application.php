<?php

namespace Eyika\Atom\Framework\Foundation;

use Eyika\Atom\Framework\Foundation\Concerns\ServiceContainer;
use Eyika\Atom\Framework\Foundation\Contracts\ApplicationInterface;

class Application implements ApplicationInterface
{
    use ServiceContainer;

    public function __construct(string $basepath)
    {
        $GLOBALS['basepath'] = $basepath;
    }
}
