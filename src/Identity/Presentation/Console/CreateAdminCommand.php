<?php

declare(strict_types=1);

namespace Phpresent\Identity\Presentation\Console;

use Phpresent\Identity\Domain\Entity\Role;
use Phpresent\Identity\Domain\Entity\User;
use Phpresent\Identity\Domain\Exception\InvalidEmailException;
use Phpresent\Identity\Domain\Repository\RoleRepositoryInterface;
use Phpresent\Identity\Domain\Repository\UserRepositoryInterface;
use Phpresent\Identity\Domain\Service\PasswordHasherInterface;
use Phpresent\Identity\Domain\ValueObject\Email;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bootstraps the first admin user, bypassing `PermissionInterface` on
 * purpose. `CreateUserHandler`/`CreateRoleHandler` (§18.2) both require an
 * already-authenticated actor with `users.manage`/`roles.manage` — from an
 * empty database that's a real chicken-and-egg problem (no user can ever be
 * granted a permission, because granting one requires already being logged
 * in as someone who has it). This command goes straight to the
 * repositories instead, the same way a framework's seeder/fixture command
 * would — it's an install-time operation run with shell access, not a web
 * request, so it isn't gated the same way.
 */
#[AsCommand(name: 'identity:create-admin', description: 'Creates the first admin user and an "admin" role with every known permission.')]
final class CreateAdminCommand extends Command
{
    private const array ADMIN_PERMISSIONS = ['users.view', 'users.manage', 'roles.view', 'roles.manage'];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly PasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email address')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password')
            ->addOption('display-name', null, InputOption::VALUE_REQUIRED, 'Admin display name', 'Administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $email = new Email($this->stringOption($input, 'email'));
        } catch (InvalidEmailException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $password = $this->stringOption($input, 'password');

        if ($password === '') {
            $io->error('--password is required.');

            return Command::FAILURE;
        }

        if ($this->userRepository->findByEmail($email->toString()) !== null) {
            $io->error(sprintf('A user with email "%s" already exists.', $email->toString()));

            return Command::FAILURE;
        }

        $role = $this->roleRepository->findByName('admin');

        if ($role === null) {
            $role = new Role('admin', self::ADMIN_PERMISSIONS);
            $this->roleRepository->save($role);
            $io->writeln('Created "admin" role with permissions: ' . implode(', ', self::ADMIN_PERMISSIONS));
        }

        $user = new User(
            email: $email,
            passwordHash: $this->passwordHasher->hash($password),
            displayName: $this->stringOption($input, 'display-name'),
            roleIds: [$role->id()->toString()],
        );
        $this->userRepository->save($user);

        $io->success(sprintf('Created admin user "%s".', $email->toString()));

        return Command::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : '';
    }
}
