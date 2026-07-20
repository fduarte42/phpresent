<?php

declare(strict_types=1);

namespace Phpresent\Backup\Application\Service;

use Psr\Http\Message\StreamInterface;

/**
 * Generic "write JSON tables + binary files to an archive, read them back"
 * port — deliberately knows nothing about Displays/Themes/Media/etc.
 * (that domain knowledge belongs to `ExportBackupHandler`/
 * `ImportBackupHandler`), same separation `MediaStorageInterface` (§19)
 * keeps between "how to store bytes" and "what a `MediaAsset` is."
 */
interface BackupArchiverInterface
{
    /**
     * @param array<string, list<array<string, mixed>>> $tables table name => rows (each row must be JSON-serializable)
     * @param array<string, StreamInterface> $files archive-relative path => content
     */
    public function write(array $tables, array $files): StreamInterface;

    /**
     * @return array{tables: array<string, list<array<string, mixed>>>, files: array<string, string>} files: archive-relative path => raw bytes
     */
    public function read(StreamInterface $archive): array;
}
