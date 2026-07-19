<?php

declare(strict_types=1);

use Phpresent\Identity\Application\Command\LoginCommand;
use Phpresent\Identity\Application\Command\LoginHandler;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Exception\InvalidCredentialsException;
use Phpresent\Identity\Domain\ValueObject\Email;
use PhpresentTest\Support\FakePasswordHasher;
use PhpresentTest\Support\InMemoryUserRepository;

it('authenticates with correct credentials', function (): void {
    $hasher = new FakePasswordHasher();
    $userRepository = new InMemoryUserRepository();
    $userRepository->save(new User(new Email('operator@example.com'), $hasher->hash('secret'), 'Operator'));
    $handler = new LoginHandler($userRepository, $hasher);

    $dto = $handler(new LoginCommand('Operator@Example.com', 'secret'));

    expect($dto->email)->toBe('operator@example.com');
});

it('rejects an unknown email', function (): void {
    $handler = new LoginHandler(new InMemoryUserRepository(), new FakePasswordHasher());

    $handler(new LoginCommand('nobody@example.com', 'secret'));
})->throws(InvalidCredentialsException::class);

it('rejects a wrong password', function (): void {
    $hasher = new FakePasswordHasher();
    $userRepository = new InMemoryUserRepository();
    $userRepository->save(new User(new Email('operator@example.com'), $hasher->hash('secret'), 'Operator'));
    $handler = new LoginHandler($userRepository, $hasher);

    $handler(new LoginCommand('operator@example.com', 'wrong'));
})->throws(InvalidCredentialsException::class);

it('rejects a deactivated user even with the correct password', function (): void {
    $hasher = new FakePasswordHasher();
    $userRepository = new InMemoryUserRepository();
    $user = new User(new Email('operator@example.com'), $hasher->hash('secret'), 'Operator');
    $user->deactivate(new DateTimeImmutable());
    $userRepository->save($user);
    $handler = new LoginHandler($userRepository, $hasher);

    $handler(new LoginCommand('operator@example.com', 'secret'));
})->throws(InvalidCredentialsException::class);
