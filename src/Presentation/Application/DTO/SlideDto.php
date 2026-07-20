<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Application\DTO;

use Phpresent\Presentation\Domain\ValueObject\Slide;

final readonly class SlideDto
{
    /**
     * @param string[] $lines
     */
    public function __construct(
        public array $lines,
        public ?string $sectionType,
        public ?string $sectionLabel,
    ) {
    }

    public static function fromValueObject(Slide $slide): self
    {
        return new self($slide->lines, $slide->sectionType, $slide->sectionLabel);
    }
}
