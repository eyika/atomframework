<?php
namespace Eyika\Atom\Framework\Support\Storage;

use League\Flysystem\Config;
use League\Flysystem\UrlGeneration\PublicUrlGenerator;

class LocalPublicUrlGenerator implements PublicUrlGenerator
{
    public function publicUrl(string $path, Config $config): string
    {
        return $config->get('url'). '/'.$path;
    }
}
