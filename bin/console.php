#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * The single CLI entry point for the app (SDD §16.9): a laminas-cli
 * application built the same way `vendor/bin/laminas --container=...`
 * would, but pointed at our container without needing the flag every
 * time. Every command it runs — `identity:create-admin`, every
 * `migrations:*` command — is resolved from the same DI container
 * `config/container.php` builds for the HTTP app, via the
 * `laminas-cli.commands` map in `config/autoload/cli.global.php`; there
 * is no separate bootstrap per command family.
 *
 * Run with: php bin/console.php <command> [options]
 * List all commands: php bin/console.php list
 */

chdir(__DIR__ . '/..');
require 'vendor/autoload.php';

use Laminas\Cli\ApplicationFactory;
use Laminas\Cli\ApplicationProvisioner;

/** @var Laminas\ServiceManager\ServiceManager $container */
$container = require 'config/container.php';

$application = (new ApplicationFactory())();
$application = (new ApplicationProvisioner())($application, $container);

exit($application->run());
