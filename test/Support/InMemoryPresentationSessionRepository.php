<?php

declare(strict_types=1);

namespace PhpresentTest\Support;

use Phpresent\Presentation\Domain\Entity\PresentationSession;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

final class InMemoryPresentationSessionRepository implements PresentationSessionRepositoryInterface
{
    private ?PresentationSession $session = null;

    public function current(): PresentationSession
    {
        if ($this->session === null) {
            $this->session = new PresentationSession();
        }

        return $this->session;
    }

    public function save(PresentationSession $session): void
    {
        $this->session = $session;
    }
}
