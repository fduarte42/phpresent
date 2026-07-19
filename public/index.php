<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\MiddlewareFactory;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

/** @var ServiceManager $container */
$container = require 'config/container.php';

$app = $container->get(Application::class);
$factory = $container->get(MiddlewareFactory::class);

(require 'config/pipeline.php')($app, $factory, $container);
(require 'config/routes.php')($app, $factory, $container);

$app->run();
