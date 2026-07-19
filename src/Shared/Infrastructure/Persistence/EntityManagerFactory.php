<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;

final class EntityManagerFactory
{
    public function __invoke(ContainerInterface $container): EntityManager
    {
        $config = $container->get('config');
        /** @var array{database: array{url: string}} $config */
        $databaseUrl = $config['database']['url'];

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                dirname(__DIR__, 2) . '/Song/Domain/Entity',
                dirname(__DIR__, 2) . '/Song/Infrastructure/Persistence',
            ],
            isDevMode: (bool) ($config['debug'] ?? false),
        );

        $connection = DriverManager::getConnection(
            ['url' => $databaseUrl],
            $ormConfig,
        );

        return new EntityManager($connection, $ormConfig);
    }
}
