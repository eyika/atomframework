<?php

use Eyika\Atom\Framework\Exceptions\NotImplementedException;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;

class LocalTemporaryUrlGenerator implements TemporaryUrlGenerator
{
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        throw new NotImplementedException();
    }
}
