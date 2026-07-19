<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Http;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Minimal server-side Inertia.js protocol adapter: full HTML document on a
 * normal navigation, JSON page object when the client sends
 * `X-Inertia: true`. See https://inertiajs.com/the-protocol.
 */
final readonly class InertiaResponseFactory
{
    public function __construct(
        private string $assetVersion,
        private string $viteManifestPath,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(ServerRequestInterface $request, string $component, array $props = []): ResponseInterface
    {
        $page = [
            'component' => $component,
            'props' => $props,
            'url' => $request->getUri()->getPath() . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : ''),
            'version' => $this->assetVersion,
        ];

        if ($request->getHeaderLine('X-Inertia') === 'true') {
            $response = new JsonResponse($page);

            return $response
                ->withHeader('X-Inertia', 'true')
                ->withHeader('Vary', 'X-Inertia');
        }

        $html = $this->renderShell($page);

        $response = new Response();
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array{component: string, props: array<string, mixed>, url: string, version: string} $page
     */
    private function renderShell(array $page): string
    {
        $encodedPage = htmlspecialchars(
            json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ENT_QUOTES,
            'UTF-8',
        );

        [$scriptTag, $styleTags] = $this->assetTags();

        return <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Phpresent</title>
                {$styleTags}
            </head>
            <body>
                <div id="app" data-page="{$encodedPage}"></div>
                {$scriptTag}
            </body>
            </html>
            HTML;
    }

    /**
     * @return array{0: string, 1: string} [scriptTag, styleTags]
     */
    private function assetTags(): array
    {
        if (!is_file($this->viteManifestPath)) {
            // Vite dev server proxy — see resources/js/vite-dev-entry.
            return ['<script type="module" src="http://localhost:5173/@vite/client"></script>' .
                '<script type="module" src="http://localhost:5173/app.ts"></script>', ''];
        }

        /** @var array<string, array{file: string, css?: list<string>}> $manifest */
        $manifest = json_decode((string) file_get_contents($this->viteManifestPath), true, flags: JSON_THROW_ON_ERROR);
        $entry = $manifest['app.ts'] ?? null;

        if ($entry === null) {
            return ['', ''];
        }

        $script = sprintf('<script type="module" src="/build/%s"></script>', $entry['file']);
        $styles = implode('', array_map(
            static fn (string $css): string => sprintf('<link rel="stylesheet" href="/build/%s">', $css),
            $entry['css'] ?? [],
        ));

        return [$script, $styles];
    }
}
