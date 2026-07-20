<?php

declare(strict_types=1);

use Phpresent\Bible\Application\Command\CreateBookmarkCommand;
use Phpresent\Bible\Application\Command\CreateBookmarkHandler;
use Phpresent\Bible\Application\Command\RemoveBookmarkCommand;
use Phpresent\Bible\Application\Command\RemoveBookmarkHandler;
use Phpresent\Bible\Application\Query\ListBookmarksHandler;
use Phpresent\Bible\Application\Query\ListBookmarksQuery;
use PhpresentTest\Support\InMemoryBibleBookmarkRepository;

it('creates a bookmark and lists it', function (): void {
    $repository = new InMemoryBibleBookmarkRepository();
    $created = (new CreateBookmarkHandler($repository))(new CreateBookmarkCommand(
        translationId: 'kjv',
        book: 'Psalm',
        chapter: 23,
        label: 'Funeral service',
    ));

    expect($created->book)->toBe('Psalm');
    expect($created->label)->toBe('Funeral service');

    $listed = (new ListBookmarksHandler($repository))(new ListBookmarksQuery());
    expect($listed)->toHaveCount(1);
});

it('removes a bookmark and reports whether one was found', function (): void {
    $repository = new InMemoryBibleBookmarkRepository();
    $created = (new CreateBookmarkHandler($repository))(new CreateBookmarkCommand('kjv', 'Psalm', 23));

    expect((new RemoveBookmarkHandler($repository))(new RemoveBookmarkCommand($created->id)))->toBeTrue();
    expect((new RemoveBookmarkHandler($repository))(new RemoveBookmarkCommand($created->id)))->toBeFalse();
});
