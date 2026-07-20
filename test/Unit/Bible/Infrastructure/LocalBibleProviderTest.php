<?php

declare(strict_types=1);

use Phpresent\Bible\Infrastructure\Plugin\LocalBibleProvider;

it('lists exactly one translation', function (): void {
    $translations = (new LocalBibleProvider())->translations();

    expect($translations)->toHaveCount(1);
    expect($translations[0]->id)->toBe('kjv');
    expect($translations[0]->abbreviation)->toBe('KJV');
});

it('finds verses containing the search term, case-insensitively', function (): void {
    $results = (new LocalBibleProvider())->search('kjv', 'LOVE');

    $references = array_map(
        static fn ($verse) => "{$verse->book} {$verse->chapter}:{$verse->verse}",
        $results,
    );

    expect($references)->toContain('John 3:16');
    expect($references)->toContain('Romans 8:28');
});

it('returns an empty array for an unknown translation or blank query', function (): void {
    $provider = new LocalBibleProvider();

    expect($provider->search('esv', 'love'))->toBe([]);
    expect($provider->search('kjv', '   '))->toBe([]);
});

it('respects the search limit', function (): void {
    $results = (new LocalBibleProvider())->search('kjv', 'the', limit: 2);

    expect($results)->toHaveCount(2);
});

it('returns a full chapter when no verse range is given', function (): void {
    $passage = (new LocalBibleProvider())->getPassage('kjv', 'Psalm', 23);

    expect($passage)->not->toBeNull();
    expect($passage->verses)->toHaveCount(6);
    expect($passage->verses[0]->text)->toContain('The LORD is my shepherd');
});

it('respects a verse range', function (): void {
    $passage = (new LocalBibleProvider())->getPassage('kjv', '1 Corinthians', 13, startVerse: 4, endVerse: 5);

    expect($passage)->not->toBeNull();
    expect($passage->verses)->toHaveCount(2);
    expect($passage->verses[0]->verse)->toBe(4);
    expect($passage->verses[1]->verse)->toBe(5);
});

it('returns null for an unknown book, chapter, or translation', function (): void {
    $provider = new LocalBibleProvider();

    expect($provider->getPassage('kjv', 'Revelation', 22))->toBeNull();
    expect($provider->getPassage('kjv', 'Genesis', 99))->toBeNull();
    expect($provider->getPassage('esv', 'Genesis', 1))->toBeNull();
});
