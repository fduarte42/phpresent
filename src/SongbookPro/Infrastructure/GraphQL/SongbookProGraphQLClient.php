<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Phpresent\SongbookPro\Domain\Exception\SongbookProApiException;
use Phpresent\SongbookPro\Infrastructure\Cache\ETagCacheInterface;
use Psr\Log\LoggerInterface;

final readonly class SongbookProGraphQLClient implements GraphQLClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RateLimiter $rateLimiter,
        private ETagCacheInterface $etagCache,
        private LoggerInterface $logger,
        private string $apiUrl,
        private string $apiToken,
        private int $maxRetries = 3,
        private int $retryBaseDelayMs = 200,
    ) {
    }

    public function query(string $query, array $variables = []): GraphQLResponse
    {
        $cacheKey = $query . json_encode($variables, JSON_THROW_ON_ERROR);
        $cached = $this->etagCache->get($cacheKey);

        $attempt = 0;
        while (true) {
            $attempt++;
            $this->rateLimiter->acquire();

            try {
                return $this->attempt($query, $variables, $cacheKey, $cached);
            } catch (SongbookProApiException $exception) {
                if ($attempt > $this->maxRetries) {
                    throw $exception;
                }

                $delayMs = $this->retryBaseDelayMs * (2 ** ($attempt - 1));
                $this->logger->warning('Retrying SongbookPro GraphQL request', [
                    'attempt' => $attempt,
                    'delay_ms' => $delayMs,
                    'reason' => $exception->getMessage(),
                ]);
                usleep($delayMs * 1_000);
            }
        }
    }

    /**
     * @param array<string, mixed> $variables
     * @param array{etag: string, data: array<string, mixed>}|null $cached
     */
    private function attempt(string $query, array $variables, string $cacheKey, ?array $cached): GraphQLResponse
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->apiToken,
        ];
        if ($cached !== null) {
            $headers['If-None-Match'] = $cached['etag'];
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => $headers,
                'json' => ['query' => $query, 'variables' => $variables],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            throw SongbookProApiException::fromTransportFailure($exception);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 304 && $cached !== null) {
            return new GraphQLResponse(data: $cached['data'], etag: $cached['etag'], fromCache: true);
        }

        if ($statusCode === 429) {
            throw SongbookProApiException::rateLimited();
        }

        if ($statusCode >= 500) {
            throw new SongbookProApiException("SongbookPro GraphQL API returned HTTP {$statusCode}");
        }

        /** @var array{data?: array<string, mixed>, errors?: array<int, array{message: string}>} $body */
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (isset($body['errors']) && $body['errors'] !== []) {
            throw SongbookProApiException::fromGraphQLErrors($body['errors']);
        }

        $data = $body['data'] ?? [];
        $etag = $response->getHeaderLine('ETag');

        if ($etag !== '') {
            $this->etagCache->put($cacheKey, $etag, $data);
        }

        return new GraphQLResponse(data: $data, etag: $etag !== '' ? $etag : null, fromCache: false);
    }
}
