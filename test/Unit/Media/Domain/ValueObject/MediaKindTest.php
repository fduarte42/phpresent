<?php

declare(strict_types=1);

use Phpresent\Media\Domain\ValueObject\MediaKind;

it('derives kind from mime type prefix', function (string $mimeType, MediaKind $expected): void {
    expect(MediaKind::fromMimeType($mimeType))->toBe($expected);
})->with([
    ['image/png', MediaKind::Image],
    ['image/jpeg', MediaKind::Image],
    ['video/mp4', MediaKind::Video],
    ['audio/mpeg', MediaKind::Audio],
    ['application/pdf', MediaKind::Document],
    ['text/plain', MediaKind::Document],
]);
