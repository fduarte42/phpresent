<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\ServiceManager;

$aggregator = new ConfigAggregator([
    Laminas\Diactoros\ConfigProvider::class,
    Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    Mezzio\Router\ConfigProvider::class,
    Mezzio\Helper\ConfigProvider::class,
    Mezzio\ProblemDetails\ConfigProvider::class,
    Mezzio\Session\ConfigProvider::class,
    Mezzio\Session\Ext\ConfigProvider::class,
    Mezzio\ConfigProvider::class,
    new ArrayProvider(
        require __DIR__ . '/autoload/dependencies.global.php'
    ),
    new ArrayProvider(
        require __DIR__ . '/autoload/songbookpro.global.php'
    ),
    new ArrayProvider(
        require __DIR__ . '/autoload/websocket.global.php'
    ),
    new ArrayProvider(
        require __DIR__ . '/plugins.php'
    ),
    new ArrayProvider(
        require (file_exists(__DIR__ . '/autoload/local.php')
            ? __DIR__ . '/autoload/local.php'
            : __DIR__ . '/autoload/local.php.dist')
    ),
]);

$config = $aggregator->getMergedConfig();

$dependencies = $config['dependencies'] ?? [];
$dependencies['services']['config'] = $config;

return new ServiceManager($dependencies);
