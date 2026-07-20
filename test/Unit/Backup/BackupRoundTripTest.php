<?php

declare(strict_types=1);

use Laminas\Diactoros\Stream;
use Phpresent\Backup\Application\Command\ExportBackupCommand;
use Phpresent\Backup\Application\Command\ExportBackupHandler;
use Phpresent\Backup\Application\Command\ImportBackupCommand;
use Phpresent\Backup\Application\Command\ImportBackupHandler;
use Phpresent\Backup\Infrastructure\Zip\ZipBackupArchiver;
use Phpresent\Bible\Domain\Entity\BibleBookmark;
use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\ValueObject\Email;
use Phpresent\Media\Domain\Entity\MediaAsset;
use Phpresent\Presentation\Domain\Entity\Display;
use Phpresent\Presentation\Domain\ValueObject\DisplayRole;
use Phpresent\Presentation\Domain\ValueObject\DisplaySettings;
use Phpresent\Shared\Domain\Security\PermissionDeniedException;
use Phpresent\Theme\Domain\Entity\Theme;
use Phpresent\Theme\Domain\ValueObject\ThemeScope;
use PhpresentTest\Support\FakeAuditLogger;
use PhpresentTest\Support\FakeMediaStorage;
use PhpresentTest\Support\FakePermission;
use PhpresentTest\Support\InMemoryBibleBookmarkRepository;
use PhpresentTest\Support\InMemoryDisplayRepository;
use PhpresentTest\Support\InMemoryMediaAssetRepository;
use PhpresentTest\Support\InMemoryRoleRepository;
use PhpresentTest\Support\InMemoryThemeRepository;
use PhpresentTest\Support\InMemoryUserRepository;

function backupTestStream(string $contents): Stream
{
    $stream = new Stream('php://temp', 'r+');
    $stream->write($contents);
    $stream->rewind();

    return $stream;
}

/**
 * @return array{0: ExportBackupHandler, 1: ImportBackupHandler, 2: array{
 *     displays: InMemoryDisplayRepository,
 *     themes: InMemoryThemeRepository,
 *     mediaAssets: InMemoryMediaAssetRepository,
 *     mediaStorage: FakeMediaStorage,
 *     bookmarks: InMemoryBibleBookmarkRepository,
 *     roles: InMemoryRoleRepository,
 *     users: InMemoryUserRepository,
 * }}
 */
function makeBackupHandlers(): array
{
    $displays = new InMemoryDisplayRepository();
    $themes = new InMemoryThemeRepository();
    $mediaAssets = new InMemoryMediaAssetRepository();
    $mediaStorage = new FakeMediaStorage();
    $bookmarks = new InMemoryBibleBookmarkRepository();
    $roles = new InMemoryRoleRepository();
    $users = new InMemoryUserRepository();
    $archiver = new ZipBackupArchiver();
    $permission = new FakePermission();
    $auditLogger = new FakeAuditLogger();

    $exportHandler = new ExportBackupHandler(
        $displays,
        $themes,
        $mediaAssets,
        $mediaStorage,
        $bookmarks,
        $roles,
        $users,
        $archiver,
        $permission,
        $auditLogger,
    );
    $importHandler = new ImportBackupHandler(
        $displays,
        $themes,
        $mediaAssets,
        $mediaStorage,
        $bookmarks,
        $roles,
        $users,
        $archiver,
        $permission,
        $auditLogger,
    );

    return [$exportHandler, $importHandler, [
        'displays' => $displays,
        'themes' => $themes,
        'mediaAssets' => $mediaAssets,
        'mediaStorage' => $mediaStorage,
        'bookmarks' => $bookmarks,
        'roles' => $roles,
        'users' => $users,
    ]];
}

it('exports and reimports every table, remapping user role ids through newly created roles', function (): void {
    [$exportHandler, , $repos] = makeBackupHandlers();

    $repos['displays']->save(new Display('Main Screen', DisplayRole::Main, new DisplaySettings(theme: 'dark')));
    $repos['themes']->save(new Theme('Default', ThemeScope::Global, backgroundColor: '#101020'));
    $repos['bookmarks']->save(new BibleBookmark('kjv', 'Psalm', 23, 1, 6, 'Funeral'));

    $role = new Role('admin', ['users.view', 'users.manage']);
    $repos['roles']->save($role);

    $user = new User(new Email('admin@example.com'), 'hashed:secret', 'Admin', [$role->id()->toString()]);
    $repos['users']->save($user);

    $asset = new MediaAsset('photo.jpg', 'orig-key.jpg', 'image/jpeg', 5, 400, 300);
    $repos['mediaAssets']->save($asset);
    // Prime the fake storage with matching content for the export's readStream() call.
    $repos['mediaStorage']->write('orig-key.jpg', 'image/jpeg', backupTestStream('fake-jpeg-bytes'));

    $archive = $exportHandler(new ExportBackupCommand('actor-1'));
    $zipBytes = $archive->getContents();

    expect($zipBytes)->not->toBe('');

    // Import into an entirely fresh set of repositories.
    [, $importHandler, $freshRepos] = makeBackupHandlers();

    $result = $importHandler(new ImportBackupCommand('actor-1', backupTestStream($zipBytes)));

    expect($result->displaysImported)->toBe(1);
    expect($result->themesImported)->toBe(1);
    expect($result->bibleBookmarksImported)->toBe(1);
    expect($result->rolesImported)->toBe(1);
    expect($result->usersImported)->toBe(1);

    $importedUsers = $freshRepos['users']->all();
    expect($importedUsers)->toHaveCount(1);
    $importedRoles = $freshRepos['roles']->all();
    expect($importedRoles)->toHaveCount(1);

    // The critical assertion: the imported user's roleId points at the
    // NEWLY created role's id, not the stale exported one.
    expect($importedUsers[0]->roleIds())->toBe([$importedRoles[0]->id()->toString()]);
    expect($importedUsers[0]->roleIds()[0])->not->toBe($role->id()->toString());
});

it('reuses an existing role/user by name/email on a second import rather than duplicating', function (): void {
    [$exportHandler, $importHandler, $repos] = makeBackupHandlers();

    $role = new Role('admin', ['users.manage']);
    $repos['roles']->save($role);
    $repos['users']->save(new User(new Email('admin@example.com'), 'hashed:secret', 'Admin', [$role->id()->toString()]));

    $archive = $exportHandler(new ExportBackupCommand('actor-1'));
    $zipBytes = $archive->getContents();

    // Import into the SAME repositories that already have this role/user.
    $result = $importHandler(new ImportBackupCommand('actor-1', backupTestStream($zipBytes)));

    expect($result->rolesReused)->toBe(1);
    expect($result->rolesImported)->toBe(0);
    expect($result->usersSkipped)->toBe(1);
    expect($result->usersImported)->toBe(0);
    expect($repos['roles']->all())->toHaveCount(1);
    expect($repos['users']->all())->toHaveCount(1);
});

it('throws PermissionDeniedException for an unauthorized actor', function (): void {
    $displays = new InMemoryDisplayRepository();
    $themes = new InMemoryThemeRepository();
    $mediaAssets = new InMemoryMediaAssetRepository();
    $mediaStorage = new FakeMediaStorage();
    $bookmarks = new InMemoryBibleBookmarkRepository();
    $roles = new InMemoryRoleRepository();
    $users = new InMemoryUserRepository();
    $archiver = new ZipBackupArchiver();
    $deny = new FakePermission(allow: false);
    $auditLogger = new FakeAuditLogger();

    $exportHandler = new ExportBackupHandler(
        $displays,
        $themes,
        $mediaAssets,
        $mediaStorage,
        $bookmarks,
        $roles,
        $users,
        $archiver,
        $deny,
        $auditLogger,
    );

    expect(fn () => $exportHandler(new ExportBackupCommand('actor-1')))
        ->toThrow(PermissionDeniedException::class);
});
