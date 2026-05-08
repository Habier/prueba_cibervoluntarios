<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Cache;

use App\Application\Port\VehicleCatalogInterface;
use App\Infrastructure\Cache\CachedVehicleCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedVehicleCatalogTest extends TestCase
{
    public function testCachesKnownVehicleIdsAcrossCalls(): void
    {
        $inner = new class implements VehicleCatalogInterface {
            public int $calls = 0;

            public function findKnownVehicleIds(array $vehicleIds): array
            {
                ++$this->calls;

                return array_values(array_filter(
                    $vehicleIds,
                    static fn (string $vehicleId): bool => in_array($vehicleId, ['veh-1', 'veh-2'], true),
                ));
            }
        };

        $catalog = new CachedVehicleCatalog($inner, new ArrayAdapter(), 300, 1);

        self::assertSame(['veh-1', 'veh-2'], $catalog->findKnownVehicleIds(['veh-1', 'veh-2']));
        self::assertSame(['veh-2', 'veh-1'], $catalog->findKnownVehicleIds(['veh-2', 'veh-1']));
        self::assertSame(1, $inner->calls);
    }

    public function testNegativeCacheExpiresSoonerThanKnownCache(): void
    {
        $inner = new class implements VehicleCatalogInterface {
            public int $calls = 0;

            public function findKnownVehicleIds(array $vehicleIds): array
            {
                ++$this->calls;

                return [];
            }
        };

        $catalog = new CachedVehicleCatalog($inner, new ArrayAdapter(), 300, 1);

        self::assertSame([], $catalog->findKnownVehicleIds(['unknown-veh']));
        self::assertSame([], $catalog->findKnownVehicleIds(['unknown-veh']));
        self::assertSame(1, $inner->calls);

        sleep(2);

        self::assertSame([], $catalog->findKnownVehicleIds(['unknown-veh']));
        self::assertSame(2, $inner->calls);
    }
}
