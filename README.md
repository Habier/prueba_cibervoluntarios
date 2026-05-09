# Backend de Logística IoT de vehículos

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
```

## Inicializar base de datos


```bash
composer initialize
```

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

Ejecutar el system test end-to-end contra el stack Docker local actual:

```bash
composer test:system
```

## Decisiones de diseño

He optado por una aproximación DDD, sin tampoco pasarme de la raya por ser una prueba.


### FrankenPHP

Se eligió FrankenPHP por ser el runner PHP con mayor rendimiento. El trade-off aceptado es que es menos conocido y sus logs no son tan buenos como nginx+FPM.

### Pipelines
Véase [GitHub Actions](https://github.com/Habier/prueba_cibervoluntarios/actions) con lo que ha estado ocurriendo.
Incluye test de calidad, ECS y PHPstan, lanzamiento de suit PHPUnit y por ultimo un smoke test.
De esta forma, el código es evaluado en cada entrada a master e independiente a las pruebas que ejecute en local.

### Ingesta asíncrona con RabbitMQ

La ingesta asíncrona con RabbitMQ desacopla el pico HTTP del ritmo real de persistencia. La desventaja es que la consistencia visible para lectura no es inmediata.

### Rechazo temprano de `vehicleId` desconocido

Evita aceptar datos que luego serían descartados. Supone más carga en la API, pero notificaría a los dispositivos IOT de su error. De ahí la siguiente medida.

### Cache Redis para existencia de vehículos

Evita consultar DB en cada request repetida, aunque signifique manejar un servicio más.

## Por qué PostgreSQL + TimescaleDB + PostGIS

- PostgreSQL es una buena DB para manejar datos de GPS y permite tener una única hypertable `gps_coordinates`.
- TimescaleDB se adapta a la ingesta de series temporales con alta carga de escritura.
- PostGIS habilita consultas espaciales futuras y soporte para geofencing.

## Por qué RabbitMQ en lugar de Redis o Kafka
- Descarto Kafka por desconocimiento del software, así que he ido a cosas conocidas
- Se requieren colas duraderas, mensajes persistentes, ingesta sin perdidas y poder leer cientos de mensajes a la vez.

## Por qué DBAL en vez de Doctrine para almacenar datos GPS
Aunque totalmente factible el uso de doctrine, al ser un punto crítico en rendimiento de la aplicación, he optado por lanzar SQL directo por DBAL ya que tendrá que enfrentarse a picos de 25.000 peticiones por segundo.


## Por qué `php-amqplib/php-amqplib`

El worker crítico de GPS no usa Symfony Messenger. Usa `php-amqplib` directamente para controlar:

- `prefetch_count`
- ACK manual tras el commit de PostgreSQL
- Flush basado en timeout
- Flush basado en tamaño de batch
- Publicación en DLQ para mensajes inválidos

## Por qué un system test
El comando `composer test:system` hace una prueba desde fuera, que aunque ya está cubierta por PHPUnit, y no la he incluido en CI, me ha sido de ayuda durante el desarrollo a pulir errores de configuración.


## Documentación de la API

La API utiliza API Platform y genera automáticamente documentación OpenAPI 3.1.0.
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

- Las alertas simplemente se almacenan, aunque podría cambiarse el listener para, por ejemplo, enviar emails
- `vehicleId` desconocido se rechaza en la API, antes de publicar en RabbitMQ.
