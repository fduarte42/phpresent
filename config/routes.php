<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Phpresent\Song\Presentation\Http\Handler\GetSongHandler;
use Phpresent\Song\Presentation\Http\Handler\ListSongsHandler;
use Phpresent\Song\Presentation\Http\Handler\SongsIndexPageHandler;
use Phpresent\Song\Presentation\Http\Handler\SyncSongsHandler;
use Psr\Container\ContainerInterface;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/songs', SongsIndexPageHandler::class, 'songs.index');

    $app->get('/api/songs', ListSongsHandler::class, 'api.songs.list');
    $app->get('/api/songs/{id}', GetSongHandler::class, 'api.songs.get');
    $app->post('/api/songs/sync', SyncSongsHandler::class, 'api.songs.sync');
};
