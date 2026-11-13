<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use GuzzleHttp\ClientInterface;
use RuntimeException;
use SilverStripe\Core\Environment;

/**
 * Authenticated, read-only Reapit Foundations client.
 *
 * Configuration is read via Silverstripe's Environment API, supporting .env files and process environment variables.
 */
class ApiClient
{
    private ?string $token = null;
    private int $expiresAt = 0;

    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    public function customerId(): string
    {
        return $this->setting('REAPIT_CUSTOMER_ID');
    }

    public function get(string $path, array $query = []): array
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new RuntimeException('Reapit API paths must be relative to the configured API host');
        }

        $response = $this->httpClient->request('GET', 'https://platform.reapit.cloud' . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken(),
                'reapit-customer' => $this->customerId(),
                'api-version' => '2020-01-31',
                'Accept' => 'application/json',
            ],
            'query' => $this->encodeQuery($query),
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => true,
        ]);

        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body)) {
            throw new RuntimeException('Unexpected JSON response from Reapit');
        }

        return $body;
    }

    /**
     * Iterate through every page; errors propagate so failed imports cannot be marked complete.
     */
    public function pages(string $path, array $query = [], ?int $maxResults = null): iterable
    {
        if ($maxResults !== null && $maxResults < 1) {
            throw new \InvalidArgumentException('The maximum result count must be positive.');
        }

        $page = 1;
        $received = 0;
        do {
            $pageSize = min(100, $maxResults === null ? 100 : $maxResults - $received);
            $result = $this->get($path, array_merge($query, [
                'pageSize' => $pageSize,
                'pageNumber' => $page,
            ]));
            $embedded = $result['_embedded'] ?? [];
            if (!is_array($embedded) || !array_is_list($embedded)) {
                throw new RuntimeException('Malformed Reapit collection');
            }

            // A limit is a total across all pages, not the number of pages.
            // The final page may be shorter than the requested page size.
            $batch = array_slice($embedded, 0, $maxResults === null ? null : $maxResults - $received);
            $received += count($batch);
            yield $batch;

            $total = (int) ($result['totalPageCount'] ?? 0);
            $page++;
        } while ($page <= $total && ($maxResults === null || $received < $maxResults));
    }

    /**
     * Reapit expects repeated query keys (lettingStatus=toLet&lettingStatus=tenancyCurrent)
     * rather than PHP/Guzzle's indexed array convention.
     */
    private function encodeQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $item);
            }
        }
        return implode('&', $parts);
    }

    private function accessToken(): string
    {
        if ($this->token !== null && time() < $this->expiresAt) {
            return $this->token;
        }

        $response = $this->httpClient->request('POST', 'https://connect.reapit.cloud/token', [
            'auth' => [$this->setting('REAPIT_CLIENT_ID'), $this->setting('REAPIT_CLIENT_SECRET')],
            'form_params' => ['grant_type' => 'client_credentials', 'client_id' => $this->setting('REAPIT_CLIENT_ID')],
            'timeout' => 20,
            'http_errors' => true,
        ]);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Reapit token response did not contain an access token');
        }

        $this->token = (string) $payload['access_token'];
        $this->expiresAt = time() + max(1, (int) ($payload['expires_in'] ?? 3600) - 60);
        return $this->token;
    }

    private function setting(string $name): string
    {
        $value = Environment::getEnv($name);
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }
        return $value;
    }
}
