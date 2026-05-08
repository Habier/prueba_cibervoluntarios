<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\DataFixtures\FixtureIds;
use App\Tests\Support\DatabaseTestCase;

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

    public function testGetFleets(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/fleets');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(2, $items);
    }

    public function testGetFleetVehicles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/fleets/' . FixtureIds::FLEET_NORTH . '/vehicles');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(1, $items);
    }

    public function testGetVehicleTypes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/vehicle-types');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(3, $items);
    }

    public function testGetAlertTypes(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/alert-types');

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        $items = $data['member'] ?? $data['hydra:member'] ?? $data;
        self::assertCount(2, $items);
    }
}
