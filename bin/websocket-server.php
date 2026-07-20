#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone WebSocket server for the Presentation module (SDD §7.5/§13).
 * A separate OS process from the Mezzio HTTP app — sharing the same
 * Doctrine EntityManager config (this script builds the same DI container
 * `config/container.php` builds for the HTTP app and `bin/console.php`
 * builds for CLI commands, §16.9), but not the Mezzio HTTP pipeline.
 *
 * Run with: php bin/websocket-server.php (or `composer serve:websocket`).
 */

chdir(__DIR__ . '/..');
require 'vendor/autoload.php';

use Doctrine\ORM\EntityManagerInterface;
use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler;
use Phpresent\Presentation\Infrastructure\Realtime\PresentationChannel;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

/** @var Laminas\ServiceManager\ServiceManager $container */
$container = require 'config/container.php';

/** @var array<string, mixed> $config */
$config = $container->get('config');
/** @var array{port?: int, host?: string, poll_interval_seconds?: float} $wsConfig */
$wsConfig = $config['websocket'] ?? [];
$port = (int) ($wsConfig['port'] ?? 8090);
$host = (string) ($wsConfig['host'] ?? '0.0.0.0');
$pollIntervalSeconds = (float) ($wsConfig['poll_interval_seconds'] ?? 0.25);

$channel = new PresentationChannel(
    $container->get(GetPresentationSessionHandler::class),
    $container->get(EntityManagerInterface::class),
);

$loop = Loop::get();
$loop->addPeriodicTimer($pollIntervalSeconds, $channel->poll(...));

// Not IoServer::factory() — its signature (component, port, address) takes
// no $loop argument and always creates its own internal loop via
// LoopFactory::create(), silently ignoring a loop passed positionally
// after $address. That would leave the periodic timer above registered on
// a loop that's never run, so the server would accept connections but
// never actually poll for changes — constructing SocketServer/IoServer
// directly is what's needed to share one loop between both.
$socket = new SocketServer("{$host}:{$port}", [], $loop);
$server = new IoServer(new HttpServer(new WsServer($channel)), $socket, $loop);

fwrite(STDOUT, sprintf("Phpresent WebSocket server listening on ws://%s:%d\n", $host, $port));
$server->run();
