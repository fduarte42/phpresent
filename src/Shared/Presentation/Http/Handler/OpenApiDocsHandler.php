<?php

declare(strict_types=1);

namespace Phpresent\Shared\Presentation\Http\Handler;

use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A static Swagger UI page for browsing `GET /api/openapi.json`. Not an
 * Inertia/Vue page — deliberately plain HTML loading the swagger-ui-dist
 * bundle from a CDN, since this is developer-facing tooling, not part of
 * the operator-facing app the rest of the frontend is built for.
 */
final readonly class OpenApiDocsHandler implements RequestHandlerInterface
{
    private const string HTML = <<<'HTML'
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <title>Phpresent API Docs</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui.css">
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                window.ui = SwaggerUIBundle({
                    url: '/api/openapi.json',
                    dom_id: '#swagger-ui',
                });
            </script>
        </body>
        </html>
        HTML;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse(self::HTML);
    }
}
