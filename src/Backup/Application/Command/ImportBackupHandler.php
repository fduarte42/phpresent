<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\Command;

use DateTimeImmutable;
use Exception;
use Laminas\Diactoros\Stream;
use Phpresent\Backup\Application\DTO\BackupImportResult;
use Phpresent\Backup\Application\Service\BackupArchiverInterface;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Bible\Domain\Repository\BibleBookmarkRepositoryInterface;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Exception\InvalidEmailException;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Identity\Domain\ValueObject\Email;
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\Repository\ThemeRepositoryInterface;
use Phpresent\Theme\Domain\ValueObject\TextAlign;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;
use Ramsey\Uuid\Uuid;
use ValueError;

/**
 * Restores a `ExportBackupHandler` archive. Every restored entity gets a
 * *fresh* id (none of these entities' constructors accept one — they
 * generate their own, §7.1/§19.1/§20/§21.1) rather than preserving the
 * exported id, which is why `roleIds` on each user row has to be remapped:
 * `roles.json` is processed first, building an old-id-to-new-`Role` map
 * from the same import pass, then each user's `roleIds` is translated
 * through it. This is intentionally the *only* cross-reference this
 * handler fixes up — `Theme::backgroundMediaAssetId` is left pointing at
 * whatever id was exported, unremapped, because that field was already
 * designed to tolerate a dangling/unresolvable reference (§19.1's
 * `SongSetItem`-style "the referenced thing doesn't have to exist"
 * reasoning) rather than being a new correctness gap introduced here.
 *
 * Idempotent for `Role`/`User` (matched by their unique `name`/`email` and
 * reused rather than duplicated on a second import of the same archive);
 * `Display`/`Theme`/`BibleBookmark`/`MediaAsset` are not — re-importing
 * the same archive creates duplicates of those, since none of them have a
 * natural unique key to dedupe on. This is meant for restoring into an
 * empty/fresh database (disaster recovery), not merging into a populated
 * one.
 */
final readonly class ImportBackupHandler
{
    private const string PERMISSION = 'backup.manage';

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

    public function __invoke(ImportBackupCommand $command): BackupImportResult
    {
        if (!$this->permission->can($command->actorUserId, self::PERMISSION)) {
            throw PermissionDeniedException::forPermission(self::PERMISSION);
        }

        $data = $this->archiver->read($command->archive);
        $tables = $data['tables'];
        $files = $data['files'];

        $displaysImported = $this->importDisplays($tables['displays'] ?? []);
        $themesImported = $this->importThemes($tables['themes'] ?? []);
        [$mediaImported, $mediaSkipped] = $this->importMediaAssets($tables['media_assets'] ?? [], $files);
        $bookmarksImported = $this->importBookmarks($tables['bible_bookmarks'] ?? []);
        [$roleIdMap, $rolesImported, $rolesReused] = $this->importRoles($tables['roles'] ?? []);
        [$usersImported, $usersSkipped] = $this->importUsers($tables['users'] ?? [], $roleIdMap);

        $result = new BackupImportResult(
            displaysImported: $displaysImported,
            themesImported: $themesImported,
            mediaAssetsImported: $mediaImported,
            mediaAssetsSkipped: $mediaSkipped,
            bibleBookmarksImported: $bookmarksImported,
            rolesImported: $rolesImported,
            rolesReused: $rolesReused,
            usersImported: $usersImported,
            usersSkipped: $usersSkipped,
        );

        /** @var string $actorUserId Permission check above already required a non-null actor. */
        $actorUserId = $command->actorUserId;
        $this->auditLogger->record($actorUserId, 'backup.imported', [
            'displaysImported' => $result->displaysImported,
            'themesImported' => $result->themesImported,
            'mediaAssetsImported' => $result->mediaAssetsImported,
            'bibleBookmarksImported' => $result->bibleBookmarksImported,
            'rolesImported' => $result->rolesImported,
            'usersImported' => $result->usersImported,
        ]);

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function importDisplays(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            try {
                $display = new Display(
                    name: $this->str($row['name'] ?? null),
                    role: DisplayRole::from($this->str($row['role'] ?? null)),
                    settings: DisplaySettings::fromArray(is_array($row['settings'] ?? null) ? $row['settings'] : []),
                    now: $this->parseDate($row['createdAt'] ?? null),
                );
            } catch (ValueError) {
                continue;
            }

            $this->displayRepository->save($display);
            $count++;
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function importThemes(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            try {
                $theme = new Theme(
                    name: $this->str($row['name'] ?? null),
                    scope: ThemeScope::from($this->str($row['scope'] ?? null)),
                    songExternalId: $this->nullableString($row['songExternalId'] ?? null),
                    sectionType: $this->nullableString($row['sectionType'] ?? null),
                    backgroundColor: $this->nullableString($row['backgroundColor'] ?? null),
                    backgroundMediaAssetId: $this->nullableString($row['backgroundMediaAssetId'] ?? null),
                    fontFamily: $this->nullableString($row['fontFamily'] ?? null),
                    fontColor: $this->nullableString($row['fontColor'] ?? null),
                    fontSizeScale: is_numeric($row['fontSizeScale'] ?? null) ? (float) $row['fontSizeScale'] : 1.0,
                    textAlign: TextAlign::from($this->str($row['textAlign'] ?? null, 'center')),
                    now: $this->parseDate($row['createdAt'] ?? null),
                );
            } catch (ValueError) {
                continue;
            }

            $this->themeRepository->save($theme);
            $count++;
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string> $files archive-relative path => raw bytes
     * @return array{0: int, 1: int} [imported, skipped]
     */
    private function importMediaAssets(array $rows, array $files): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $oldStorageKey = $this->str($row['storageKey'] ?? null);
            $bytes = $files["media/{$oldStorageKey}"] ?? null;

            if ($bytes === null) {
                $skipped++;

                continue;
            }

            $mimeType = $this->str($row['mimeType'] ?? null, 'application/octet-stream');
            $filename = $this->str($row['filename'] ?? null, 'file');
            $newStorageKey = Uuid::uuid4()->toString() . '-' . basename($filename);

            $stream = new Stream('php://temp', 'r+');
            $stream->write($bytes);
            $stream->rewind();
            $this->mediaStorage->write($newStorageKey, $mimeType, $stream);

            $asset = new MediaAsset(
                filename: $filename,
                storageKey: $newStorageKey,
                mimeType: $mimeType,
                sizeBytes: is_numeric($row['sizeBytes'] ?? null) ? (int) $row['sizeBytes'] : strlen($bytes),
                width: is_numeric($row['width'] ?? null) ? (int) $row['width'] : null,
                height: is_numeric($row['height'] ?? null) ? (int) $row['height'] : null,
                now: $this->parseDate($row['uploadedAt'] ?? null),
            );
            $this->mediaAssetRepository->save($asset);
            $imported++;
        }

        return [$imported, $skipped];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function importBookmarks(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $bookmark = new BibleBookmark(
                translationId: $this->str($row['translationId'] ?? null),
                book: $this->str($row['book'] ?? null),
                chapter: is_numeric($row['chapter'] ?? null) ? (int) $row['chapter'] : 1,
                startVerse: is_numeric($row['startVerse'] ?? null) ? (int) $row['startVerse'] : null,
                endVerse: is_numeric($row['endVerse'] ?? null) ? (int) $row['endVerse'] : null,
                label: $this->nullableString($row['label'] ?? null),
                now: $this->parseDate($row['createdAt'] ?? null),
            );
            $this->bookmarkRepository->save($bookmark);
            $count++;
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{0: array<string, Role>, 1: int, 2: int} [oldId => Role, imported, reused]
     */
    private function importRoles(array $rows): array
    {
        $map = [];
        $imported = 0;
        $reused = 0;

        foreach ($rows as $row) {
            $oldId = $this->str($row['id'] ?? null);
            $name = $this->str($row['name'] ?? null);
            $existing = $this->roleRepository->findByName($name);

            if ($existing !== null) {
                $map[$oldId] = $existing;
                $reused++;

                continue;
            }

            $permissions = is_array($row['permissions'] ?? null)
                ? array_map(fn (mixed $p): string => $this->str($p), $row['permissions'])
                : [];

            $role = new Role($name, $permissions);
            $this->roleRepository->save($role);
            $map[$oldId] = $role;
            $imported++;
        }

        return [$map, $imported, $reused];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, Role> $roleIdMap old id => Role
     * @return array{0: int, 1: int} [imported, skipped]
     */
    private function importUsers(array $rows, array $roleIdMap): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $emailValue = $this->str($row['email'] ?? null);

            if ($this->userRepository->findByEmail($emailValue) !== null) {
                $skipped++;

                continue;
            }

            try {
                $email = new Email($emailValue);
            } catch (InvalidEmailException) {
                $skipped++;

                continue;
            }

            $oldRoleIds = is_array($row['roleIds'] ?? null) ? $row['roleIds'] : [];
            $roleIds = [];

            foreach ($oldRoleIds as $oldRoleId) {
                $oldRoleId = $this->str($oldRoleId);

                if (isset($roleIdMap[$oldRoleId])) {
                    $roleIds[] = $roleIdMap[$oldRoleId]->id()->toString();
                }
            }

            $user = new User(
                email: $email,
                passwordHash: $this->str($row['passwordHash'] ?? null),
                displayName: $this->str($row['displayName'] ?? null, $emailValue),
                roleIds: $roleIds,
                now: $this->parseDate($row['createdAt'] ?? null),
            );

            if (($row['isActive'] ?? true) === false) {
                $user->deactivate($this->parseDate($row['createdAt'] ?? null) ?? new DateTimeImmutable());
            }

            $this->userRepository->save($user);
            $imported++;
        }

        return [$imported, $skipped];
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function str(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }
}
