<?php

namespace Eyika\Atom\Framework\Http\Contracts\ClientOld;

use Psr\Http\Message\ResponseInterface;

class HttpResponseOld
{
    protected ?ResponseInterface $response;

    public function __construct(?ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function status(): ?int
    {
        return $this->response ? $this->response->getStatusCode() : null;
    }

    public function ok(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function json(): ?array
    {
        return json_decode($this->body(), true);
    }

    public function body(): ?string
    {
        return $this->response ? (string) $this->response->getBody() : null;
    }
}
