<?php

declare(strict_types=1);

use Laminas\Diactoros\Stream;
use Phpresent\Backup\Infrastructure\Zip\ZipBackupArchiver;

function streamFrom(string $contents): Stream
{
    $stream = new Stream('php://temp', 'r+');
    $stream->write($contents);
    $stream->rewind();

    return $stream;
}

it('round-trips tables and files through a real zip archive', function (): void {
    $archiver = new ZipBackupArchiver();

    $tables = [
        'displays' => [
            ['id' => 'd1', 'name' => 'Main Screen', 'role' => 'main'],
        ],
        'themes' => [],
    ];
    $files = [
        'media/abc-photo.jpg' => streamFrom('fake-jpeg-bytes'),
    ];

    $archive = $archiver->write($tables, $files);
    $result = $archiver->read($archive);

    expect($result['tables']['displays'])->toBe($tables['displays']);
    expect($result['tables']['themes'])->toBe([]);
    expect($result['files']['media/abc-photo.jpg'])->toBe('fake-jpeg-bytes');
});

it('throws when reading a file that is not a Phpresent backup archive', function (): void {
    $archiver = new ZipBackupArchiver();

    expect(fn () => $archiver->read(streamFrom('not a zip file at all')))->toThrow(RuntimeException::class);
});
