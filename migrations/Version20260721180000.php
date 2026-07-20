<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bible_bookmarks table (Bible module, ninth increment).';
    }

    public function up(Schema $schema): void
    {
        $bookmarks = $schema->createTable('bible_bookmarks');
        $bookmarks->addColumn('id', 'guid', ['length' => 36]);
        $bookmarks->addColumn('translation_id', 'string', ['length' => 64]);
        $bookmarks->addColumn('book', 'string', ['length' => 191]);
        $bookmarks->addColumn('chapter', 'integer');
        $bookmarks->addColumn('start_verse', 'integer', ['notnull' => false]);
        $bookmarks->addColumn('end_verse', 'integer', ['notnull' => false]);
        $bookmarks->addColumn('label', 'string', ['length' => 191, 'notnull' => false]);
        $bookmarks->addColumn('created_at', 'datetime_immutable');
        $bookmarks->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('bible_bookmarks');
    }
}
