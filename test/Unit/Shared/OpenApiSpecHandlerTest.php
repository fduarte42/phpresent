<?php

declare(strict_types=1);

use Laminas\Diactoros\ServerRequest;
use Phpresent\Shared\Presentation\Http\Handler\OpenApiDocsHandler;
use Phpresent\Shared\Presentation\Http\Handler\OpenApiSpecHandler;

it('serves the checked-in openapi.yaml as JSON with the expected top-level shape', function (): void {
    $handler = new OpenApiSpecHandler(dirname(__DIR__, 3) . '/docs/openapi.yaml');

    $response = $handler->handle(new ServerRequest());

    expect($response->getHeaderLine('Content-Type'))->toContain('application/json');

    /** @var array<string, mixed> $body */
    $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

    expect($body['openapi'])->toBe('3.0.3');
    expect($body)->toHaveKeys(['info', 'paths', 'components']);
    expect($body['paths'])->toHaveKey('/api/backup/export');
});

it('renders a Swagger UI page pointing at the spec endpoint', function (): void {
    $handler = new OpenApiDocsHandler();

    $response = $handler->handle(new ServerRequest());
    $html = (string) $response->getBody();

    expect($response->getHeaderLine('Content-Type'))->toContain('text/html');
    expect($html)->toContain('/api/openapi.json');
    expect($html)->toContain('SwaggerUIBundle');
});
