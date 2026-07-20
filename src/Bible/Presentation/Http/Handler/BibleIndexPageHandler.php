<?php

declare(strict_types=1);

namespace Phpresent\Bible\Presentation\Http\Handler;

use Phpresent\Bible\Application\Query\ListBibleTranslationsHandler;
use Phpresent\Bible\Application\Query\ListBibleTranslationsQuery;
use Phpresent\Bible\Application\Query\ListBookmarksHandler;
use Phpresent\Bible\Application\Query\ListBookmarksQuery;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class BibleIndexPageHandler implements RequestHandlerInterface
{
    public function __construct(
        private ListBibleTranslationsHandler $listBibleTranslationsHandler,
        private ListBookmarksHandler $listBookmarksHandler,
        private InertiaResponseFactory $inertia,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inertia->render($request, 'Bible/Index', [
            'translations' => ($this->listBibleTranslationsHandler)(new ListBibleTranslationsQuery()),
            'bookmarks' => ($this->listBookmarksHandler)(new ListBookmarksQuery()),
        ]);
    }
}
