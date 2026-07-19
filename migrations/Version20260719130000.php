<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create song_sets and song_set_items tables (SongSet module, second increment).';
    }

    public function up(Schema $schema): void
    {
        $songSets = $schema->createTable('song_sets');
        $songSets->addColumn('id', 'guid', ['length' => 36]);
        $songSets->addColumn('external_id', 'string', ['length' => 191]);
        $songSets->addColumn('name', 'string', ['length' => 512]);
        $songSets->addColumn('service_date', 'datetime_immutable', ['notnull' => false]);
        $songSets->addColumn('notes', 'text', ['notnull' => false]);
        $songSets->addColumn('source_revision', 'string', ['length' => 191]);
        $songSets->addColumn('source_checksum', 'string', ['length' => 191]);
        $songSets->addColumn('synced_at', 'datetime_immutable');
        $songSets->addColumn('created_at', 'datetime_immutable');
        $songSets->addColumn('updated_at', 'datetime_immutable');
        $songSets->setPrimaryKey(['id']);
        $songSets->addUniqueIndex(['external_id'], 'uniq_song_sets_external_id');
        $songSets->addIndex(['name'], 'idx_song_sets_name');

        $items = $schema->createTable('song_set_items');
        $items->addColumn('id', 'guid', ['length' => 36]);
        $items->addColumn('song_set_id', 'guid', ['length' => 36]);
        $items->addColumn('song_external_id', 'string', ['length' => 191]);
        $items->addColumn('source_position', 'integer');
        $items->addColumn('local_position', 'integer', ['notnull' => false]);
        $items->addColumn('transposed_key', 'string', ['length' => 8, 'notnull' => false]);
        $items->addColumn('notes', 'text', ['notnull' => false]);
        $items->setPrimaryKey(['id']);
        $items->addUniqueIndex(['song_set_id', 'source_position'], 'uniq_song_set_source_position');
        $items->addIndex(['song_set_id'], 'idx_song_set_items_song_set_id');
        $items->addForeignKeyConstraint($songSets->getName(), ['song_set_id'], ['id'], ['onDelete' => 'CASCADE']);

        // sync_state already exists (Song module migration) — reused for
        // entity_type = 'song_set' rows at runtime, not recreated here.
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('song_set_items');
        $schema->dropTable('song_sets');
    }
}
