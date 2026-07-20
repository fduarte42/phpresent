<?php

declare(strict_types=1);

use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Exception\InvalidThemeScopeException;
use Phpresent\Theme\Domain\ValueObject\TextAlign;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;

it('constructs a valid Global theme with no target', function (): void {
    $theme = new Theme('Default', ThemeScope::Global);

    expect($theme->scope())->toBe(ThemeScope::Global);
    expect($theme->songExternalId())->toBeNull();
    expect($theme->sectionType())->toBeNull();
    expect($theme->fontSizeScale())->toBe(1.0);
    expect($theme->textAlign())->toBe(TextAlign::Center);
});

it('rejects a Global theme with a song or section target', function (): void {
    expect(fn () => new Theme('X', ThemeScope::Global, songExternalId: 'sbp-1'))
        ->toThrow(InvalidThemeScopeException::class);
    expect(fn () => new Theme('X', ThemeScope::Global, sectionType: 'chorus'))
        ->toThrow(InvalidThemeScopeException::class);
});

it('requires a songExternalId for a Song-scoped theme', function (): void {
    expect(fn () => new Theme('X', ThemeScope::Song))->toThrow(InvalidThemeScopeException::class);

    $theme = new Theme('X', ThemeScope::Song, songExternalId: 'sbp-1');
    expect($theme->songExternalId())->toBe('sbp-1');
});

it('rejects a Song-scoped theme that also sets a sectionType', function (): void {
    expect(fn () => new Theme('X', ThemeScope::Song, songExternalId: 'sbp-1', sectionType: 'chorus'))
        ->toThrow(InvalidThemeScopeException::class);
});

it('requires a sectionType for a Section-scoped theme', function (): void {
    expect(fn () => new Theme('X', ThemeScope::Section))->toThrow(InvalidThemeScopeException::class);

    $theme = new Theme('X', ThemeScope::Section, sectionType: 'chorus');
    expect($theme->sectionType())->toBe('chorus');
});

it('rejects a Section-scoped theme that also sets a songExternalId', function (): void {
    expect(fn () => new Theme('X', ThemeScope::Section, songExternalId: 'sbp-1', sectionType: 'chorus'))
        ->toThrow(InvalidThemeScopeException::class);
});

it('re-validates the target invariant on update', function (): void {
    $theme = new Theme('X', ThemeScope::Global);

    expect(fn () => $theme->update(
        name: 'X',
        scope: ThemeScope::Song,
        songExternalId: null,
        sectionType: null,
        backgroundColor: null,
        backgroundMediaAssetId: null,
        fontFamily: null,
        fontColor: null,
        fontSizeScale: 1.0,
        textAlign: TextAlign::Center,
        now: new DateTimeImmutable(),
    ))->toThrow(InvalidThemeScopeException::class);
});
