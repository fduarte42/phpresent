<?php

declare(strict_types=1);

use Phpresent\Bible\Infrastructure\Plugin\LocalBibleProvider;
use Phpresent\Shared\Infrastructure\Plugin\PluginRegistry;

it('filters registered plugins down to Bible providers', function (): void {
    $bibleProvider = new LocalBibleProvider();
    $registry = new PluginRegistry([$bibleProvider]);

    expect($registry->bibleProviders())->toBe([$bibleProvider]);
});

it('returns an empty list when no Bible providers are registered', function (): void {
    $registry = new PluginRegistry([]);

    expect($registry->bibleProviders())->toBe([]);
});
