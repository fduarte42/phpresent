<?php

declare(strict_types=1);

namespace Phpresent\Backup\Infrastructure\Zip;

use DateTimeImmutable;
use Laminas\Diactoros\Stream;
use Phpresent\Backup\Application\Service\BackupArchiverInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use ZipArchive;

final class ZipBackupArchiver implements BackupArchiverInterface
{
    private const string MANIFEST_ENTRY = 'manifest.json';
    private const int MANIFEST_VERSION = 1;

    public function write(array $tables, array $files): StreamInterface
    {
        $tmpPath = $this->tempFile();
        $zip = new ZipArchive();

        if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create archive at \"{$tmpPath}\".");
        }

        $zip->addFromString(self::MANIFEST_ENTRY, json_encode([
            'version' => self::MANIFEST_VERSION,
            'createdAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'tables' => array_keys($tables),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        foreach ($tables as $table => $rows) {
            $zip->addFromString("{$table}.json", json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        }

        // ZipArchive::addStream() doesn't exist in this PHP/libzip build
        // (verified by actually running this, not assumed from the docs —
        // see SDD's own convention on this, e.g. §16.7) despite being
        // documented as available since PHP 7.4. addFile() (a real
        // filesystem path) does exist, so each stream is spooled to its
        // own temp file first.
        $fileTempPaths = [];

        foreach ($files as $path => $stream) {
            $fileTmpPath = $this->tempFile();
            file_put_contents($fileTmpPath, $stream->getContents());
            $fileTempPaths[] = $fileTmpPath;
            $zip->addFile($fileTmpPath, $path);
        }

        $zip->close();

        foreach ($fileTempPaths as $fileTempPath) {
            unlink($fileTempPath);
        }

        return new Stream($tmpPath, 'r');
    }

    public function read(StreamInterface $archive): array
    {
        $tmpPath = $this->tempFile();
        file_put_contents($tmpPath, $archive->getContents());

        $zip = new ZipArchive();

        if ($zip->open($tmpPath) !== true) {
            unlink($tmpPath);

            throw new RuntimeException('Unable to open the uploaded file as a ZIP archive.');
        }

        $manifestJson = $zip->getFromName(self::MANIFEST_ENTRY);

        if ($manifestJson === false) {
            $zip->close();
            unlink($tmpPath);

            throw new RuntimeException('Not a Phpresent backup archive (missing manifest.json).');
        }

        /** @var array{tables?: list<string>} $manifest */
        $manifest = json_decode($manifestJson, true, flags: JSON_THROW_ON_ERROR);
        $tableNames = $manifest['tables'] ?? [];

        $tables = [];
        foreach ($tableNames as $table) {
            $json = $zip->getFromName("{$table}.json");
            /** @var list<array<string, mixed>> $rows */
            $rows = $json === false ? [] : json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $tables[$table] = $rows;
        }

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || $name === self::MANIFEST_ENTRY || !str_starts_with($name, 'media/')) {
                continue;
            }

            $contents = $zip->getFromName($name);

            if ($contents !== false) {
                $files[$name] = $contents;
            }
        }

        $zip->close();
        unlink($tmpPath);

        return ['tables' => $tables, 'files' => $files];
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'phpresent-backup-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the backup archive.');
        }

        return $path;
    }
}
