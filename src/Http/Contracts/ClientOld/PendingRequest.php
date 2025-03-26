<?php

namespace Eyika\Atom\Framework\Http\Contracts\ClientOld;

use Eyika\Atom\Framework\Http\Client\Exceptions\ConnectionException;
use Eyika\Atom\Framework\Http\Client\Exceptions\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class PendingRequestOld
{
    protected Client $client;
    protected array $options = [];
    protected ?string $baseUrl = null;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    public function withHeaders(array $headers): self
    {
        $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);
        return $this;
    }

    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    public function acceptJson(): self
    {
        return $this->withHeaders(['Accept' => 'application/json']);
    }

    public function asJson(): self
    {
        return $this->withHeaders(['Content-Type' => 'application/json']);
    }

    public function send(string $method, string $url, array $options = []): HttpResponse
    {
        $url = $this->baseUrl ? $this->baseUrl . '/' . ltrim($url, '/') : $url;
        $options = array_merge($this->options, $options);

        try {
            $response = $this->client->request($method, $url, $options);
            return new HttpResponse($response);
        } catch (ConnectException $e) {
            $_e = new ConnectionException("Connection error: " . $e->getMessage(), $e->getRequest(), $e);
            throw $_e;
        } catch (RequestException $e) {
            return new HttpResponse($e->getResponse());
        }
    }
}
