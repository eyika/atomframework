<?php

namespace Eyika\Atom\Framework\Support\Database;
// require_once __DIR__."/../libs/helpers.php"; May need to uncomment this

use Eyika\Atom\Framework\Support\Concerns\DeepClonesSelf;
use Eyika\Atom\Framework\Support\Database\Concerns\InitsModelEvents;
use Eyika\Atom\Framework\Support\Database\Concerns\QueryBuilder;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

abstract class Model implements ModelInterface
{
    use QueryBuilder, InitsModelEvents, DeepClonesSelf;

    /**
     * Create a new model instance.
     *
     * @param array  $attributes
     * @return void
     */
    public function __construct(array $values = [])
    {
        $this->prepareModel($values);
    }
}
