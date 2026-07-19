<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;

chdir(dirname(__DIR__));

// When running under PHP's built-in server (`composer serve`), let it serve
// real files (built Vite assets, etc.) directly instead of routing every
// request through the Mezzio pipeline. A real webserver in production is
// already configured to only forward non-file requests here.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($requestedFile !== __DIR__ && is_file($requestedFile)) {
        return false;
    }
}

require 'vendor/autoload.php';

/** @var ServiceManager $container */
$container = require 'config/container.php';

$app = $container->get(Application::class);
$factory = $container->get(MiddlewareFactory::class);

(require 'config/pipeline.php')($app, $factory, $container);
(require 'config/routes.php')($app, $factory, $container);

$app->run();
