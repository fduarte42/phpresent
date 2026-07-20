<?php

declare(strict_types=1);

use Phpresent\Bible\Application\Query\GetBiblePassageHandler;
use Phpresent\Bible\Application\Query\GetBiblePassageQuery;
use Phpresent\Bible\Application\Query\ListBibleTranslationsHandler;
use Phpresent\Bible\Application\Query\ListBibleTranslationsQuery;
use Phpresent\Bible\Application\Query\SearchBibleHandler;
use Phpresent\Bible\Application\Query\SearchBibleQuery;
use Phpresent\Bible\Infrastructure\Plugin\LocalBibleProvider;
use Phpresent\Shared\Infrastructure\Plugin\PluginRegistry;

function makeBibleRegistry(): PluginRegistry
{
    return new PluginRegistry([new LocalBibleProvider()]);
}

it('lists translations across every registered provider', function (): void {
    $translations = (new ListBibleTranslationsHandler(makeBibleRegistry()))(new ListBibleTranslationsQuery());

    expect($translations)->toHaveCount(1);
    expect($translations[0]->providerId)->toBe('local-kjv');
    expect($translations[0]->id)->toBe('kjv');
});

it('routes search to whichever provider owns the translation id', function (): void {
    $results = (new SearchBibleHandler(makeBibleRegistry()))(new SearchBibleQuery('kjv', 'shepherd'));

    expect($results)->toHaveCount(1);
    expect($results[0]->book)->toBe('Psalm');
});

it('returns an empty list for an unrecognized translation id', function (): void {
    $results = (new SearchBibleHandler(makeBibleRegistry()))(new SearchBibleQuery('esv', 'shepherd'));

    expect($results)->toBe([]);
});

it('fetches a passage across registered providers', function (): void {
    $passage = (new GetBiblePassageHandler(makeBibleRegistry()))(
        new GetBiblePassageQuery('kjv', 'John', 3, 16, 17),
    );

    expect($passage)->not->toBeNull();
    expect($passage->verses)->toHaveCount(2);
});

it('returns null for a passage no registered provider recognizes', function (): void {
    $passage = (new GetBiblePassageHandler(makeBibleRegistry()))(
        new GetBiblePassageQuery('esv', 'John', 3, 16, 17),
    );

    expect($passage)->toBeNull();
});
