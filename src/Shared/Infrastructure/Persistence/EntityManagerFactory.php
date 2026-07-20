<?php

declare(strict_types=1);

namespace Phpresent\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Doctrine\UuidType;

final class EntityManagerFactory
{
    /**
     * Registers custom Doctrine DBAL types used across every module's
     * entities (currently just `uuid`, from ramsey/uuid-doctrine). Doctrine
     * has no autodiscovery for these — every place that builds its own
     * EntityManager (this factory, and each module's throwaway in-memory
     * SQLite EntityManager in integration tests, see §16.4) must call this
     * before mapping metadata, or `uuid`-typed columns fail with
     * `UnknownColumnType`. Guarded so it's safe to call more than once.
     */
    public static function registerCustomTypes(): void
    {
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }
    }

    public function __invoke(ContainerInterface $container): EntityManager
    {
        self::registerCustomTypes();

        $config = $container->get('config');
        /** @var array{database: array{url: string}} $config */
        $databaseUrl = $config['database']['url'];

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: [
                __DIR__,
                dirname(__DIR__, 2) . '/Song/Domain/Entity',
                dirname(__DIR__, 2) . '/Song/Infrastructure/Persistence',
                dirname(__DIR__, 2) . '/SongSet/Domain/Entity',
                dirname(__DIR__, 2) . '/Identity/Domain/Entity',
                dirname(__DIR__, 2) . '/Presentation/Domain/Entity',
                dirname(__DIR__, 2) . '/Media/Domain/Entity',
            ],
            isDevMode: (bool) ($config['debug'] ?? false),
        );

        $dsnParser = new DsnParser([
            'sqlite' => 'pdo_sqlite',
            'mysql' => 'pdo_mysql',
            'postgres' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
        ]);

        $connection = DriverManager::getConnection(
            $dsnParser->parse($databaseUrl),
            $ormConfig,
        );

        return new EntityManager($connection, $ormConfig);
    }
}
