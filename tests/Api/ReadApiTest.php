<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\FixtureIds;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ReadApiTest extends DatabaseTestCase
{
    public function testGetVehiclesReturnsLastPosition(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(3, $items);
        self::assertArrayHasKey('lastPosition', $items[0]);
    }

    public function testGetVehicleCoordinatesSupportsFilters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?limit=1');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(1, $items);
    }

    public function testGetVehicleCoordinatesUsesDefaultLimit(): void
    {
        $this->insertCoordinates(FixtureIds::VEHICLE_1, 'default-limit', 60, new \DateTimeImmutable('2026-02-01T00:00:00+00:00'));

        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;

        self::assertCount(50, $items);
        self::assertSame('default-limit-59', $items[0]['externalId']);
        self::assertSame('default-limit-10', $items[49]['externalId']);
    }

    public function testGetVehicleCoordinatesLimitIsClampedToFiveHundred(): void
    {
        $this->insertCoordinates(FixtureIds::VEHICLE_1, 'clamped-limit', 520, new \DateTimeImmutable('2026-03-01T00:00:00+00:00'));

        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?limit=9999');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;

        self::assertCount(500, $items);
        self::assertSame('clamped-limit-519', $items[0]['externalId']);
        self::assertSame('clamped-limit-20', $items[499]['externalId']);
    }

    private function insertCoordinates(string $vehicleId, string $externalPrefix, int $count, \DateTimeImmutable $baseTimestamp): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        for ($index = 0; $index < $count; ++$index) {
            $timestamp = $baseTimestamp->modify(sprintf('+%d seconds', $index));

            $connection->insert('gps_coordinates', [
                'id' => sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', $index),
                'external_id' => sprintf('%s-%d', $externalPrefix, $index),
                'vehicle_id' => $vehicleId,
                'latitude' => 40.0 + ($index / 1000),
                'longitude' => -3.0 - ($index / 1000),
                'altitude' => 10.0,
                'speed_kmh' => 60.0,
                'accuracy' => 5.0,
                'device_timestamp' => $timestamp->format('Y-m-d H:i:sP'),
                'received_at' => $timestamp->format('Y-m-d H:i:sP'),
            ]);
        }

        self::ensureKernelShutdown();
    }

    public function testGetVehicleCoordinatesFiltersByFrom(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?from=2026-01-01T12:00:01%2B00:00');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;

        self::assertCount(1, $items);
        self::assertSame('coord-2', $items[0]['externalId']);
    }

    public function testGetVehicleCoordinatesFiltersByTo(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?to=2026-01-01T12:00:01%2B00:00');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;

        self::assertCount(1, $items);
        self::assertSame('coord-1', $items[0]['externalId']);
    }

    public function testGetVehicleCoordinatesFiltersByFromAndTo(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?from=2026-01-01T12:00:00%2B00:00&to=2026-01-01T12:00:00%2B00:00');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;

        self::assertCount(1, $items);
        self::assertSame('coord-1', $items[0]['externalId']);
    }

    public function testGetVehicleCoordinatesReturnsEmptyResultWhenNoRowsMatch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicles/' . FixtureIds::VEHICLE_1 . '/coordinates?from=2030-01-01T00:00:00%2B00:00');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertSame([], $items);
    }
}
