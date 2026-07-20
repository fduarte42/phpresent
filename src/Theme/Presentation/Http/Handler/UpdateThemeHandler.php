<?php

declare(strict_types=1);

namespace Phpresent\Theme\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Phpresent\Theme\Application\Command\UpdateThemeCommand;
use Phpresent\Theme\Application\Command\UpdateThemeHandler as UpdateThemeCommandHandler;
use Phpresent\Theme\Domain\Exception\InvalidThemeScopeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ValueError;

final readonly class UpdateThemeHandler implements RequestHandlerInterface
{
    public function __construct(private UpdateThemeCommandHandler $updateThemeHandler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var RouteResult $routeResult */
        $routeResult = $request->getAttribute(RouteResult::class);
        $id = (string) $routeResult->getMatchedParams()['id'];

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $theme = ($this->updateThemeHandler)(new UpdateThemeCommand(
                id: $id,
                name: is_string($body['name'] ?? null) ? $body['name'] : '',
                scope: is_string($body['scope'] ?? null) ? $body['scope'] : '',
                songExternalId: is_string($body['songExternalId'] ?? null) ? $body['songExternalId'] : null,
                sectionType: is_string($body['sectionType'] ?? null) ? $body['sectionType'] : null,
                backgroundColor: is_string($body['backgroundColor'] ?? null) ? $body['backgroundColor'] : null,
                backgroundMediaAssetId: is_string($body['backgroundMediaAssetId'] ?? null)
                    ? $body['backgroundMediaAssetId'] : null,
                fontFamily: is_string($body['fontFamily'] ?? null) ? $body['fontFamily'] : null,
                fontColor: is_string($body['fontColor'] ?? null) ? $body['fontColor'] : null,
                fontSizeScale: is_numeric($body['fontSizeScale'] ?? null) ? (float) $body['fontSizeScale'] : 1.0,
                textAlign: is_string($body['textAlign'] ?? null) ? $body['textAlign'] : 'center',
            ));
        } catch (ValueError|InvalidThemeScopeException $exception) {
            return new JsonResponse(['title' => $exception->getMessage(), 'status' => 400], 400);
        }

        if ($theme === null) {
            return new JsonResponse(['title' => 'Theme not found', 'status' => 404], 404);
        }

        return new JsonResponse(['data' => $theme]);
    }
}
