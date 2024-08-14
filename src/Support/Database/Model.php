<?php

namespace Eyika\Atom\Support\Database;
// require_once __DIR__."/../libs/helpers.php"; May need to uncomment this

use Eyika\Atom\Support\Database\Concerns\InitsModelEvents;
use Eyika\Atom\Support\Database\Concerns\QueryBuilder;
use Eyika\Atom\Support\Database\Contracts\ModelInterface;
use Eyika\Atom\Support\Database\Contracts\UserModelInterface;

abstract class Model implements ModelInterface
{
    use QueryBuilder, InitsModelEvents;

    /**
     * Create a new model instance.
     *
     * @param array  $attributes
     * @param self|self&UserModelInterface $child
     * @return void
     */
    public function __construct(array $values = [], $child = null)
    {
        $this->child = $child;
        mysqly::auth(env('DB_USER'), env('DB_PASS'), env('DB_NAME'), env('DB_HOST'));
        $this->prepareModel($values);
    }
}