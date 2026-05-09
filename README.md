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
composer initialize
```

## Inicializar base de datos

Comando recomendado para preparar el entorno local:

```bash
composer initialize
```

Este comando:

1. levanta `database` y `app`
2. ejecuta migraciones
3. carga fixtures

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

### Nota importante sobre `composer test` y RabbitMQ e2e

El script `composer test` hace tres cosas deliberadas antes de ejecutar PHPUnit:

1. levanta `database`, `app` y `rabbitmq`
2. **detiene** `worker-gps` y `worker-alerts`
3. **reinicia** `app`

Esto evita tests frágiles en `GpsWorkerE2eTest`:

- Si un worker Docker queda corriendo en paralelo, puede consumir mensajes de la cola mientras PHPUnit ejecuta el test, robando carga y rompiendo aserciones de conteo.
- El test e2e necesita control total del ciclo de consumo (publicar, luego consumir explícitamente dentro del propio test).
- El restart de `app` garantiza un contenedor limpio para las variables de entorno de test y evita estado residual entre ejecuciones.

En resumen: ese e2e **no** comprueba escalado multi-worker; comprueba de forma determinista el contrato end-to-end de publicación + consumo + persistencia bajo un único consumidor controlado por PHPUnit.

Ejecutar el system test end-to-end contra el stack Docker local actual:

```bash
composer test:system
```

`composer test:system` ejecuta `composer initialize`, luego `npm ci` y finalmente `npm run test:system`. El runner usa la dependencia Node `pg` y asume la configuración Docker local por defecto de este proyecto (`app` en `localhost:8081`, PostgreSQL en `localhost:55432` y RabbitMQ management en `localhost:15672`).

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

## Decisiones de diseño

### Qué problema resuelve esta arquitectura

El objetivo principal no es responder con lógica pesada dentro de la petición HTTP, sino **absorber picos de escritura sin perder datos**.

Por eso el sistema separa claramente dos responsabilidades:

- **HTTP** valida rápido y delega trabajo.
- **Workers** hacen el trabajo costoso fuera de la latencia del cliente.

### Decisiones clave

| Decisión | Por qué se tomó | Trade-off aceptado |
|---|---|---|
| Ingesta asíncrona con RabbitMQ | desacopla el pico HTTP del ritmo real de persistencia | la consistencia visible para lectura no es inmediata |
| Cache Redis para existencia de vehículos | evita consultar PostgreSQL en cada request repetida y reduce presión sobre conexiones | una caché negativa demasiado larga puede rechazar temporalmente un vehículo recién creado |
| Un mensaje por coordenada | simplifica reintentos, DLQ, trazabilidad e idempotencia | aumenta presión sobre broker si el volumen crece mucho |
| Batch en el worker | reduce round-trips a PostgreSQL y mejora throughput | añade complejidad de flush por tiempo/tamaño |
| ACK tras commit | prioriza durabilidad frente a pérdida silenciosa | si PostgreSQL se degrada, aumentará el backlog en cola |
| Rechazo temprano de `vehicleId` desconocido | evita aceptar datos que luego serían descartados | obliga a que el catálogo esté disponible en la ruta de ingesta |
| Proyección `vehicle_last_positions` separada | optimiza lecturas frecuentes sin recorrer histórico completo | hay que mantener una vista resumida además del histórico |

### Qué se prioriza explícitamente

1. **Durabilidad** antes que latencia artificialmente baja.
2. **Idempotencia** antes que throughput ingenuo.
3. **Escalado horizontal de consumidores** antes que lógica compleja en la API.
4. **Lecturas optimizadas** mediante proyecciones, no consultas pesadas sobre el histórico completo.

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
- `vehicleId` debe existir en el catálogo de vehículos (si no existe, la API rechaza el request con `400`, no publica en RabbitMQ y devuelve un error estable con `type = https://api-platform.com/errors/unknown-vehicle-id` y `detail` con los `vehicleId` desconocidos)

La comprobación de existencia de `vehicleId` usa una caché Redis dedicada:

- los `vehicleId` conocidos se cachean con TTL más largo
- los `vehicleId` desconocidos se cachean con TTL corto para no mantener rechazos obsoletos demasiado tiempo
- PostgreSQL sigue siendo la fuente de verdad

Se aceptan timestamps de dispositivo futuros. La API devuelve una advertencia y registra contexto estructurado.

## Endpoints de Salud

- `GET /ready`: verifica la conectividad con PostgreSQL y RabbitMQ (usado por Docker healthcheck)

## Documentación de la API

La API utiliza API Platform y genera automáticamente documentación OpenAPI 3.1.0.

### Acceder a la documentación

Archivo `openapi.yaml` en la raíz del proyecto, que contiene la especificación completa de la API con descripciones detalladas y ejemplos para todos los endpoints.

### Regenerar la documentación

Para regenerar el archivo `openapi.yaml` después de actualizar endpoints:

```bash
php bin/console api:openapi:export --yaml -o openapi.yaml
```

**Nota**: Al regenerar el archivo YAML, es necesario editar manualmente la sección `servers` para establecer el servidor local:

```yaml
servers:
  -
    url: http://localhost:8081
    description: 'Local development server'
```

## Escalado

Escala horizontalmente aumentando las réplicas de `worker-gps` y ajustando:

- `GPS_PREFETCH_COUNT`
- `GPS_BATCH_SIZE`
- Tamaño del pool de conexiones de PostgreSQL
- Throughput de la cola de RabbitMQ

Como el modelo de escritura es asíncrono e idempotente, más workers pueden procesar en paralelo de forma segura.

### Cómo pensar en picos de 25.000 requests por segundo

**Primero lo importante:** 25.000 rps no es un ajuste fino de variables. Es un objetivo de capacidad que exige diseñar el sistema como una tubería completa: **ingesta HTTP -> broker -> workers -> PostgreSQL**.

Si uno de esos tramos no acompaña, el sistema no escala de verdad; solo mueve el cuello de botella.

### Qué tendría que ocurrir para soportarlo

#### 1. La API HTTP debe hacer muy poco

Para absorber ese pico, la ruta HTTP debe limitarse a:

- validar shape y reglas básicas
- comprobar existencia de `vehicleId`
- publicar en RabbitMQ
- devolver `202/400` rápido

No debe:

- consultar histórico
- ejecutar lógica espacial pesada
- persistir coordenadas directamente en PostgreSQL
- generar alertas complejas dentro de la request

Ese principio ya está reflejado en esta solución y es la base correcta para escalar.

#### 2. RabbitMQ debe absorber el burst, no procesarlo todo en tiempo real

A 25.000 rps, el broker actúa como amortiguador. Eso significa que durante el pico puede crecer el backlog aunque la API siga respondiendo bien.

Eso es aceptable SI:

- la cola principal tiene capacidad suficiente
- las colas son duraderas
- hay observabilidad sobre lag, publish rate y consumer rate
- existe un SLO claro de cuánto retraso de procesamiento se tolera

En otras palabras: **soportar el pico no significa procesar todo instantáneamente**, sino no perder datos y drenar la cola a una velocidad sostenible.

#### 3. Los workers deben escalar horizontalmente con límites reales

Para aumentar capacidad de consumo hay que escalar `worker-gps`, pero con disciplina:

- ajustar `GPS_PREFETCH_COUNT`
- ajustar `GPS_BATCH_SIZE`
- ajustar `GPS_FLUSH_TIMEOUT_MS`
- limitar el número de workers al número real de conexiones/CPU/IO que PostgreSQL puede soportar

Más workers NO siempre implica más throughput. Si PostgreSQL entra en contención, solo conseguirás más competencia por CPU, locks y conexiones.

#### 4. PostgreSQL debe tratar la escritura como carga crítica

Para acercarse a 25.000 rps, la base de datos necesita estar tratada como componente principal del sistema, no como detalle de infraestructura.

Como mínimo:

- pool de conexiones dimensionado para los workers reales
- índices estrictamente necesarios en tablas calientes
- TimescaleDB bien configurado para el patrón temporal
- discos rápidos y WAL dimensionado para escritura sostenida
- vigilancia sobre bloat, checkpoints y saturación de IO

La estrategia de batch actual ayuda mucho, pero por sí sola no garantiza ese volumen.

#### 5. Las alertas pueden necesitar desacoplarse del commit principal

Hoy las alertas se generan dentro de la transacción de GPS. Eso simplifica consistencia, pero a gran escala puede convertirse en coste adicional en la ruta crítica del worker.

Si el volumen real o la complejidad de reglas crece, el siguiente paso natural es:

- persistir coordenadas y `vehicle_last_positions` primero
- publicar eventos internos o usar outbox
- procesar alertas en un pipeline separado

Eso ya está alineado con la sección de mejoras futuras.

### Plan práctico de escalado hacia 25.000 rps

#### Fase 1 — absorber picos de forma segura

- mantener la API mínima y asíncrona
- medir publish latency, queue depth y consumer lag
- validar idempotencia bajo reentrega
- ajustar tamaño de batch y prefetch con carga real

#### Fase 2 — aumentar throughput sostenido

- escalar `worker-gps` horizontalmente
- separar recursos de app HTTP y workers
- dimensionar PostgreSQL para escritura sostenida
- verificar que `vehicle_last_positions` y alertas no se convierten en el cuello de botella

#### Fase 3 — desacoplar trabajo secundario

- mover alertas complejas fuera de la transacción principal
- evaluar particionado/retención del histórico según volumen real
- introducir métricas y autoscaling basado en lag y tasa de drenaje

### Señales de que todavía NO estás listo para 25.000 rps

- no conoces el backlog máximo aceptable
- no mides lag de RabbitMQ
- no has hecho pruebas de carga con reintentos y duplicados
- el pool de PostgreSQL no está dimensionado
- workers y API compiten por los mismos recursos sin aislamiento
- alertas complejas siguen creciendo dentro de la transacción principal

### Resumen ejecutivo

Este diseño va en la dirección correcta para picos altos porque desacopla la ingesta del procesamiento y protege la durabilidad.

Pero para **soportar 25.000 requests por segundo de verdad**, hay que escalar y observar el sistema completo, no solo “subir workers”.

## Configuración de reglas de alertas

Las reglas de alertas críticas son configurables por entorno mediante variables:

- `GPS_SPEED_LIMIT_KMH`
- `GPS_IDLE_SPEED_THRESHOLD_KMH`
- `GPS_GEOFENCE_MIN_LATITUDE`
- `GPS_GEOFENCE_MAX_LATITUDE`
- `GPS_GEOFENCE_MIN_LONGITUDE`
- `GPS_GEOFENCE_MAX_LONGITUDE`

Si no se definen explícitamente, Symfony usa defaults seguros declarados en `config/services.yaml`.

## Limitaciones Conocidas

- El worker de alertas es actualmente un placeholder porque la generación de alertas ocurre dentro de la transacción de GPS.
- La política de reintentos depende de la reentrega del broker en lugar de colas de reintento con retardo.
- `vehicleId` desconocido se rechaza en la ingesta HTTP (400) antes de publicar en RabbitMQ.

## Alcance del ejercicio

Este proyecto está preparado para ser evaluado como ejercicio técnico con foco en:

- arquitectura orientada a ingesta asíncrona
- durabilidad e idempotencia
- separación de responsabilidades entre HTTP, broker, workers y lectura
- decisiones técnicas explicadas y cubiertas con tests relevantes

No afirma, por sí solo, soporte probado de 25.000 rps en producción sin una validación específica de carga en un entorno equivalente al real.

## Mejoras Futuras

- Añadir Outbox Pattern para fiabilidad de eventos internos.
- Añadir reglas de geofence y desviación de ruta.
- Mover el fan-out de alertas a una cola dedicada cuando el volumen de alertas crezca.
- Añadir exportación de métricas dedicada.
