<?php

declare(strict_types=1);

namespace Phpresent\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260719140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users, roles and audit_log tables (Identity module, third increment).';
    }

    public function up(Schema $schema): void
    {
        $users = $schema->createTable('users');
        $users->addColumn('id', 'guid', ['length' => 36]);
        $users->addColumn('email', 'string', ['length' => 320]);
        $users->addColumn('password_hash', 'string', ['length' => 255]);
        $users->addColumn('display_name', 'string', ['length' => 191]);
        $users->addColumn('role_ids', 'json');
        $users->addColumn('is_active', 'boolean', ['default' => true]);
        $users->addColumn('created_at', 'datetime_immutable');
        $users->addColumn('updated_at', 'datetime_immutable');
        $users->setPrimaryKey(['id']);
        $users->addUniqueIndex(['email'], 'uniq_users_email');
        $users->addIndex(['email'], 'idx_users_email');

        $roles = $schema->createTable('roles');
        $roles->addColumn('id', 'guid', ['length' => 36]);
        $roles->addColumn('name', 'string', ['length' => 64]);
        $roles->addColumn('permissions', 'json');
        $roles->setPrimaryKey(['id']);
        $roles->addUniqueIndex(['name'], 'uniq_roles_name');

        $auditLog = $schema->createTable('audit_log');
        $auditLog->addColumn('id', 'guid', ['length' => 36]);
        $auditLog->addColumn('actor_user_id', 'string', ['length' => 191]);
        $auditLog->addColumn('action', 'string', ['length' => 191]);
        $auditLog->addColumn('context', 'json');
        $auditLog->addColumn('recorded_at', 'datetime_immutable');
        $auditLog->setPrimaryKey(['id']);
        $auditLog->addIndex(['actor_user_id'], 'idx_audit_log_actor_user_id');
        $auditLog->addIndex(['recorded_at'], 'idx_audit_log_recorded_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('audit_log');
        $schema->dropTable('roles');
        $schema->dropTable('users');
    }
}
