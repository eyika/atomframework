<?php

namespace Eyika\Atom\Framework\Support\Storage;

class FileUploadProperties
{
    protected string $name;
    protected string $type;
    protected string $tmpName;
    protected string $error;
    protected string $size;

    public function __construct(string $name, string $type, string $tmp_name, string $error, string $size)
    {
        $this->name = $name;
        $this->type = $type;
        $this->tmpName = $tmp_name;
        $this->error = $error;
        $this->size = $size;
    }

    public function name()
    {
        return $this->name;
    }

    public function type()
    {
        return $this->type;
    }

    public function tmpName()
    {
        return $this->tmpName;
    }

    public function error()
    {
        return $this->error;
    }

    public function size()
    {
        return $this->size;
    }
}