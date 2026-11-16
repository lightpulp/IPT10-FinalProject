<?php

declare(strict_types=1);

namespace Lightpull;

use JsonException;

/**
 * Outgoing HTTP for calling external APIs. It uses cURL with short timeouts and
 * turns every kind of failure (network error, non-2xx status, bad JSON) into one
 * exception type, HttpClientException, so callers have a single thing to catch.
 */
class HttpClient
{
    public function __construct(private readonly int $timeout = 5)
    {
    }

    /**
     * GETs $url and decodes its JSON body into an array.
     *
     * @param array<string, string> $headers e.g. ['Authorization' => 'Bearer ...']
     * @throws HttpClientException
     */
    public function getJson(string $url, array $headers = [], ?int $timeout = null): array
    {
        $timeout ??= $this->timeout;
        $lines = ['Accept: application/json', 'User-Agent: Lightpull-HttpClient/1.0'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HTTPHEADER => $lines,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        // Messages name the host only: some APIs put the key in the URL path.
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($body === false) {
            throw new HttpClientException("Could not reach {$host}: {$error}");
        }
        if ($status === 429) {
            throw new HttpClientException("{$host} is rate limiting us (HTTP 429).", 429);
        }
        if ($status < 200 || $status >= 300) {
            throw new HttpClientException("{$host} answered HTTP {$status}.", $status);
        }
        try {
            $data = json_decode((string) $body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpClientException("{$host} did not return valid JSON.", $status, $e);
        }
        if (!is_array($data)) {
            throw new HttpClientException("{$host} returned JSON that is not an object.", $status);
        }
        return $data;
    }
}
