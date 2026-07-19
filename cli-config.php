<?php

declare(strict_types=1);

use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;

chdir(__DIR__);
require 'vendor/autoload.php';

/** @var \Laminas\ServiceManager\ServiceManager $container */
$container = require 'config/container.php';

/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get(EntityManagerInterface::class);

return DependencyFactory::fromEntityManager(
    new PhpFile(__DIR__ . '/config/migrations.php'),
    new ExistingEntityManager($entityManager),
);
