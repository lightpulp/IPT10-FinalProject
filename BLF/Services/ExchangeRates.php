<?php

declare(strict_types=1);

namespace App\Services;

use Lightpull\App;
use Lightpull\Cache;
use Lightpull\HttpClient;
use Lightpull\HttpClientException;

/**
 * Peso exchange rates from ExchangeRate-API, so customers abroad can see a
 * rough price in their own currency. Prices and quotes stay in pesos.
 *
 * Rates change once a day, so they are cached and the API is called at most
 * twice a day. If a call fails, the last saved rates are shown (marked stale)
 * and the API is not called again for a few minutes. If there are no saved
 * rates at all, latest() returns null and pages simply leave the section out.
 */
final class ExchangeRates
{
    private const CACHE_KEY = 'exchange-rates:PHP';
    private const FAILURE_KEY = 'exchange-rates:PHP:failed';
    private const OPEN_URL = 'https://open.er-api.com/v6/latest/PHP';
    private const KEYED_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/PHP';

    public function __construct(
        private readonly HttpClient $http,
        private readonly Cache $cache,
        private readonly App $app,
    ) {
    }

    /** @return array{rates: array<string, float>, updated_at: int, stale: bool}|null */
    public function latest(): ?array
    {
        $fresh = $this->cache->get(self::CACHE_KEY, (int) $this->setting('max_age', 43200));
        if ($fresh !== null) {
            return $fresh + ['stale' => false];
        }

        $recentlyFailed = $this->cache->get(self::FAILURE_KEY, (int) $this->setting('retry_after', 600)) !== null;
        if (!$recentlyFailed) {
            try {
                $rates = $this->fetch();
                $this->cache->put(self::CACHE_KEY, $rates);
                $this->cache->forget(self::FAILURE_KEY);
                return $rates + ['stale' => false];
            } catch (HttpClientException $e) {
                error_log('Exchange rates could not be refreshed: ' . $e->getMessage());
                $this->cache->put(self::FAILURE_KEY, $e->getMessage());
            }
        }

        $stale = $this->cache->get(self::CACHE_KEY, (int) $this->setting('stale_max_age', 604800));
        return $stale === null ? null : $stale + ['stale' => true];
    }

    /**
     * Converts a peso amount into each configured currency.
     *
     * @return array<string, float> e.g. ['USD' => 3145.20, 'AED' => 11550.75]
     */
    public static function convert(float $pesos, array $rates): array
    {
        return array_map(fn (float $rate) => round($pesos * $rate, 2), $rates);
    }

    /** @return array{rates: array<string, float>, updated_at: int} */
    private function fetch(): array
    {
        // The key goes in the URL path, as ExchangeRate-API's v6 endpoint expects.
        $key = (string) $this->setting('api_key', '');
        $url = $key === '' ? self::OPEN_URL : sprintf(self::KEYED_URL, rawurlencode($key));
        $json = $this->http->getJson($url, [], 4);

        if (($json['result'] ?? null) !== 'success') {
            throw new HttpClientException('ExchangeRate-API returned an error: ' . ($json['error-type'] ?? 'unknown'));
        }
        // The keyed endpoint calls the list "conversion_rates", the open endpoint calls it "rates".
        $all = $json['conversion_rates'] ?? $json['rates'] ?? null;
        if (!is_array($all)) {
            throw new HttpClientException('ExchangeRate-API response had no rates.');
        }

        $rates = [];
        foreach ((array) $this->setting('currencies', ['USD']) as $code) {
            if (isset($all[$code]) && is_numeric($all[$code]) && $all[$code] > 0) {
                $rates[$code] = (float) $all[$code];
            }
        }
        if ($rates === []) {
            throw new HttpClientException('ExchangeRate-API response had none of the configured currencies.');
        }
        return ['rates' => $rates, 'updated_at' => (int) ($json['time_last_update_unix'] ?? time())];
    }

    private function setting(string $key, mixed $default): mixed
    {
        return $this->app->config('exchange_rates.' . $key, $default);
    }
}
