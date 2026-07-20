<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phpresent\Identity\Application\Service\AuthenticatorInterface;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Identity\Domain\Service\PasswordHasherInterface;
use Phpresent\Identity\Infrastructure\Persistence\DoctrineRoleRepository;
use Phpresent\Identity\Infrastructure\Persistence\DoctrineUserRepository;
use Phpresent\Identity\Infrastructure\Security\CompositeAuthenticator;
use Phpresent\Identity\Infrastructure\Security\JwtAuthenticator;
use Phpresent\Identity\Infrastructure\Security\PhpPasswordHasher;
use Phpresent\Identity\Infrastructure\Security\RolePermissionChecker;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Shared\Infrastructure\Persistence\DoctrineAuditLogger;
use Phpresent\Shared\Infrastructure\Persistence\DoctrineSyncStateRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;
use Phpresent\SongbookPro\Domain\Service\AccessTokenProviderInterface;
use Phpresent\SongbookPro\Infrastructure\Security\StaticAccessTokenProvider;
use Phpresent\Song\Application\Service\SongSourceInterface;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Phpresent\Song\Infrastructure\Persistence\DoctrineSongRepository;
use Phpresent\Song\Infrastructure\SongbookPro\SongSource;
use Phpresent\SongSet\Application\Service\SongSetSourceInterface;
use Phpresent\SongSet\Domain\Repository\SongSetRepositoryInterface;
use Phpresent\SongSet\Infrastructure\Persistence\DoctrineSongSetRepository;
use Phpresent\SongSet\Infrastructure\SongbookPro\SongSetSource;
use Phpresent\Song\Presentation\Http\Handler\GetSongHandler as GetSongHttpHandler;
use Phpresent\Song\Presentation\Http\Handler\ListSongsHandler;
use Phpresent\Song\Presentation\Http\Handler\SyncSongsHandler as SyncSongsHttpHandler;
use Phpresent\SongbookPro\Infrastructure\GraphQL\GraphQLClientInterface;
use Phpresent\SongbookPro\Infrastructure\GraphQL\RateLimiter;
use Phpresent\SongbookPro\Infrastructure\GraphQL\SongbookProGraphQLClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface as SimpleCacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

return [
    'dependencies' => [
        'aliases' => [
            EntityManagerInterface::class => EntityManager::class,
            GuzzleClientInterface::class => GuzzleClient::class,
            SongRepositoryInterface::class => DoctrineSongRepository::class,
            SyncStateRepositoryInterface::class => DoctrineSyncStateRepository::class,
            SongSourceInterface::class => SongSource::class,
            SongSetRepositoryInterface::class => DoctrineSongSetRepository::class,
            SongSetSourceInterface::class => SongSetSource::class,
            AccessTokenProviderInterface::class => StaticAccessTokenProvider::class,
            UserRepositoryInterface::class => DoctrineUserRepository::class,
            RoleRepositoryInterface::class => DoctrineRoleRepository::class,
            PasswordHasherInterface::class => PhpPasswordHasher::class,
            PermissionInterface::class => RolePermissionChecker::class,
            AuditLoggerInterface::class => DoctrineAuditLogger::class,
            AuthenticatorInterface::class => CompositeAuthenticator::class,
        ],
        'factories' => [
            EntityManager::class => EntityManagerFactory::class,

            GuzzleClient::class => static fn (): GuzzleClient => new GuzzleClient(),

            SimpleCacheInterface::class => static function (ContainerInterface $container): SimpleCacheInterface {
                $config = $container->get('config');
                $dsn = (string) ($config['cache']['dsn'] ?? 'array://');

                $adapter = str_starts_with($dsn, 'redis://')
                    ? RedisAdapter::createConnection($dsn)
                    : null;

                return new Psr16Cache($adapter !== null ? new RedisAdapter($adapter) : new ArrayAdapter());
            },

            LoggerInterface::class => static function (ContainerInterface $container): LoggerInterface {
                $config = $container->get('config');
                $logger = new Logger('phpresent');
                $logger->pushHandler(new StreamHandler(
                    ($config['debug'] ?? false) ? 'php://stderr' : dirname(__DIR__, 2) . '/var/log/app.log',
                    Logger::DEBUG,
                ));

                return $logger;
            },

            RateLimiter::class => static function (ContainerInterface $container): RateLimiter {
                $config = $container->get('config');
                $rate = (float) ($config['songbookpro']['rate_limit_per_second'] ?? 5);

                return new RateLimiter($rate);
            },

            StaticAccessTokenProvider::class => static function (ContainerInterface $container): StaticAccessTokenProvider {
                $config = $container->get('config');

                return new StaticAccessTokenProvider((string) $config['songbookpro']['api_token']);
            },

            GraphQLClientInterface::class => static function (ContainerInterface $container): GraphQLClientInterface {
                $config = $container->get('config');
                $sbpConfig = $config['songbookpro'];

                return new SongbookProGraphQLClient(
                    httpClient: $container->get(GuzzleClientInterface::class),
                    rateLimiter: $container->get(RateLimiter::class),
                    tokenProvider: $container->get(AccessTokenProviderInterface::class),
                    logger: $container->get(LoggerInterface::class),
                    apiUrl: (string) $sbpConfig['api_url'],
                    maxRetries: (int) $sbpConfig['graphql']['max_retries'],
                    retryBaseDelayMs: (int) $sbpConfig['graphql']['retry_base_delay_ms'],
                );
            },

            SongSource::class => static function (ContainerInterface $container): SongSource {
                $config = $container->get('config');

                return new SongSource(
                    client: $container->get(GraphQLClientInterface::class),
                    mapper: $container->get(\Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper::class),
                    logger: $container->get(LoggerInterface::class),
                    library: (string) $config['songbookpro']['group_id'],
                );
            },

            SongSetSource::class => static function (ContainerInterface $container): SongSetSource {
                $config = $container->get('config');

                return new SongSetSource(
                    client: $container->get(GraphQLClientInterface::class),
                    mapper: $container->get(\Phpresent\SongSet\Infrastructure\Mapper\SongSetGraphQLMapper::class),
                    pageSize: (int) $config['songbookpro']['graphql']['page_size'],
                );
            },

            JwtAuthenticator::class => static function (ContainerInterface $container): JwtAuthenticator {
                $config = $container->get('config');

                return new JwtAuthenticator(
                    secret: (string) $config['identity']['jwt_secret'],
                    algorithm: (string) ($config['identity']['jwt_algorithm'] ?? 'HS256'),
                );
            },

            InertiaResponseFactory::class => static function (ContainerInterface $container): InertiaResponseFactory {
                $manifestPath = dirname(__DIR__, 2) . '/public/build/.vite/manifest.json';

                return new InertiaResponseFactory(
                    assetVersion: is_file($manifestPath) ? hash_file('xxh128', $manifestPath) : 'dev',
                    viteManifestPath: $manifestPath,
                );
            },

            ListSongsHandler::class => ReflectionBasedAbstractFactory::class,
            GetSongHttpHandler::class => ReflectionBasedAbstractFactory::class,
            SyncSongsHttpHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Song\Presentation\Http\Handler\SongsIndexPageHandler::class => ReflectionBasedAbstractFactory::class,

            \Phpresent\SongSet\Presentation\Http\Handler\ListSongSetsHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\SongSet\Presentation\Http\Handler\GetSongSetHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\SongSet\Presentation\Http\Handler\SyncSongSetsHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\SongSet\Presentation\Http\Handler\ReorderSongSetItemsHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\SongSet\Presentation\Http\Handler\SongSetsIndexPageHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\SongSet\Presentation\Http\Handler\SongSetShowPageHandler::class => ReflectionBasedAbstractFactory::class,

            \Phpresent\Identity\Presentation\Http\Middleware\AuthenticationMiddleware::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\LoginHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\LogoutHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\ListUsersHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\GetUserHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\CreateUserHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\AssignRoleHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\DeactivateUserHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\ListRolesHandler::class => ReflectionBasedAbstractFactory::class,
            \Phpresent\Identity\Presentation\Http\Handler\CreateRoleHandler::class => ReflectionBasedAbstractFactory::class,
        ],
        'abstract_factories' => [
            ReflectionBasedAbstractFactory::class,
        ],
    ],
];
