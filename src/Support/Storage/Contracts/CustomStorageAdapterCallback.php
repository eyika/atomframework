<?php

namespace Eyika\Atom\Framework\Support\Storage\Contracts;

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use Eyika\Atom\Framework\Foundation\Application;
use League\Flysystem\FilesystemAdapter;

/**
 * Hybridauth storage manager interface
 */
abstract class CustomStorageAdapterCallback
{
    /**
     * Retrieve a item from storage
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __invoke(Application $app, string $disk): FilesystemAdapter
    {
        throw new NotImplementedException('this method was not implemented, you should implement it');
    }
}
