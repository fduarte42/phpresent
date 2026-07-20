<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\Command;

use Phpresent\Backup\Application\Service\BackupArchiverInterface;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Bundles every table Phpresent owns as *its own* local state — never
 * Song/SongSet, which are SongbookPro-sourced and re-synchronized rather
 * than backed up (§1: "every synced field is attributed back to
 * SongbookPro"); restoring a stale synced copy would be actively wrong.
 * `sync_state` is skipped too — after a restore, the next sync pass just
 * runs as a full resync, which is safe (§6), so preserving that cursor
 * isn't worth a bespoke "list all entity types" addition to
 * `SyncStateRepositoryInterface`, which has none today.
 *
 * Backup files contain password hashes (bcrypt, never plaintext) — the
 * same sensitivity as a raw database dump of the `users` table, not
 * more. Treat exported archives accordingly.
 */
final readonly class ExportBackupHandler
{
    private const string PERMISSION = 'backup.manage';
    private const int MAX_ROWS = 1_000_000;

    public function __construct(
        private DisplayRepositoryInterface $displayRepository,
        private ThemeRepositoryInterface $themeRepository,
        private MediaAssetRepositoryInterface $mediaAssetRepository,
        private MediaStorageInterface $mediaStorage,
        private BibleBookmarkRepositoryInterface $bookmarkRepository,
        private RoleRepositoryInterface $roleRepository,
        private UserRepositoryInterface $userRepository,
        private BackupArchiverInterface $archiver,
        private PermissionInterface $permission,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    public function __invoke(ExportBackupCommand $command): StreamInterface
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        $mediaAssets = $this->mediaAssetRepository->all(self::MAX_ROWS);

        $tables = [
            'displays' => array_map($this->displayRow(...), $this->displayRepository->all()),
            'themes' => array_map($this->themeRow(...), $this->themeRepository->all(self::MAX_ROWS)),
            'media_assets' => array_map($this->mediaAssetRow(...), $mediaAssets),
            'bible_bookmarks' => array_map($this->bookmarkRow(...), $this->bookmarkRepository->all(self::MAX_ROWS)),
            'roles' => array_map($this->roleRow(...), $this->roleRepository->all(self::MAX_ROWS)),
            'users' => array_map($this->userRow(...), $this->userRepository->all(self::MAX_ROWS)),
        ];

        $files = [];
        foreach ($mediaAssets as $asset) {
            $files["media/{$asset->storageKey()}"] = $this->mediaStorage->readStream($asset->storageKey());
        }

        $archive = $this->archiver->write($tables, $files);

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'backup.exported', [
            'displays' => count($tables['displays']),
            'themes' => count($tables['themes']),
            'mediaAssets' => count($tables['media_assets']),
            'bibleBookmarks' => count($tables['bible_bookmarks']),
            'roles' => count($tables['roles']),
            'users' => count($tables['users']),
        ]);

        return $archive;
    }

    /**
     * @return array<string, mixed>
     */
    private function displayRow(Display $display): array
    {
        return [
            'id' => $display->id()->toString(),
            'name' => $display->name(),
            'role' => $display->role()->value,
            'settings' => $display->settings()->toArray(),
            'createdAt' => $display->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function themeRow(Theme $theme): array
    {
        return [
            'id' => $theme->id()->toString(),
            'name' => $theme->name(),
            'scope' => $theme->scope()->value,
            'songExternalId' => $theme->songExternalId(),
            'sectionType' => $theme->sectionType(),
            'backgroundColor' => $theme->backgroundColor(),
            'backgroundMediaAssetId' => $theme->backgroundMediaAssetId(),
            'fontFamily' => $theme->fontFamily(),
            'fontColor' => $theme->fontColor(),
            'fontSizeScale' => $theme->fontSizeScale(),
            'textAlign' => $theme->textAlign()->value,
            'createdAt' => $theme->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaAssetRow(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id()->toString(),
            'filename' => $asset->filename(),
            'storageKey' => $asset->storageKey(),
            'mimeType' => $asset->mimeType(),
            'sizeBytes' => $asset->sizeBytes(),
            'width' => $asset->width(),
            'height' => $asset->height(),
            'uploadedAt' => $asset->uploadedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookmarkRow(BibleBookmark $bookmark): array
    {
        return [
            'id' => $bookmark->id()->toString(),
            'translationId' => $bookmark->translationId(),
            'book' => $bookmark->book(),
            'chapter' => $bookmark->chapter(),
            'startVerse' => $bookmark->startVerse(),
            'endVerse' => $bookmark->endVerse(),
            'label' => $bookmark->label(),
            'createdAt' => $bookmark->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roleRow(Role $role): array
    {
        return [
            'id' => $role->id()->toString(),
            'name' => $role->name(),
            'permissions' => $role->permissions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(User $user): array
    {
        return [
            'id' => $user->id()->toString(),
            'email' => $user->email(),
            'passwordHash' => $user->passwordHash(),
            'displayName' => $user->displayName(),
            'roleIds' => $user->roleIds(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->createdAt()->format(DATE_ATOM),
        ];
    }
}
