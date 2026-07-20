<?php

declare(strict_types=1);

use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;

it('defaults to default settings when none are given', function (): void {
    $display = new Display('Main Screen', DisplayRole::Main);

    expect($display->settings())->toEqual(DisplaySettings::default());
});

it('updates name, role and settings in place', function (): void {
    $display = new Display('Main Screen', DisplayRole::Main);
    $originalUpdatedAt = $display->updatedAt();

    $newSettings = new DisplaySettings(theme: 'dark', fontScale: 1.5);
    $now = $originalUpdatedAt->modify('+1 hour');
    $display->update('Confidence Monitor', DisplayRole::ConfidenceMonitor, $newSettings, $now);

    expect($display->name())->toBe('Confidence Monitor');
    expect($display->role())->toBe(DisplayRole::ConfidenceMonitor);
    expect($display->settings())->toEqual($newSettings);
    expect($display->updatedAt())->toBe($now);
});

it('round-trips settings through toArray/fromArray without loss', function (): void {
    $settings = new DisplaySettings(
        theme: 'dark',
        safeAreaPercent: 8,
        fontScale: 1.25,
        showLowerThird: true,
        watermarkText: 'Sunday Service',
    );

    expect(DisplaySettings::fromArray($settings->toArray()))->toEqual($settings);
});
