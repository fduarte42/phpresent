<?php

declare(strict_types=1);

use Phpresent\SongbookPro\Infrastructure\GraphQL\DeltaFetcher;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLResponse;
use PhpresentTest\Support\FakeGraphQLClient;

it('yields every item across pages and relays each timestamp as the next since', function (): void {
    $client = new FakeGraphQLClient([
        new GraphQLResponse([
            'dataItems' => [
                'timestamp' => '1000',
                'hasMore' => true,
                'items' => [
                    ['id' => 'a', 'type' => 'SONG', 'deleted' => false, 'data' => json_encode(['title' => 'A'])],
                ],
            ],
        ]),
        new GraphQLResponse([
            'dataItems' => [
                'timestamp' => '2000',
                'hasMore' => false,
                'items' => [
                    ['id' => 'b', 'type' => 'SONG', 'deleted' => false, 'data' => json_encode(['title' => 'B'])],
                ],
            ],
        ]),
    ]);

    $fetcher = new DeltaFetcher($client, 'library-1');
    $items = iterator_to_array($fetcher->fetch('500'));

    expect($items)->toHaveCount(2);
    expect($items[0]->id)->toBe('a');
    expect($items[1]->id)->toBe('b');
    expect($fetcher->lastTimestamp())->toBe('2000');

    expect($client->recordedVariables[0])->toBe(['library' => 'library-1', 'since' => '500']);
    expect($client->recordedVariables[1])->toBe(['library' => 'library-1', 'since' => '1000']);
});

it('decodes tombstones with null data', function (): void {
    $client = new FakeGraphQLClient([
        new GraphQLResponse([
            'dataItems' => [
                'timestamp' => '1000',
                'hasMore' => false,
                'items' => [
                    ['id' => 'a', 'type' => 'SONG', 'deleted' => true, 'data' => null],
                ],
            ],
        ]),
    ]);

    $fetcher = new DeltaFetcher($client, 'library-1');
    $items = iterator_to_array($fetcher->fetch(null));

    expect($items[0]->deleted)->toBeTrue();
    expect($items[0]->data)->toBeNull();
});
