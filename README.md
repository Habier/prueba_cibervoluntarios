# Backend de Logística IoT

Backend en Symfony 7.4 / PHP 8.4 para la ingesta de GPS de alto rendimiento en un contexto de flota logística.

## Stack

- PHP 8.4
- Symfony 7.4
- API Platform
- FrankenPHP
- PostgreSQL + TimescaleDB + PostGIS
- RabbitMQ con colas duraderas y DLQ
- Doctrine ORM + Doctrine DBAL
- PHPUnit
- Doctrine Fixtures Bundle
- PHPStan nivel 8
- ECS + PHP-CS-Fixer
- GitHub Actions

## Ejecutar con Docker

```bash
docker compose up --build -d
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

## Ejecutar Workers

```bash
docker compose exec worker-gps php bin/console app:gps:consume
docker compose exec worker-alerts php bin/console app:alerts:consume
```

## Tests y Calidad

```bash
composer phpstan
composer ecs
composer ecs:fix
```

Ejecutar tests PHPUnit

```bash
composer test
```

Ejecutar el system test end-to-end contra el stack Docker local actual:

```bash
npm install
npm run test:system
```

`npm install` es necesario porque el runner usa la dependencia Node `pg`. `npm run test:system` asume la configuración Docker local por defecto de este proyecto (`app` en `localhost:8081`, PostgreSQL en `localhost:55432` y RabbitMQ management en `localhost:15672`). Asegúrate de tener `docker compose up -d`, migraciones aplicadas y datos base cargados antes de ejecutarlo.

Si quieres ejecutar `vendor/bin/phpunit` en el host en lugar de dentro de Docker, tu PHP local debe tener `pdo_pgsql` habilitado y la base de datos Docker debe estar expuesta en `127.0.0.1:55432`.

## Arquitectura

El código se divide en `Domain`, `Application` e `Infrastructure`.

- **Domain** contiene value objects, enums para conceptos estables y reglas de alertas.
- **Application** contiene comandos, puertos y servicios de orquestación.
- **Infrastructure** contiene adaptadores de API Platform, entidades/repositorios de Doctrine, adaptadores de RabbitMQ, health checks y workers de consola.

Es un diseño ligero inspirado en CQRS:

- **Lado de escritura**: `POST /api/gps-coordinates` y `POST /api/gps-coordinates/batch` publican en RabbitMQ.
- **Lado del worker**: `app:gps:consume` almacena mensajes en buffer, los persiste con DBAL, actualiza `vehicle_last_positions` y crea alertas.
- **Lado de lectura**: los providers de API Platform leen proyecciones optimizadas y coordenadas históricas.

## Por qué PostgreSQL + TimescaleDB + PostGIS

- PostgreSQL ofrece garantías transaccionales e indexación madura.
- TimescaleDB se adapta a la ingesta de series temporales con alta carga de escritura.
- PostGIS habilita consultas espaciales futuras y soporte para geofencing.
- Una única hypertable `gps_coordinates` escala mejor que una tabla por vehículo porque el overhead operativo, el coste de planificación y la gestión de esquemas se mantienen acotados.

## Por qué RabbitMQ en lugar de Redis

- Se requieren colas duraderas, mensajes persistentes, enrutamiento de DLQ y semánticas explícitas de ACK/NACK para una ingesta sin pérdidas.
- Redis puede añadirse como caché opcional, pero no como cola crítica principal.

## Por qué `php-amqplib/php-amqplib`

El worker crítico de GPS no usa Symfony Messenger. Usa `php-amqplib` directamente para controlar:

- `prefetch_count`
- ACK manual tras el commit de PostgreSQL
- Flush basado en timeout
- Flush basado en tamaño de batch
- Publicación en DLQ para mensajes inválidos

## Estrategia de Batch

- La ingesta HTTP acepta arrays y publica inmediatamente un mensaje persistente por coordenada.
- El worker almacena mensajes en memoria.
- El flush ocurre cuando se alcanza `GPS_BATCH_SIZE` o expira `GPS_FLUSH_TIMEOUT_MS`.
- Los inserts usan una única sentencia SQL multi-fila con `ON CONFLICT DO NOTHING`.

## Estrategia de Idempotencia

- Si existe `externalId`, `(vehicle_id, external_id)` es único.
- En caso contrario, `(vehicle_id, device_timestamp, latitude, longitude)` es la clave natural de deduplicación.
- El worker solo genera alertas a partir de filas realmente insertadas, evitando duplicados por reejecución.

## Garantías de No Pérdida de Datos

- Los mensajes son persistentes.
- La cola principal y la DLQ son duraderas.
- El ACK ocurre solo tras el commit exitoso de la base de datos.
- Si PostgreSQL falla, los mensajes permanecen sin ACK y son reentregados.
- Los payloads inválidos se enrutan a la DLQ.

## Reglas de Validación

- Latitud: `-90..90`
- Longitud: `-180..180`
- `speedKmh >= 0`
- `accuracy >= 0` cuando se proporciona
- `vehicleId` debe ser UUID
- `deviceTimestamp` debe ser válido

Se aceptan timestamps de dispositivo futuros. La API devuelve una advertencia y registra contexto estructurado.

## Endpoints de Salud

- `GET /ready`: verifica la conectividad con PostgreSQL y RabbitMQ (usado por Docker healthcheck)

## Escalado

Escala horizontalmente aumentando las réplicas de `worker-gps` y ajustando:

- `GPS_PREFETCH_COUNT`
- `GPS_BATCH_SIZE`
- Tamaño del pool de conexiones de PostgreSQL
- Throughput de la cola de RabbitMQ

Como el modelo de escritura es asíncrono e idempotente, más workers pueden procesar en paralelo de forma segura.

## Limitaciones Conocidas

- El worker de alertas es actualmente un placeholder porque la generación de alertas ocurre dentro de la transacción de GPS.
- La política de reintentos depende de la reentrega del broker en lugar de colas de reintento con retardo.

## Mejoras Futuras

- Añadir Outbox Pattern para fiabilidad de eventos internos.
- Añadir reglas de geofence y desviación de ruta.
- Mover el fan-out de alertas a una cola dedicada cuando el volumen de alertas crezca.
- Añadir exportación de métricas dedicada.
