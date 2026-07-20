<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\DTO;

final readonly class BackupImportResult
{
    public function __construct(
        public int $displaysImported,
        public int $themesImported,
        public int $mediaAssetsImported,
        public int $mediaAssetsSkipped,
        public int $bibleBookmarksImported,
        public int $rolesImported,
        public int $rolesReused,
        public int $usersImported,
        public int $usersSkipped,
    ) {
    }
}
