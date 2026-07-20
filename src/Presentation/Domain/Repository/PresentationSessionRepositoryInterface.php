<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\Repository;

use Phpresent\Presentation\Domain\Entity\PresentationSession;

interface PresentationSessionRepositoryInterface
{
    /**
     * Returns the one live session, creating and persisting it on first
     * access — see `PresentationSession`'s docblock for why there is no
     * `get(id)` here.
     */
    public function current(): PresentationSession;

    public function save(PresentationSession $session): void;
}
