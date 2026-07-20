<?php

declare(strict_types=1);

namespace Phpresent\Shared\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Serves `docs/openapi.yaml` (the hand-written spec covering the full REST
 * surface, §23) as JSON, since Swagger UI and most tooling expect a URL
 * rather than a checked-in file. Parsed on every request rather than
 * cached — this endpoint is low-traffic developer tooling, not a hot path.
 */
final readonly class OpenApiSpecHandler implements RequestHandlerInterface
{
    public function __construct(private string $specPath)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($this->specPath);

        return new JsonResponse($spec);
    }
}
