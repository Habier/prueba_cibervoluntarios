<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Double\InMemoryGpsMessagePublisher;
use App\Tests\Support\DatabaseTestCase;

final class GpsIngestionApiTest extends DatabaseTestCase
{
    public function testPostGpsCoordinatesPublishesMessages(): void
    {
        $client = static::createClient();
        /** @var InMemoryGpsMessagePublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryGpsMessagePublisher::class);
        $publisher->reset();

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'externalId' => 'http-1',
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'accuracy' => 5,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);
        self::assertSame(1, count($publisher->messages));
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['accepted']);
    }

    public function testPostGpsCoordinatesBatchRejectsWhenLimitExceeded(): void
    {
        $client = static::createClient();

        $payload = [
            'coordinates' => [],
        ];
        for ($index = 0; $index < 501; ++$index) {
            $payload['coordinates'][] = [
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ];
        }

        $client->request('POST', '/api/gps-coordinates/batch', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode($payload, JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
    }

    public function testFutureDeviceTimestampIsAcceptedWithWarning(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2099-01-01T00:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('FUTURE_DEVICE_TIMESTAMP', $data['warnings'][0]['type']);
    }

    public function testInvalidLatitudeIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 91.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidLongitudeIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => 181.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testNegativeSpeedIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => -1,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testNegativeAccuracyIsRejected(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 1,
                'accuracy' => -1,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }
}
