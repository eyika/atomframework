<?php

namespace Eyika\Atom\Framework\Http;

use Exception;

class Proxy
{
    protected Request $request;
    protected ?string $target;
    protected array $extraHeaders;
    protected $blacklist; //['authorization', 'cookie', 'x-csrf-token'] could be in config/proxy.php

    public function __construct(Request $request, ?string $target = null, array $extraHeaders = [])
    {
        $this->request = $request;
        $this->extraHeaders = $extraHeaders;
        $this->blacklist = [];

        if ($target) {
            if (!filter_var($target, FILTER_VALIDATE_URL) || !str_starts_with($target, 'http')) {
                throw new Exception("Invalid target URL: $target");
            }
            $this->target = $target;
        }
    }

    public function to(string $target, array $extraHeaders = [])
    {
        if (!filter_var($target, FILTER_VALIDATE_URL) || !str_starts_with($target, 'http')) {
            throw new Exception("Invalid target URL: $target");
        }
        $this->target = $target;
        $this->extraHeaders = $extraHeaders;
        return $this->send();
    }

    public function send(): Response
    {
        $headers = [];
        $blacklist = $this->getBlacklistedHeaders();

        foreach ($this->request->headers() as $key => $value) {
            if (strtolower($key) === 'host') continue;
            if (in_array(strtolower($key), $blacklist)) continue;
            $headers[$key] = $value;
        }

        $headers = array_merge($headers, $this->extraHeaders);

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "$k: $v";
        }

        $options = [
            'http' => [
                'method' => $this->request->method(),
                'header' => implode("\r\n", $headerLines),
                'content' => $this->request->body(),
                'ignore_errors' => true,
            ]
        ];

        $context = stream_context_create($options);
        $url = $this->target;

        // Merge original query string
        $query = $this->request->query();
        $init = true;
        foreach($query as $key => $val) {
            if ($init) {
                $url .= "?$key=$val";
                $init = false;
                continue;
            }

            $url .= "&$key=$val";
        }

        $body = @file_get_contents($url, false, $context);

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

    private function getBlacklistedHeaders()
    {
        //in future this function should return blacklisted headers from /config/proxy.php
        return $this->blacklist;
    }
}
