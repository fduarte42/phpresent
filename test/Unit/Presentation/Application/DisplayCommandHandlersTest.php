<?php

declare(strict_types=1);

use Phpresent\Presentation\Application\Command\CreateDisplayCommand;
use Phpresent\Presentation\Application\Command\CreateDisplayHandler;
use Phpresent\Presentation\Application\Command\RemoveDisplayCommand;
use Phpresent\Presentation\Application\Command\RemoveDisplayHandler;
use Phpresent\Presentation\Application\Command\UpdateDisplayCommand;
use Phpresent\Presentation\Application\Command\UpdateDisplayHandler;
use Phpresent\Presentation\Application\Query\GetDisplayHandler;
use Phpresent\Presentation\Application\Query\GetDisplayQuery;
use Phpresent\Presentation\Application\Query\ListDisplaysHandler;
use Phpresent\Presentation\Application\Query\ListDisplaysQuery;
use PhpresentTest\Support\InMemoryDisplayRepository;

it('creates a display and makes it findable by id', function (): void {
    $repository = new InMemoryDisplayRepository();
    $created = (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'Main Screen', role: 'main'));

    $found = (new GetDisplayHandler($repository))(new GetDisplayQuery($created->id));

    expect($found)->not->toBeNull();
    expect($found->name)->toBe('Main Screen');
    expect($found->role)->toBe('main');
});

it('throws ValueError for an unknown role', function (): void {
    $repository = new InMemoryDisplayRepository();

    expect(fn () => (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'X', role: 'not-a-role')))
        ->toThrow(ValueError::class);
});

it('updates an existing display', function (): void {
    $repository = new InMemoryDisplayRepository();
    $created = (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'Main Screen', role: 'main'));

    $updated = (new UpdateDisplayHandler($repository))(new UpdateDisplayCommand(
        id: $created->id,
        name: 'Confidence Monitor',
        role: 'confidence_monitor',
        settings: ['theme' => 'dark'],
    ));

    expect($updated->name)->toBe('Confidence Monitor');
    expect($updated->role)->toBe('confidence_monitor');
    expect($updated->settings['theme'])->toBe('dark');
});

it('returns null when updating an unknown display', function (): void {
    $repository = new InMemoryDisplayRepository();

    $result = (new UpdateDisplayHandler($repository))(new UpdateDisplayCommand(
        id: '11111111-1111-1111-1111-111111111111',
        name: 'X',
        role: 'main',
    ));

    expect($result)->toBeNull();
});

it('removes a display and reports whether one was found', function (): void {
    $repository = new InMemoryDisplayRepository();
    $created = (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'Main Screen', role: 'main'));

    expect((new RemoveDisplayHandler($repository))(new RemoveDisplayCommand($created->id)))->toBeTrue();
    expect((new RemoveDisplayHandler($repository))(new RemoveDisplayCommand($created->id)))->toBeFalse();
    expect((new GetDisplayHandler($repository))(new GetDisplayQuery($created->id)))->toBeNull();
});

it('lists all displays', function (): void {
    $repository = new InMemoryDisplayRepository();
    (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'A', role: 'main'));
    (new CreateDisplayHandler($repository))(new CreateDisplayCommand(name: 'B', role: 'operator'));

    expect((new ListDisplaysHandler($repository))(new ListDisplaysQuery()))->toHaveCount(2);
});
