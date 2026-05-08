# IoT Logistics Backend

Symfony 7.4 / PHP 8.4 backend for high-throughput GPS ingestion in a logistics fleet context.

## Stack

- PHP 8.4
- Symfony 7.4
- API Platform
- FrankenPHP
- PostgreSQL + TimescaleDB + PostGIS
- RabbitMQ with durable queues and DLQ
- Doctrine ORM + Doctrine DBAL
- PHPUnit
- Doctrine Fixtures Bundle
- PHPStan level 8
- ECS + PHP-CS-Fixer
- GitHub Actions

## Run With Docker

```bash
docker compose up --build -d
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

## Run Workers

```bash
docker compose exec worker-gps php bin/console app:gps:consume
docker compose exec worker-alerts php bin/console app:alerts:consume
```

## Tests And Quality

```bash
vendor/bin/phpstan analyse
vendor/bin/ecs check
vendor/bin/ecs check --fix
```

Production-like tests run on PostgreSQL/TimescaleDB, not SQLite.

Local test database bootstrap:

```bash
composer test
```

If you only want to pre-start the required services without running the suite:

```bash
composer test:setup
```

If you want to run `vendor/bin/phpunit` on the host instead of inside Docker, your local PHP must have `pdo_pgsql` enabled and the Docker database must be exposed on `127.0.0.1:55432`.

## Architecture

The code is split into `Domain`, `Application`, and `Infrastructure`.

- Domain contains value objects, enums for stable concepts, and alert rules.
- Application contains commands, ports, and orchestration services.
- Infrastructure contains API Platform adapters, Doctrine entities/repositories, RabbitMQ adapters, health checks, and console workers.

This is a lightweight CQRS-inspired design:

- Write side: `POST /api/gps-coordinates` and `POST /api/gps-coordinates/batch` publish to RabbitMQ.
- Worker side: `app:gps:consume` buffers messages, persists them with DBAL, updates `vehicle_last_positions`, and creates alerts.
- Read side: API Platform providers read optimized projections and historical coordinates.

## Why PostgreSQL + TimescaleDB + PostGIS

- PostgreSQL gives transactional guarantees and mature indexing.
- TimescaleDB fits append-heavy time-series GPS ingestion.
- PostGIS enables future spatial queries and geofencing support.
- A single `gps_coordinates` hypertable scales better than one table per vehicle because operational overhead, planning cost, and schema management stay bounded.

## Why RabbitMQ Instead Of Redis

- Durable queues, persistent messages, DLQ routing, and explicit ACK/NACK semantics are required for no-loss ingestion.
- Redis can be added as an optional cache, but not as the primary critical queue.

## Why `php-amqplib/php-amqplib`

The critical GPS worker does not use Symfony Messenger. It uses `php-amqplib` directly to control:

- `prefetch_count`
- manual ACK after PostgreSQL commit
- timeout-based flush
- batch-size-based flush
- DLQ publishing for invalid messages

## Batch Strategy

- HTTP ingestion accepts arrays and immediately publishes one persistent message per coordinate.
- The worker buffers messages in memory.
- Flush happens when `GPS_BATCH_SIZE` is reached or `GPS_FLUSH_TIMEOUT_MS` expires.
- Inserts use a single multi-row SQL statement with `ON CONFLICT DO NOTHING`.

## Idempotency Strategy

- If `externalId` exists, `(vehicle_id, external_id)` is unique.
- Otherwise `(vehicle_id, device_timestamp, latitude, longitude)` is the natural dedupe key.
- The worker only generates alerts from rows actually inserted, avoiding replay duplicates.

## No Data Loss Guarantees

- Messages are persistent.
- Main queue and DLQ are durable.
- ACK happens only after successful database commit.
- If PostgreSQL fails, messages stay unacked and are redelivered.
- Invalid payloads are routed to the DLQ.

## Validation Rules

- Latitude: `-90..90`
- Longitude: `-180..180`
- `speedKmh >= 0`
- `accuracy >= 0` when provided
- `vehicleId` must be UUID
- `deviceTimestamp` must be valid

Future device timestamps are accepted. The API returns a warning and logs structured context.

## Health Endpoints

- `GET /ready`: checks PostgreSQL and RabbitMQ connectivity (used by Docker healthcheck)

## Scaling

Scale horizontally by increasing `worker-gps` replicas and tuning:

- `GPS_PREFETCH_COUNT`
- `GPS_BATCH_SIZE`
- PostgreSQL connection pool sizing
- RabbitMQ queue throughput

Because the write model is asynchronous and idempotent, more workers can process in parallel safely.

## Known Limitations

- Alert worker is currently a placeholder because alert generation happens inside the GPS transaction.
- Retry policy relies on broker redelivery instead of delayed retry queues.
- Test database uses SQLite for fast local CI-oriented checks, not TimescaleDB-specific behavior.

## Future Improvements

- Add Outbox Pattern for internal event reliability.
- Add geofence and route-deviation rules.
- Move alert fan-out to a dedicated queue when alert volume grows.
- Add dedicated metrics export.
