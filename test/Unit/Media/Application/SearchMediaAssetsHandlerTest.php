<?php

declare(strict_types=1);

use Phpresent\Media\Application\Query\SearchMediaAssetsHandler;
use Phpresent\Media\Application\Query\SearchMediaAssetsQuery;
use Phpresent\Media\Domain\Entity\MediaAsset;
use PhpresentTest\Support\InMemoryMediaAssetRepository;

it('returns everything when the query is empty', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $repository->save(new MediaAsset('a.jpg', 'k1', 'image/jpeg', 1));
    $repository->save(new MediaAsset('b.mp4', 'k2', 'video/mp4', 1));

    $results = (new SearchMediaAssetsHandler($repository))(new SearchMediaAssetsQuery());

    expect($results)->toHaveCount(2);
});

it('filters by filename case-insensitively when a query is given', function (): void {
    $repository = new InMemoryMediaAssetRepository();
    $repository->save(new MediaAsset('Sunday-Slide.jpg', 'k1', 'image/jpeg', 1));
    $repository->save(new MediaAsset('sermon.mp4', 'k2', 'video/mp4', 1));

    $results = (new SearchMediaAssetsHandler($repository))(new SearchMediaAssetsQuery(query: 'sunday'));

    expect($results)->toHaveCount(1);
    expect($results[0]->filename)->toBe('Sunday-Slide.jpg');
});
