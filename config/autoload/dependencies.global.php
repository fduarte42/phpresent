<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
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
use Phpresent\Media\Application\Service\MediaStorageInterface;
use Phpresent\Media\Domain\Repository\MediaAssetRepositoryInterface;
use Phpresent\Media\Infrastructure\Flysystem\FlysystemMediaStorage;
use Phpresent\Media\Infrastructure\Persistence\DoctrineMediaAssetRepository;
use Phpresent\Media\Presentation\Http\Handler\DeleteMediaAssetHandler;
use Phpresent\Media\Presentation\Http\Handler\DownloadMediaAssetHandler;
use Phpresent\Media\Presentation\Http\Handler\GetMediaAssetHandler as GetMediaAssetHttpHandler;
use Phpresent\Media\Presentation\Http\Handler\ListMediaAssetsHandler;
use Phpresent\Media\Presentation\Http\Handler\MediaIndexPageHandler;
use Phpresent\Media\Presentation\Http\Handler\UploadMediaAssetHandler as UploadMediaAssetHttpHandler;
use Phpresent\Presentation\Domain\Repository\DisplayRepositoryInterface;
use Phpresent\Presentation\Domain\Repository\PresentationSessionRepositoryInterface;
use Phpresent\Presentation\Infrastructure\Persistence\DoctrineDisplayRepository;
use Phpresent\Presentation\Infrastructure\Persistence\DoctrinePresentationSessionRepository;
use Phpresent\Presentation\Presentation\Http\Handler\CreateDisplayHandler as CreateDisplayHttpHandler;
use Phpresent\Presentation\Presentation\Http\Handler\DeleteDisplayHandler;
use Phpresent\Presentation\Presentation\Http\Handler\DisplaysIndexPageHandler;
use Phpresent\Presentation\Presentation\Http\Handler\GetDisplayHandler as GetDisplayHttpHandler;
use Phpresent\Presentation\Application\Query\GetPresentationSessionHandler as GetPresentationSessionQueryHandler;
use Phpresent\Presentation\Presentation\Http\Handler\GetPresentationSessionHandler as GetPresentationSessionHttpHandler;
use Phpresent\Presentation\Presentation\Http\Handler\ListDisplaysHandler as ListDisplaysHttpHandler;
use Phpresent\Presentation\Presentation\Http\Handler\LoadSongIntoPresentationHandler as LoadSongIntoPresentationHttpHandler;
use Phpresent\Presentation\Presentation\Http\Handler\PresentationControlHandler;
use Phpresent\Presentation\Presentation\Http\Handler\PresentationControlPageHandler;
use Phpresent\Presentation\Presentation\Http\Handler\PresentationSseHandler;
use Phpresent\Presentation\Presentation\Http\Handler\UpdateDisplayHandler as UpdateDisplayHttpHandler;
use Phpresent\Shared\Domain\Audit\AuditLoggerInterface;
use Phpresent\Shared\Domain\Repository\SyncStateRepositoryInterface;
use Phpresent\Shared\Domain\Security\PermissionInterface;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Shared\Infrastructure\Persistence\DoctrineAuditLogger;
use Phpresent\Shared\Infrastructure\Persistence\DoctrineSyncStateRepository;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;
use Phpresent\SongbookPro\Domain\Service\AccessTokenProviderInterface;
use Phpresent\SongbookPro\Infrastructure\Security\StaticAccessTokenProvider;
use Phpresent\Song\Application\Query\SearchSongsHandler;
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
            DisplayRepositoryInterface::class => DoctrineDisplayRepository::class,
            PresentationSessionRepositoryInterface::class => DoctrinePresentationSessionRepository::class,
            MediaAssetRepositoryInterface::class => DoctrineMediaAssetRepository::class,
            MediaStorageInterface::class => FlysystemMediaStorage::class,
            FilesystemOperator::class => Filesystem::class,
        ],
        'factories' => [
            EntityManager::class => EntityManagerFactory::class,

            GuzzleClient::class => static fn (): GuzzleClient => new GuzzleClient(),

            Filesystem::class => static function (ContainerInterface $container): Filesystem {
                $config = $container->get('config');
                $storagePath = (string) $config['media']['storage_path'];

                if (!is_dir($storagePath)) {
                    mkdir($storagePath, 0775, true);
                }

                return new Filesystem(new LocalFilesystemAdapter($storagePath));
            },

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

            ListDisplaysHttpHandler::class => ReflectionBasedAbstractFactory::class,
            GetDisplayHttpHandler::class => ReflectionBasedAbstractFactory::class,
            CreateDisplayHttpHandler::class => ReflectionBasedAbstractFactory::class,
            UpdateDisplayHttpHandler::class => ReflectionBasedAbstractFactory::class,
            DeleteDisplayHandler::class => ReflectionBasedAbstractFactory::class,
            GetPresentationSessionHttpHandler::class => ReflectionBasedAbstractFactory::class,
            LoadSongIntoPresentationHttpHandler::class => ReflectionBasedAbstractFactory::class,
            PresentationControlHandler::class => ReflectionBasedAbstractFactory::class,

            PresentationSseHandler::class => static function (ContainerInterface $container): PresentationSseHandler {
                $config = $container->get('config');
                $wsConfig = $config['websocket'] ?? [];

                return new PresentationSseHandler(
                    getPresentationSessionHandler: $container->get(GetPresentationSessionQueryHandler::class),
                    entityManager: $container->get(EntityManagerInterface::class),
                    pollIntervalSeconds: (float) ($wsConfig['poll_interval_seconds'] ?? 0.25),
                    maxDurationSeconds: (int) ($wsConfig['sse_max_duration_seconds'] ?? 55),
                );
            },

            DisplaysIndexPageHandler::class => ReflectionBasedAbstractFactory::class,

            PresentationControlPageHandler::class => static function (
                ContainerInterface $container,
            ): PresentationControlPageHandler {
                $config = $container->get('config');

                return new PresentationControlPageHandler(
                    getPresentationSessionHandler: $container->get(GetPresentationSessionQueryHandler::class),
                    searchSongsHandler: $container->get(SearchSongsHandler::class),
                    inertia: $container->get(InertiaResponseFactory::class),
                    websocketPort: (int) ($config['websocket']['port'] ?? 8090),
                );
            },

            ListMediaAssetsHandler::class => ReflectionBasedAbstractFactory::class,
            GetMediaAssetHttpHandler::class => ReflectionBasedAbstractFactory::class,
            DownloadMediaAssetHandler::class => ReflectionBasedAbstractFactory::class,
            DeleteMediaAssetHandler::class => ReflectionBasedAbstractFactory::class,
            MediaIndexPageHandler::class => ReflectionBasedAbstractFactory::class,

            UploadMediaAssetHttpHandler::class => static function (
                ContainerInterface $container,
            ): UploadMediaAssetHttpHandler {
                $config = $container->get('config');

                return new UploadMediaAssetHttpHandler(
                    uploadMediaAssetHandler: $container->get(\Phpresent\Media\Application\Command\UploadMediaAssetHandler::class),
                    maxUploadBytes: (int) ($config['media']['max_upload_bytes'] ?? 200 * 1024 * 1024),
                );
            },
        ],
        'abstract_factories' => [
            ReflectionBasedAbstractFactory::class,
        ],
    ],
];
