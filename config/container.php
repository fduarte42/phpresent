<?php

declare(strict_types=1);

use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ServiceManager\ServiceManager;

$aggregator = new ConfigAggregator([
    Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    Mezzio\Router\ConfigProvider::class,
    Mezzio\Helper\ConfigProvider::class,
    Mezzio\ProblemDetails\ConfigProvider::class,
    Mezzio\ConfigProvider::class,
    new ConfigAggregator\ArrayProvider(
        require __DIR__ . '/autoload/dependencies.global.php'
    ),
    new ConfigAggregator\ArrayProvider(
        require __DIR__ . '/autoload/songbookpro.global.php'
    ),
], null, [
    file_exists(__DIR__ . '/autoload/local.php')
        ? __DIR__ . '/autoload/local.php'
        : __DIR__ . '/autoload/local.php.dist',
]);

$config = $aggregator->getMergedConfig();

$dependencies = $config['dependencies'] ?? [];
$dependencies['services']['config'] = $config;

return new ServiceManager($dependencies);
