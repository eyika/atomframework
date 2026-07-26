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

        // Strip credential/session headers by default so they are NOT forwarded to
        // the upstream (was an empty list → Authorization/Cookie leaked).
        $this->blacklist = array_map('strtolower', config('proxy.blacklist', [
            'authorization', 'proxy-authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token',
        ]));

        if ($target) {
            $this->assertSafeTarget($target);
            $this->target = $target;
        }
    }

    public function to(string $target, array $extraHeaders = [])
    {
        $this->assertSafeTarget($target);
        $this->target = $target;
        $this->extraHeaders = $extraHeaders;
        return $this->send();
    }

    /**
     * Validate a proxy target and block SSRF. Requires an http(s) URL; if a host
     * allowlist is configured (config proxy.allowed_hosts) the host must be in it,
     * otherwise the host must NOT resolve to a private/reserved/loopback address
     * (blocks 127.0.0.1, 169.254.169.254, 10/172.16/192.168, ::1, …).
     *
     * @throws Exception
     */
    private function assertSafeTarget(string $target): void
    {
        if (!filter_var($target, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $target)) {
            throw new Exception("Invalid target URL: $target");
        }

        $host = parse_url($target, PHP_URL_HOST);
        if (!$host) {
            throw new Exception("Invalid target host: $target");
        }

        $allowed = array_map('strtolower', config('proxy.allowed_hosts', []));
        if (!empty($allowed)) {
            if (!in_array(strtolower($host), $allowed, true)) {
                throw new Exception("Proxy target host not allowed: $host");
            }
            return; // explicit allowlist is the trust anchor
        }

        // No allowlist → resolve and reject private/reserved/loopback addresses.
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new Exception("Could not resolve proxy target host: $host");
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new Exception("Proxy target resolves to a private/reserved address: $host ($ip)");
        }
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
                // Do NOT follow redirects — a 30x to an internal host would bypass
                // the SSRF target check.
                'follow_location' => 0,
                'max_redirects' => 0,
            ]
        ];

        $context = stream_context_create($options);
        $url = $this->target;

        // Merge original query string (url-encoded to avoid breaking the URL).
        $query = $this->request->query();
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
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
