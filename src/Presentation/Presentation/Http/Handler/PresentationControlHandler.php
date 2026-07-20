<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Presentation\Http\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Phpresent\Presentation\Application\Command\JumpToSlideCommand;
use Phpresent\Presentation\Application\Command\JumpToSlideHandler;
use Phpresent\Presentation\Application\Command\NextSlideCommand;
use Phpresent\Presentation\Application\Command\NextSlideHandler;
use Phpresent\Presentation\Application\Command\PreviousSlideCommand;
use Phpresent\Presentation\Application\Command\PreviousSlideHandler;
use Phpresent\Presentation\Application\Command\SetBlankedCommand;
use Phpresent\Presentation\Application\Command\SetBlankedHandler;
use Phpresent\Presentation\Application\Command\SetEmergencyMessageCommand;
use Phpresent\Presentation\Application\Command\SetEmergencyMessageHandler;
use Phpresent\Presentation\Application\Command\SetFontSizeAdjustCommand;
use Phpresent\Presentation\Application\Command\SetFontSizeAdjustHandler;
use Phpresent\Presentation\Application\Command\SetFrozenCommand;
use Phpresent\Presentation\Application\Command\SetFrozenHandler;
use Phpresent\Presentation\Application\Command\SetLyricsHiddenCommand;
use Phpresent\Presentation\Application\Command\SetLyricsHiddenHandler;
use Phpresent\Presentation\Application\DTO\PresentationSessionDto;
use Phpresent\Presentation\Domain\Exception\InvalidSlideIndexException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A single endpoint for every live-control command (SDD §7: Next, Previous,
 * JumpToSlide, Black, Freeze, HideLyrics, FontSizeAdjust, EmergencyMessage)
 * rather than one route per command. Each remains its own Command/Handler
 * pair in the Application layer — independently unit-testable — this only
 * collapses the *HTTP routing* surface, since 1:1 REST-route-to-command
 * mapping isn't required by CQRS and would mean eight nearly identical
 * three-line HTTP handler classes for no behavioral benefit.
 */
final readonly class PresentationControlHandler implements RequestHandlerInterface
{
    public function __construct(
        private NextSlideHandler $nextSlideHandler,
        private PreviousSlideHandler $previousSlideHandler,
        private JumpToSlideHandler $jumpToSlideHandler,
        private SetBlankedHandler $setBlankedHandler,
        private SetFrozenHandler $setFrozenHandler,
        private SetLyricsHiddenHandler $setLyricsHiddenHandler,
        private SetFontSizeAdjustHandler $setFontSizeAdjustHandler,
        private SetEmergencyMessageHandler $setEmergencyMessageHandler,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $action = is_string($body['action'] ?? null) ? $body['action'] : '';
        $value = $body['value'] ?? null;

        try {
            $session = match ($action) {
                'next' => ($this->nextSlideHandler)(new NextSlideCommand()),
                'previous' => ($this->previousSlideHandler)(new PreviousSlideCommand()),
                'jump' => ($this->jumpToSlideHandler)(new JumpToSlideCommand((int) $value)),
                'blank' => ($this->setBlankedHandler)(new SetBlankedCommand((bool) $value)),
                'freeze' => ($this->setFrozenHandler)(new SetFrozenCommand((bool) $value)),
                'hideLyrics' => ($this->setLyricsHiddenHandler)(new SetLyricsHiddenCommand((bool) $value)),
                'fontSize' => ($this->setFontSizeAdjustHandler)(new SetFontSizeAdjustCommand((int) $value)),
                'emergencyMessage' => ($this->setEmergencyMessageHandler)(
                    new SetEmergencyMessageCommand(is_string($value) ? $value : null),
                ),
                default => null,
            };
        } catch (InvalidSlideIndexException $exception) {
            return new JsonResponse(['title' => $exception->getMessage(), 'status' => 400], 400);
        }

        if (!$session instanceof PresentationSessionDto) {
            return new JsonResponse(['title' => 'Unknown control action', 'status' => 400], 400);
        }

        return new JsonResponse(['data' => $session]);
    }
}
