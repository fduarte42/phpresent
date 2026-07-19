<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create songs, song_sections and sync_state tables (Song module, first increment).';
    }

    public function up(Schema $schema): void
    {
        $songs = $schema->createTable('songs');
        $songs->addColumn('id', 'guid', ['length' => 36]);
        $songs->addColumn('external_id', 'string', ['length' => 191]);
        $songs->addColumn('title', 'string', ['length' => 512]);
        $songs->addColumn('authors', 'json');
        $songs->addColumn('copyright', 'string', ['length' => 512, 'notnull' => false]);
        $songs->addColumn('ccli', 'string', ['length' => 32, 'notnull' => false]);
        $songs->addColumn('default_key', 'string', ['length' => 8, 'notnull' => false]);
        $songs->addColumn('tempo', 'integer', ['notnull' => false]);
        $songs->addColumn('capo', 'integer', ['notnull' => false]);
        $songs->addColumn('tags', 'json');
        $songs->addColumn('format', 'string', ['length' => 16]);
        $songs->addColumn('metadata', 'json');
        $songs->addColumn('source_revision', 'string', ['length' => 191]);
        $songs->addColumn('source_checksum', 'string', ['length' => 191]);
        $songs->addColumn('synced_at', 'datetime_immutable');
        $songs->addColumn('created_at', 'datetime_immutable');
        $songs->addColumn('updated_at', 'datetime_immutable');
        $songs->setPrimaryKey(['id']);
        $songs->addUniqueIndex(['external_id'], 'uniq_songs_external_id');
        $songs->addIndex(['title'], 'idx_songs_title');

        $sections = $schema->createTable('song_sections');
        $sections->addColumn('id', 'guid', ['length' => 36]);
        $sections->addColumn('song_id', 'guid', ['length' => 36]);
        $sections->addColumn('position', 'integer');
        $sections->addColumn('type', 'string', ['length' => 24]);
        $sections->addColumn('label', 'string', ['length' => 191, 'notnull' => false]);
        $sections->addColumn('content', 'text');
        $sections->addColumn('chordpro_source', 'text', ['notnull' => false]);
        $sections->setPrimaryKey(['id']);
        $sections->addUniqueIndex(['song_id', 'position'], 'uniq_song_position');
        $sections->addIndex(['song_id'], 'idx_song_sections_song_id');
        $sections->addForeignKeyConstraint($songs, ['song_id'], ['id'], ['onDelete' => 'CASCADE']);

        $syncState = $schema->createTable('sync_state');
        $syncState->addColumn('entity_type', 'string', ['length' => 64]);
        $syncState->addColumn('last_synced_at', 'datetime_immutable');
        $syncState->setPrimaryKey(['entity_type']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('song_sections');
        $schema->dropTable('songs');
        $schema->dropTable('sync_state');
    }
}
