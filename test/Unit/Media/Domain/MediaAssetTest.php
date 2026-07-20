<?php

declare(strict_types=1);

use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\ValueObject\MediaKind;

it('derives its kind from the mime type at construction', function (): void {
    $asset = new MediaAsset('photo.jpg', 'key-1', 'image/jpeg', 12345, 800, 600);

    expect($asset->kind())->toBe(MediaKind::Image);
    expect($asset->width())->toBe(800);
    expect($asset->height())->toBe(600);
});

it('leaves width and height null for non-image assets', function (): void {
    $asset = new MediaAsset('sermon.mp4', 'key-2', 'video/mp4', 999999);

    expect($asset->kind())->toBe(MediaKind::Video);
    expect($asset->width())->toBeNull();
    expect($asset->height())->toBeNull();
});

it('assigns a UUID and records the upload time', function (): void {
    $now = new DateTimeImmutable('2026-07-21T09:00:00+00:00');
    $asset = new MediaAsset('doc.pdf', 'key-3', 'application/pdf', 100, now: $now);

    expect($asset->id()->toString())->toMatch('/^[0-9a-f-]{36}$/');
    expect($asset->uploadedAt())->toBe($now);
});
