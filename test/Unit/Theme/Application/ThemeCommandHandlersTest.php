<?php

declare(strict_types=1);

use Phpresent\Theme\Application\Command\CreateThemeCommand;
use Phpresent\Theme\Application\Command\CreateThemeHandler;
use Phpresent\Theme\Application\Command\RemoveThemeCommand;
use Phpresent\Theme\Application\Command\RemoveThemeHandler;
use Phpresent\Theme\Application\Command\UpdateThemeCommand;
use Phpresent\Theme\Application\Command\UpdateThemeHandler;
use Phpresent\Theme\Application\Query\GetThemeHandler;
use Phpresent\Theme\Application\Query\GetThemeQuery;
use Phpresent\Theme\Application\Query\ListThemesHandler;
use Phpresent\Theme\Application\Query\ListThemesQuery;
use Phpresent\Theme\Domain\Exception\InvalidThemeScopeException;
use PhpresentTest\Support\InMemoryThemeRepository;

it('creates a Global theme and makes it findable by id', function (): void {
    $repository = new InMemoryThemeRepository();
    $created = (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'Default', scope: 'global'));

    $found = (new GetThemeHandler($repository))(new GetThemeQuery($created->id));

    expect($found)->not->toBeNull();
    expect($found->name)->toBe('Default');
    expect($found->scope)->toBe('global');
});

it('throws ValueError for an unknown scope', function (): void {
    $repository = new InMemoryThemeRepository();

    expect(fn () => (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'X', scope: 'not-a-scope')))
        ->toThrow(ValueError::class);
});

it('throws InvalidThemeScopeException when a Song-scoped theme has no songExternalId', function (): void {
    $repository = new InMemoryThemeRepository();

    expect(fn () => (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'X', scope: 'song')))
        ->toThrow(InvalidThemeScopeException::class);
});

it('updates an existing theme', function (): void {
    $repository = new InMemoryThemeRepository();
    $created = (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'Default', scope: 'global'));

    $updated = (new UpdateThemeHandler($repository))(new UpdateThemeCommand(
        id: $created->id,
        name: 'Renamed',
        scope: 'section',
        sectionType: 'chorus',
        fontSizeScale: 1.5,
    ));

    expect($updated->name)->toBe('Renamed');
    expect($updated->scope)->toBe('section');
    expect($updated->sectionType)->toBe('chorus');
    expect($updated->fontSizeScale)->toBe(1.5);
});

it('returns null when updating an unknown theme', function (): void {
    $repository = new InMemoryThemeRepository();

    $result = (new UpdateThemeHandler($repository))(new UpdateThemeCommand(
        id: '11111111-1111-1111-1111-111111111111',
        name: 'X',
        scope: 'global',
    ));

    expect($result)->toBeNull();
});

it('removes a theme and reports whether one was found', function (): void {
    $repository = new InMemoryThemeRepository();
    $created = (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'Default', scope: 'global'));

    expect((new RemoveThemeHandler($repository))(new RemoveThemeCommand($created->id)))->toBeTrue();
    expect((new RemoveThemeHandler($repository))(new RemoveThemeCommand($created->id)))->toBeFalse();
    expect((new GetThemeHandler($repository))(new GetThemeQuery($created->id)))->toBeNull();
});

it('lists all themes', function (): void {
    $repository = new InMemoryThemeRepository();
    (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'A', scope: 'global'));
    (new CreateThemeHandler($repository))(new CreateThemeCommand(name: 'B', scope: 'song', songExternalId: 'sbp-1'));

    expect((new ListThemesHandler($repository))(new ListThemesQuery()))->toHaveCount(2);
});
