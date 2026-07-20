<?php

declare(strict_types=1);

namespace Phpresent\Presentation\Domain\ValueObject;

/**
 * Per-display presentation settings (SDD §7). `theme` is a passthrough
 * string id/slug for now — the Theme module (§15 roadmap) doesn't exist
 * yet, so this doesn't reference it.
 */
final readonly class DisplaySettings
{
    public function __construct(
        public ?string $theme = null,
        public int $safeAreaPercent = 5,
        public float $fontScale = 1.0,
        public bool $showLowerThird = false,
        public ?string $watermarkText = null,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @return array{theme: ?string, safeAreaPercent: int, fontScale: float, showLowerThird: bool, watermarkText: ?string}
     */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
            'safeAreaPercent' => $this->safeAreaPercent,
            'fontScale' => $this->fontScale,
            'showLowerThird' => $this->showLowerThird,
            'watermarkText' => $this->watermarkText,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $theme = $data['theme'] ?? null;
        $watermarkText = $data['watermarkText'] ?? null;

        return new self(
            theme: is_string($theme) ? $theme : null,
            safeAreaPercent: is_scalar($data['safeAreaPercent'] ?? null) ? (int) $data['safeAreaPercent'] : 5,
            fontScale: is_scalar($data['fontScale'] ?? null) ? (float) $data['fontScale'] : 1.0,
            showLowerThird: (bool) ($data['showLowerThird'] ?? false),
            watermarkText: is_string($watermarkText) ? $watermarkText : null,
        );
    }
}
