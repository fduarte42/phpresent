<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\DTO;

use Phpresent\Presentation\Domain\Entity\Display;

final readonly class DisplayDto
{
    /**
     * @param array{theme: ?string, safeAreaPercent: int, fontScale: float, showLowerThird: bool, watermarkText: ?string} $settings
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public array $settings,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Display $display): self
    {
        return new self(
            id: $display->id()->toString(),
            name: $display->name(),
            role: $display->role()->value,
            settings: $display->settings()->toArray(),
            createdAt: $display->createdAt()->format(DATE_ATOM),
            updatedAt: $display->updatedAt()->format(DATE_ATOM),
        );
    }
}
