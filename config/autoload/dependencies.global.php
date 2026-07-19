<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phpresent\Shared\Infrastructure\Http\InertiaResponseFactory;
use Phpresent\Shared\Infrastructure\Persistence\EntityManagerFactory;
use Phpresent\Song\Application\Service\SongSourceInterface;
use Phpresent\Song\Domain\Repository\SongRepositoryInterface;
use Phpresent\Song\Domain\Repository\SyncStateRepositoryInterface;
use Phpresent\Song\Infrastructure\Persistence\DoctrineSongRepository;
use Phpresent\Song\Infrastructure\Persistence\DoctrineSyncStateRepository;
use Phpresent\Song\Infrastructure\SongbookPro\SongSource;
use Phpresent\Song\Presentation\Http\Handler\GetSongHandler as GetSongHttpHandler;
use Phpresent\Song\Presentation\Http\Handler\ListSongsHandler;
use Phpresent\Song\Presentation\Http\Handler\SyncSongsHandler as SyncSongsHttpHandler;
use Phpresent\SongbookPro\Infrastructure\Cache\ETagCacheInterface;
use Phpresent\SongbookPro\Infrastructure\Cache\PsrETagCache;
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
            ETagCacheInterface::class => PsrETagCache::class,
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

            GraphQLClientInterface::class => static function (ContainerInterface $container): GraphQLClientInterface {
                $config = $container->get('config');
                $sbpConfig = $config['songbookpro'];

                return new SongbookProGraphQLClient(
                    httpClient: $container->get(GuzzleClientInterface::class),
                    rateLimiter: $container->get(RateLimiter::class),
                    etagCache: $container->get(ETagCacheInterface::class),
                    logger: $container->get(LoggerInterface::class),
                    apiUrl: (string) $sbpConfig['api_url'],
                    apiToken: (string) $sbpConfig['api_token'],
                    maxRetries: (int) $sbpConfig['graphql']['max_retries'],
                    retryBaseDelayMs: (int) $sbpConfig['graphql']['retry_base_delay_ms'],
                );
            },

            SongSource::class => static function (ContainerInterface $container): SongSource {
                $config = $container->get('config');

                return new SongSource(
                    client: $container->get(GraphQLClientInterface::class),
                    mapper: $container->get(\Phpresent\Song\Infrastructure\Mapper\SongGraphQLMapper::class),
                    pageSize: (int) $config['songbookpro']['graphql']['page_size'],
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
        ],
        'abstract_factories' => [
            ReflectionBasedAbstractFactory::class,
        ],
    ],
];
