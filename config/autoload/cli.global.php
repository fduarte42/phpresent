<?php

declare(strict_types=1);

use Doctrine\Migrations\Tools\Console\Command\CurrentCommand;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\DumpSchemaCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\ListCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\RollupCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\SyncMetadataCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use Phpresent\Identity\Presentation\Console\CreateAdminCommand;

// `bin/console.php` (SDD §16.9) is a laminas-cli application: every
// registered command is resolved from the app's own DI container
// (config/container.php), same as every HTTP handler — no separate
// bootstrap file per command family. Doctrine Migrations previously had
// its own bin (`vendor/bin/doctrine-migrations`) driven by `cli-config.php`
// building a second, parallel container; that file is gone; migrations
// commands are now just more entries in this list, resolved from the same
// container as everything else.
return [
    'laminas-cli' => [
        'commands' => [
            'identity:create-admin' => CreateAdminCommand::class,

            'migrations:current' => CurrentCommand::class,
            'migrations:diff' => DiffCommand::class,
            'migrations:dump-schema' => DumpSchemaCommand::class,
            'migrations:execute' => ExecuteCommand::class,
            'migrations:generate' => GenerateCommand::class,
            'migrations:latest' => LatestCommand::class,
            'migrations:list' => ListCommand::class,
            'migrations:migrate' => MigrateCommand::class,
            'migrations:rollup' => RollupCommand::class,
            'migrations:status' => StatusCommand::class,
            'migrations:sync-metadata-storage' => SyncMetadataCommand::class,
            'migrations:up-to-date' => UpToDateCommand::class,
            'migrations:version' => VersionCommand::class,
        ],
    ],
];
