<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Application\Port\VehicleCatalogInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class CachedVehicleCatalog implements VehicleCatalogInterface
{
    public function __construct(
        private VehicleCatalogInterface $inner,
        private CacheItemPoolInterface $cache,
        private int $knownVehicleTtlSeconds = 300,
        private int $unknownVehicleTtlSeconds = 15,
    ) {
    }

    public function findKnownVehicleIds(array $vehicleIds): array
    {
        if ($vehicleIds === []) {
            return [];
        }

        $uniqueVehicleIds = array_values(array_unique($vehicleIds));
        $keysByVehicleId = [];

        foreach ($uniqueVehicleIds as $vehicleId) {
            $keysByVehicleId[$vehicleId] = sprintf('vehicle_catalog.exists.%s', sha1($vehicleId));
        }

        $items = [];

        foreach ($this->cache->getItems(array_values($keysByVehicleId)) as $key => $item) {
            $items[$key] = $item;
        }
        $existenceByVehicleId = [];
        $missingVehicleIds = [];

        foreach ($uniqueVehicleIds as $vehicleId) {
            $item = $items[$keysByVehicleId[$vehicleId]] ?? null;

            if ($item !== null && $item->isHit()) {
                $existenceByVehicleId[$vehicleId] = (bool) $item->get();

                continue;
            }

            $missingVehicleIds[] = $vehicleId;
        }

        if ($missingVehicleIds !== []) {
            $knownVehicleIds = $this->inner->findKnownVehicleIds($missingVehicleIds);
            $knownLookup = array_fill_keys($knownVehicleIds, true);

            foreach ($missingVehicleIds as $vehicleId) {
                $exists = isset($knownLookup[$vehicleId]);
                $existenceByVehicleId[$vehicleId] = $exists;

                $item = $this->cache->getItem($keysByVehicleId[$vehicleId]);
                $item->set($exists);
                $item->expiresAfter($exists ? $this->knownVehicleTtlSeconds : $this->unknownVehicleTtlSeconds);
                $this->cache->saveDeferred($item);
            }

            $this->cache->commit();
        }

        return array_values(array_filter(
            $uniqueVehicleIds,
            static fn (string $vehicleId): bool => $existenceByVehicleId[$vehicleId] ?? false,
        ));
    }
}
