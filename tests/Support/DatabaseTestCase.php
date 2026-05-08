<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DataFixtures\AppFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

abstract class DatabaseTestCase extends WebTestCase
{
    private static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        if (! self::$schemaInitialized) {
            if (static::$kernel === null) {
                throw new \LogicException('Kernel is not booted.');
            }

            $application = new Application(static::$kernel);
            $application->setAutoExit(false);

            $migrateCommand = $application->find('doctrine:migrations:migrate');
            $migrateTester = new CommandTester($migrateCommand);
            $migrateTester->execute([
                '--no-interaction' => true,
            ]);

            self::$schemaInitialized = true;
        }

        $connection->executeStatement('TRUNCATE TABLE alerts, vehicle_last_positions, gps_coordinate_ingestion_keys, gps_coordinates, vehicles, fleets, vehicle_types, alert_types RESTART IDENTITY CASCADE');

        $fixtures = new AppFixtures();
        $fixtures->load($entityManager);
        $entityManager->clear();
        self::ensureKernelShutdown();
    }
}
