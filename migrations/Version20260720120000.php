<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create displays and presentation_sessions tables (Presentation module, fourth increment).';
    }

    public function up(Schema $schema): void
    {
        $displays = $schema->createTable('displays');
        $displays->addColumn('id', 'guid', ['length' => 36]);
        $displays->addColumn('name', 'string', ['length' => 191]);
        $displays->addColumn('role', 'string', ['length' => 24]);
        $displays->addColumn('settings', 'json');
        $displays->addColumn('created_at', 'datetime_immutable');
        $displays->addColumn('updated_at', 'datetime_immutable');
        $displays->setPrimaryKey(['id']);

        $sessions = $schema->createTable('presentation_sessions');
        $sessions->addColumn('id', 'guid', ['length' => 36]);
        $sessions->addColumn('current_deck', 'json', ['notnull' => false]);
        $sessions->addColumn('current_slide_index', 'integer', ['default' => 0]);
        $sessions->addColumn('is_blanked', 'boolean', ['default' => false]);
        $sessions->addColumn('is_frozen', 'boolean', ['default' => false]);
        $sessions->addColumn('lyrics_hidden', 'boolean', ['default' => false]);
        $sessions->addColumn('font_size_adjust', 'integer', ['default' => 0]);
        $sessions->addColumn('emergency_message', 'text', ['notnull' => false]);
        $sessions->addColumn('updated_at', 'datetime_immutable');
        $sessions->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('presentation_sessions');
        $schema->dropTable('displays');
    }
}
