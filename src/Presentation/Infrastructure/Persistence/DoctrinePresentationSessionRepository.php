<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Presentation\Domain\Entity\PresentationSession;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;

final class DoctrinePresentationSessionRepository implements PresentationSessionRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function current(): PresentationSession
    {
        /** @var PresentationSession|null $session */
        $session = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(PresentationSession::class, 's')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($session === null) {
            $session = new PresentationSession();
            $this->save($session);
        }

        return $session;
    }

    public function save(PresentationSession $session): void
    {
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }
}
