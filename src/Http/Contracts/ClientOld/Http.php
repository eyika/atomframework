<?php

namespace Eyika\Atom\Framework\Http\Contracts\ClientOld;

use Eyika\Atom\Framework\Http\Client\Exceptions\ConnectionException;
use Eyika\Atom\Framework\Http\Contracts\ClientOld\HttpResponse;
use Eyika\Atom\Framework\Http\Client\PendingRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

class HttpClientOld
{
    protected Client $client;
    protected array $options = [];

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    public static function build(array $config = []): PendingRequest
    {
        return new PendingRequest($config);
    }

    public static function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        return static::build()
            ->withHeaders($headers)
            ->send('GET', $url, ['query' => $query]);
    }

    public static function post(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return static::build()
            ->withHeaders($headers)
            ->send('POST', $url, ['json' => $data]);
    }

    public static function put(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return static::build()
            ->withHeaders($headers)
            ->send('PUT', $url, ['json' => $data]);
    }

    public static function delete(string $url, array $data = [], array $headers = []): HttpResponse
    {
        return static::build()
            ->withHeaders($headers)
            ->send('DELETE', $url, ['json' => $data]);
    }

    public function withHeaders(array $headers): self
    {
        $this->options['headers'] = $headers;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function send(string $method, string $url, array $options = []): HttpResponse
    {
        $options = array_merge($this->options, $options);
        return $this->request($method, $url, $options);
    }

    protected function request(string $method, string $url, array $options = []): HttpResponse
    {
        try {
            $response = $this->client->request($method, $url, $options);
            return new HttpResponse($response);
        } catch (ConnectException $e) {
            throw new ConnectionException("Connection error: " . $e->getMessage(), $e->getRequest(), $e, $e->getHandlerContext());
        } catch (RequestException $e) {
            return new HttpResponse($e->getResponse());
        } catch (ClientException | ServerException $e) {
            return new HttpResponse($e->getResponse());
        }
    }
}
