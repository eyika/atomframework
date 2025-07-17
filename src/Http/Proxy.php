<?php

namespace Eyika\Atom\Framework\Http;

use Exception;

class Proxy
{
    protected Request $request;
    protected ?string $target;

    public function __construct(Request $request, ?string $target = null)
    {
        $this->request = $request;

        if ($target) {
            if (!filter_var($target, FILTER_VALIDATE_URL) || !str_starts_with($target, 'http')) {
                throw new Exception("Invalid target URL: $target");
            }
            $this->target = $target;
        }
    }

    public function to(string $target)
    {
        if (!filter_var($target, FILTER_VALIDATE_URL) || !str_starts_with($target, 'http')) {
            throw new Exception("Invalid target URL: $target");
        }
        $this->target = $target;
        return $this->send();
    }

    public function send(): Response
    {
        $headers = [];
        foreach ($this->request->headers() as $key => $value) {
            if (strtolower($key) === 'host') continue;
            $headers[] = "$key: $value";
        }

        $options = [
            'http' => [
                'method' => $this->request->method(),
                'header' => implode("\r\n", $headers),
                'content' => $this->request->body(),
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($options);
        $body = @file_get_contents($this->target, false, $context);

        $response = new Response();

        if ($body === false) {
            return $response->plain("Proxy request to {$this->target} failed.", 502);
        }

        $response->plain($body);

        global $http_response_header;

        foreach ($http_response_header ?? [] as $headerLine) {
            if (str_starts_with(strtolower($headerLine), 'transfer-encoding:')) continue;
            if (str_starts_with(strtolower($headerLine), 'content-encoding:')) continue;

            if (strpos($headerLine, ':') !== false) {
                [$name, $value] = explode(':', $headerLine, 2);
                $response->setHeader(trim($name), trim($value));
            } elseif (preg_match('#HTTP/[\d\.]+\s+(\d+)#', $headerLine, $match)) {
                $response->status((int) $match[1]);
            }
        }

        return $response;
    }
}
