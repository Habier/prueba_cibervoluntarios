<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Double\InMemoryGpsMessagePublisher;
use App\Tests\Support\DatabaseTestCase;

final class GpsIngestionApiTest extends DatabaseTestCase
{
    public function testPostGpsCoordinatesPublishesMappedMessagesForEachCoordinate(): void
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
            ], [
                'externalId' => 'http-2',
                'vehicleId' => '99999999-9999-4999-8999-999999999999',
                'latitude' => 41.0,
                'longitude' => -4.0,
                'speedKmh' => 70,
                'accuracy' => 3,
                'deviceTimestamp' => '2026-01-01T12:01:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(202);
        self::assertCount(2, $publisher->messages);
        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $data['accepted']);

        self::assertSame('http-1', $publisher->messages[0]['externalId']);
        self::assertSame('88888888-8888-4888-8888-888888888888', $publisher->messages[0]['vehicleId']);
        self::assertSame(40.0, $publisher->messages[0]['latitude']);
        self::assertSame(-3.0, $publisher->messages[0]['longitude']);
        self::assertSame(50.0, $publisher->messages[0]['speedKmh']);
        self::assertSame(5.0, $publisher->messages[0]['accuracy']);
        self::assertSame('2026-01-01T12:00:00+00:00', $publisher->messages[0]['deviceTimestamp']);
        self::assertArrayHasKey('receivedAt', $publisher->messages[0]);

        self::assertSame('http-2', $publisher->messages[1]['externalId']);
        self::assertSame('99999999-9999-4999-8999-999999999999', $publisher->messages[1]['vehicleId']);
        self::assertSame(41.0, $publisher->messages[1]['latitude']);
        self::assertSame(-4.0, $publisher->messages[1]['longitude']);
        self::assertSame(70.0, $publisher->messages[1]['speedKmh']);
        self::assertSame(3.0, $publisher->messages[1]['accuracy']);
        self::assertSame('2026-01-01T12:01:00+00:00', $publisher->messages[1]['deviceTimestamp']);
        self::assertArrayHasKey('receivedAt', $publisher->messages[1]);
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

    public function testUnknownVehicleIsRejectedBeforePublishing(): void
    {
        $client = static::createClient();
        /** @var InMemoryGpsMessagePublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryGpsMessagePublisher::class);
        $publisher->reset();

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'externalId' => 'http-unknown-1',
                'vehicleId' => '12121212-1212-4121-8121-121212121212',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $publisher->messages);

        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://api-platform.com/errors/unknown-vehicle-id', $data['type'] ?? null);
        self::assertSame('Unknown vehicle identifier', $data['title'] ?? null);
        self::assertStringContainsString('12121212-1212-4121-8121-121212121212', (string) ($data['detail'] ?? ''));
    }

    public function testMixedKnownAndUnknownVehiclesRejectsWholeBatchWithoutPublishing(): void
    {
        $client = static::createClient();
        /** @var InMemoryGpsMessagePublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryGpsMessagePublisher::class);
        $publisher->reset();

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'externalId' => 'http-known-1',
                'vehicleId' => '88888888-8888-4888-8888-888888888888',
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ], [
                'externalId' => 'http-unknown-2',
                'vehicleId' => '13131313-1313-4131-8131-131313131313',
                'latitude' => 40.1,
                'longitude' => -3.1,
                'speedKmh' => 51,
                'deviceTimestamp' => '2026-01-01T12:00:01+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $publisher->messages);

        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://api-platform.com/errors/unknown-vehicle-id', $data['type'] ?? null);
        self::assertStringContainsString('13131313-1313-4131-8131-131313131313', (string) ($data['detail'] ?? ''));
    }

    public function testMultipleUnknownVehiclesAreAllReportedAndNothingIsPublished(): void
    {
        $client = static::createClient();
        /** @var InMemoryGpsMessagePublisher $publisher */
        $publisher = static::getContainer()->get(InMemoryGpsMessagePublisher::class);
        $publisher->reset();

        $firstUnknownId = '14141414-1414-4141-8141-141414141414';
        $secondUnknownId = '15151515-1515-4151-8151-151515151515';

        $client->request('POST', '/api/gps-coordinates', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode([
            'coordinates' => [[
                'externalId' => 'http-unknown-1',
                'vehicleId' => $firstUnknownId,
                'latitude' => 40.0,
                'longitude' => -3.0,
                'speedKmh' => 50,
                'deviceTimestamp' => '2026-01-01T12:00:00+00:00',
            ], [
                'externalId' => 'http-unknown-2',
                'vehicleId' => $secondUnknownId,
                'latitude' => 40.1,
                'longitude' => -3.1,
                'speedKmh' => 51,
                'deviceTimestamp' => '2026-01-01T12:00:01+00:00',
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertSame([], $publisher->messages);

        $data = json_decode($client->getResponse()->getContent() ?: '[]', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://api-platform.com/errors/unknown-vehicle-id', $data['type'] ?? null);
        self::assertSame('Unknown vehicle identifier', $data['title'] ?? null);
        self::assertStringContainsString($firstUnknownId, (string) ($data['detail'] ?? ''));
        self::assertStringContainsString($secondUnknownId, (string) ($data['detail'] ?? ''));
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
