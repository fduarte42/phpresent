<?php

declare(strict_types=1);

namespace Phpresent\SongbookPro\Infrastructure\GraphQL;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Phpresent\SongbookPro\Domain\Exception\SongbookProApiException;
use Phpresent\SongbookPro\Domain\Service\AccessTokenProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Talks to the real, verified SongbookPro Groups endpoint (SDD §6.1):
 * `POST https://songbookpro-groups-prod.azurewebsites.net/graphql`, a single
 * Apollo Server instance that always responds with a JSON *array* (the
 * official web client's request-batching format) even for a single-operation
 * request, so every response is unwrapped at `[0]` here.
 */
final readonly class SongbookProGraphQLClient implements GraphQLClientInterface
{
    public function __construct(
        private ClientInterface $httpClient,
        private RateLimiter $rateLimiter,
        private AccessTokenProviderInterface $tokenProvider,
        private LoggerInterface $logger,
        private string $apiUrl,
        private int $maxRetries = 3,
        private int $retryBaseDelayMs = 200,
    ) {
    }

    public function query(string $query, array $variables = []): GraphQLResponse
    {
        $attempt = 0;
        while (true) {
            $attempt++;
            $this->rateLimiter->acquire();

            try {
                return $this->attempt($query, $variables);
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
     */
    private function attempt(string $query, array $variables): GraphQLResponse
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->tokenProvider->getAccessToken(),
                ],
                // A bare single-operation body is accepted the same way as the
                // official client's batched array (§6.1) — no need to wrap it.
                'json' => ['query' => $query, 'variables' => $variables],
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            throw SongbookProApiException::fromTransportFailure($exception);
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401) {
            throw SongbookProApiException::unauthenticated();
        }

        if ($statusCode === 429) {
            throw SongbookProApiException::rateLimited();
        }

        if ($statusCode >= 500) {
            throw new SongbookProApiException("SongbookPro GraphQL API returned HTTP {$statusCode}");
        }

        /** @var list<array{data?: array<string, mixed>, errors?: array<int, array{message: string}>}> $body */
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $operationResult = $body[0] ?? [];

        if (isset($operationResult['errors']) && $operationResult['errors'] !== []) {
            throw SongbookProApiException::fromGraphQLErrors($operationResult['errors']);
        }

        return new GraphQLResponse(data: $operationResult['data'] ?? []);
    }
}
