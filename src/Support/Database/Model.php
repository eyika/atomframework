<?php

namespace Eyika\Atom\Framework\Support\Database;
// require_once __DIR__."/../libs/helpers.php"; May need to uncomment this

use Eyika\Atom\Framework\Support\Database\Concerns\InitsModelEvents;
use Eyika\Atom\Framework\Support\Database\Concerns\QueryBuilder;
use Eyika\Atom\Framework\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Framework\Support\Database\Contracts\UserModelInterface;

abstract class Model implements ModelInterface
{
    use QueryBuilder, InitsModelEvents;

    /**
     * Create a new model instance.
     *
     * @param array  $attributes
     * @param self|UserModelInterface $child
     * @return void
     */
    public function __construct(array $values = [], $child = null)
    {
        $this->child = $child;
        $this->prepareModel($values);
    }
}
