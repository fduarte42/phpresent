<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Phpresent\Identity\Presentation\Http\Handler\AssignRoleHandler;
use Phpresent\Identity\Presentation\Http\Handler\CreateRoleHandler;
use Phpresent\Identity\Presentation\Http\Handler\CreateUserHandler;
use Phpresent\Identity\Presentation\Http\Handler\DeactivateUserHandler;
use Phpresent\Identity\Presentation\Http\Handler\GetUserHandler;
use Phpresent\Identity\Presentation\Http\Handler\ListRolesHandler;
use Phpresent\Identity\Presentation\Http\Handler\ListUsersHandler;
use Phpresent\Identity\Presentation\Http\Handler\LoginHandler;
use Phpresent\Identity\Presentation\Http\Handler\LogoutHandler;
use Phpresent\Song\Presentation\Http\Handler\GetSongHandler;
use Phpresent\Song\Presentation\Http\Handler\ListSongsHandler;
use Phpresent\Song\Presentation\Http\Handler\SongsIndexPageHandler;
use Phpresent\Song\Presentation\Http\Handler\SyncSongsHandler;
use Phpresent\SongSet\Presentation\Http\Handler\GetSongSetHandler;
use Phpresent\SongSet\Presentation\Http\Handler\ListSongSetsHandler;
use Phpresent\SongSet\Presentation\Http\Handler\ReorderSongSetItemsHandler;
use Phpresent\SongSet\Presentation\Http\Handler\SongSetShowPageHandler;
use Phpresent\SongSet\Presentation\Http\Handler\SongSetsIndexPageHandler;
use Phpresent\SongSet\Presentation\Http\Handler\SyncSongSetsHandler;
use Psr\Container\ContainerInterface;

return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/songs', SongsIndexPageHandler::class, 'songs.index');

    $app->get('/api/songs', ListSongsHandler::class, 'api.songs.list');
    $app->get('/api/songs/{id}', GetSongHandler::class, 'api.songs.get');
    $app->post('/api/songs/sync', SyncSongsHandler::class, 'api.songs.sync');

    $app->get('/songsets', SongSetsIndexPageHandler::class, 'songsets.index');
    $app->get('/songsets/{id}', SongSetShowPageHandler::class, 'songsets.show');

    $app->get('/api/songsets', ListSongSetsHandler::class, 'api.songsets.list');
    $app->get('/api/songsets/{id}', GetSongSetHandler::class, 'api.songsets.get');
    $app->post('/api/songsets/sync', SyncSongSetsHandler::class, 'api.songsets.sync');
    $app->post('/api/songsets/{id}/reorder', ReorderSongSetItemsHandler::class, 'api.songsets.reorder');

    $app->post('/login', LoginHandler::class, 'login');
    $app->post('/logout', LogoutHandler::class, 'logout');

    $app->get('/api/users', ListUsersHandler::class, 'api.users.list');
    $app->get('/api/users/{id}', GetUserHandler::class, 'api.users.get');
    $app->post('/api/users', CreateUserHandler::class, 'api.users.create');
    $app->post('/api/users/{id}/roles', AssignRoleHandler::class, 'api.users.assign_role');
    $app->post('/api/users/{id}/deactivate', DeactivateUserHandler::class, 'api.users.deactivate');

    $app->get('/api/roles', ListRolesHandler::class, 'api.roles.list');
    $app->post('/api/roles', CreateRoleHandler::class, 'api.roles.create');
};
