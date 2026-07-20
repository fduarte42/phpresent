<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create media_assets table (Media module, seventh increment).';
    }

    public function up(Schema $schema): void
    {
        $mediaAssets = $schema->createTable('media_assets');
        $mediaAssets->addColumn('id', 'guid', ['length' => 36]);
        $mediaAssets->addColumn('filename', 'string', ['length' => 255]);
        $mediaAssets->addColumn('storage_key', 'string', ['length' => 512]);
        $mediaAssets->addColumn('mime_type', 'string', ['length' => 191]);
        $mediaAssets->addColumn('size_bytes', 'integer');
        $mediaAssets->addColumn('kind', 'string', ['length' => 16]);
        $mediaAssets->addColumn('width', 'integer', ['notnull' => false]);
        $mediaAssets->addColumn('height', 'integer', ['notnull' => false]);
        $mediaAssets->addColumn('uploaded_at', 'datetime_immutable');
        $mediaAssets->setPrimaryKey(['id']);
        $mediaAssets->addUniqueIndex(['storage_key'], 'uniq_media_assets_storage_key');
        $mediaAssets->addIndex(['filename'], 'idx_media_assets_filename');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('media_assets');
    }
}
