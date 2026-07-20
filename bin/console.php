#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Minimal CLI entry point, sharing the same DI container as the HTTP app
 * (`config/container.php`) and `bin/websocket-server.php` — commands are
 * resolved through it rather than hand-wired, same as everything else in
 * this codebase.
 *
 * Run with: php bin/console.php <command> [options]
 */

chdir(__DIR__ . '/..');
require 'vendor/autoload.php';

use Phpresent\Identity\Presentation\Console\CreateAdminCommand;
use Symfony\Component\Console\Application;

/** @var Laminas\ServiceManager\ServiceManager $container */
$container = require 'config/container.php';

$application = new Application('phpresent', 'dev');
$application->add($container->get(CreateAdminCommand::class));
$application->run();
