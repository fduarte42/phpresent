<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create themes table (Theme module, eighth increment).';
    }

    public function up(Schema $schema): void
    {
        $themes = $schema->createTable('themes');
        $themes->addColumn('id', 'guid', ['length' => 36]);
        $themes->addColumn('name', 'string', ['length' => 191]);
        $themes->addColumn('scope', 'string', ['length' => 16]);
        $themes->addColumn('song_external_id', 'string', ['length' => 191, 'notnull' => false]);
        $themes->addColumn('section_type', 'string', ['length' => 24, 'notnull' => false]);
        $themes->addColumn('background_color', 'string', ['length' => 16, 'notnull' => false]);
        $themes->addColumn('background_media_asset_id', 'string', ['length' => 191, 'notnull' => false]);
        $themes->addColumn('font_family', 'string', ['length' => 191, 'notnull' => false]);
        $themes->addColumn('font_color', 'string', ['length' => 16, 'notnull' => false]);
        $themes->addColumn('font_size_scale', 'float', ['default' => 1.0]);
        $themes->addColumn('text_align', 'string', ['length' => 8]);
        $themes->addColumn('created_at', 'datetime_immutable');
        $themes->addColumn('updated_at', 'datetime_immutable');
        $themes->setPrimaryKey(['id']);
        $themes->addIndex(['scope'], 'idx_themes_scope');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('themes');
    }
}
