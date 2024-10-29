<?php
namespace Eyika\Atom\Framework\Support\Storage;

use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;

class LocalPublicUrlGenerator implements PublicUrlGenerator
{
    public function publicUrl(string $path, Config $config): string
    {
        return "/storage".$path;
    }
}
