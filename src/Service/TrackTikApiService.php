<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class TrackTikApiService
{
    private HttpClientInterface $client;

    private ?string $accessToken;
    private ?string $refreshToken;
    private ?\DateTimeImmutable $tokenExpiry;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        ?string $initialAccessToken,
        ?string $refreshToken,
        private readonly LoggerInterface $logger
    ) {
        $this->client = HttpClient::create([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout'  => 30,
        ]);

        $this->accessToken  = $initialAccessToken ?: null;
        $this->refreshToken = $refreshToken ?: null;
        $this->tokenExpiry  = null;
    }

    private function ensureToken(): void
    {
        $now = new \DateTimeImmutable('now');

        // If we already have an access token and no known expiry, try it optimistically.
        if ($this->accessToken && !$this->tokenExpiry) {
            return;
        }
        // If we have a token with a future expiry, we’re good.
        if ($this->accessToken && $this->tokenExpiry && $this->tokenExpiry > $now) {
            return;
        }
        // Otherwise, refresh if possible.
        if ($this->refreshToken) {
            $this->refreshAccessToken();
            return;
        }

        throw new \RuntimeException('TrackTik: no access token available. Set TRACKTIK_REFRESH_TOKEN (and optionally TRACKTIK_ACCESS_TOKEN).');
    }

    private function refreshAccessToken(): void
    {
        try {

            $response = $this->client->request('POST', '/rest/oauth2/access_token', [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded', 'Idempotency-Key' => "asdasddsa"],
                'body'    => http_build_query([
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                ]),
            ]);




            $status = $response->getStatusCode();
            $data   = $response->toArray(false);

            if ($status >= 400) {
                $this->logger->error('TrackTik token endpoint error', [
                    'status' => $status,
                    'body'   => $response->getContent(false),
                ]);
                throw new \RuntimeException('Failed to refresh access token');
            }

            if (empty($data['access_token'])) {
                throw new \RuntimeException('Token refresh returned no access_token');
            }

            $this->accessToken = $data['access_token'];

            // Set expiry (refresh a minute early)
            if (!empty($data['expires_in'])) {
                $this->tokenExpiry = (new \DateTimeImmutable('now'))
                    ->modify('+' . max(1, ((int) $data['expires_in']) - 60) . ' seconds');
            } else {
                $this->tokenExpiry = null;
            }

            // Some tenants rotate refresh tokens
            if (!empty($data['refresh_token'])) {
                $this->refreshToken = $data['refresh_token'];
            }

            $this->logger->info('TrackTik: access token refreshed', [
                'expires_at' => $this->tokenExpiry?->format(\DateTimeInterface::ATOM),
            ]);
        } catch (TransportExceptionInterface|ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface $e) {
            $this->logger->error('TrackTik token refresh failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to refresh token: ' . $e->getMessage());
        }
    }

    private function authHeaders(array $extra = []): array
    {
        $this->ensureToken();

        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept'        => 'application/json',
        ], $extra);

        return $headers;
    }

    private function requestWithAutoRefresh(string $method, string $url, array $opts = []): array
    {
        try {
            $opts['headers'] = array_merge(
                $this->authHeaders(),
                $opts['headers'] ?? []
            );

            $response = $this->client->request($method, $url, $opts);
            return $response->toArray(false);

        } catch (ClientExceptionInterface $e) {
            $status = method_exists($e, 'getResponse') ? $e->getResponse()?->getStatusCode() : null;

            if ($status === 401 && $this->refreshToken) {
                $this->logger->warning('401 received, refreshing token and retrying...');
                $this->refreshAccessToken();

                $opts['headers'] = array_merge(
                    $this->authHeaders(),
                    $opts['headers'] ?? []
                );

                $response = $this->client->request($method, $url, $opts);
                return $response->toArray(false);
            }

            throw $e;
        }
    }

    // ---- Public API wrappers ----

    public function createEmployee(array $employeeData): array
    {
        return $this->requestWithAutoRefresh('POST', '/rest/v1/employees', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => $employeeData,
        ]);
    }

    public function updateEmployee(int $trackTikId, array $employeeData): array
    {
        return $this->requestWithAutoRefresh('PATCH', "/rest/v1/employees/$trackTikId", [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => $employeeData,
        ]);
    }

    public function getEmployee(int $trackTikId): array
    {
        return $this->requestWithAutoRefresh('GET', "/rest/v1/employees/$trackTikId");
    }

    public function getEmployeeSchema(): array
    {
        return $this->requestWithAutoRefresh('GET', '/rest/v1/employees/schema');
    }

    public function deleteEmployee(int $trackTikId): bool
    {
        $resp = $this->client->request('DELETE', "/rest/v1/employees/$trackTikId", [
            'headers' => $this->authHeaders(),
        ]);
        return $resp->getStatusCode() >= 200 && $resp->getStatusCode() < 300;
    }

    public function searchEmployees(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $query = array_merge(['limit' => $limit, 'offset' => $offset], $filters);
        return $this->requestWithAutoRefresh('GET', '/rest/v1/employees?' . http_build_query($query));
    }

    public function testConnection(): bool
    {
        try {
            $this->ensureToken();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('TrackTik API connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAccessToken(): ?string { return $this->accessToken; }
    public function isTokenValid(): bool
    {
        return (bool)$this->accessToken && (!$this->tokenExpiry || $this->tokenExpiry > new \DateTimeImmutable('now'));
    }
}
