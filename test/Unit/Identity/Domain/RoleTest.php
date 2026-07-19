<?php

declare(strict_types=1);

use Phpresent\Identity\Domain\Entity\Role;

it('checks membership of a permission', function (): void {
    $role = new Role('operator', ['songs.sync', 'songsets.reorder']);

    expect($role->hasPermission('songs.sync'))->toBeTrue();
    expect($role->hasPermission('users.manage'))->toBeFalse();
});

it('has no permissions by default', function (): void {
    $role = new Role('viewer');

    expect($role->permissions())->toBe([]);
    expect($role->hasPermission('anything'))->toBeFalse();
});
