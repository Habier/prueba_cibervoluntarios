#!/usr/bin/env node

/**
 * System test for GPS Worker API
 * Runs against fully-provisioned Docker stack with real processes
 * 
 * Tests:
 * - 300 realistic GPS coordinates sent via HTTP
 * - RabbitMQ consumes all messages
 * - worker-gps processes and persists to database
 * - At least one alert of each type generated
 * - Queues drained and empty
 * 
 * Requirements:
 * - Node.js 18+
 * - Docker stack running (app, database, rabbitmq, worker-gps)
 * - `npm install pg` (PostgreSQL client)
 */

import pg from 'pg';
import http from 'http';
import https from 'https';

const { Client } = pg;

// ============================================================================
// Configuration
// ============================================================================

const config = {
  app: {
    host: process.env.APP_HOST || 'localhost',
    port: parseInt(process.env.APP_PORT || '8081', 10),
    useHttps: process.env.APP_USE_HTTPS === 'true',
  },
  database: {
    host: process.env.DB_HOST || 'localhost',
    port: parseInt(process.env.DB_PORT || '55432', 10),
    database: process.env.DB_NAME || 'app',
    user: process.env.DB_USER || 'app',
    password: process.env.DB_PASSWORD || 'app',
  },
  rabbitmq: {
    host: process.env.RABBITMQ_HOST || 'localhost',
    port: parseInt(process.env.RABBITMQ_PORT || '15672', 10),
    user: process.env.RABBITMQ_USER || 'guest',
    password: process.env.RABBITMQ_PASSWORD || 'guest',
    queue: process.env.RABBITMQ_QUEUE || 'gps.coordinates.queue',
    dlqQueue: process.env.RABBITMQ_DLQ_QUEUE || 'gps.coordinates.queue.dlq',
  },
  test: {
    totalCoordinates: 300,
    coordinatesPerRequest: 20,
    pollingIntervalMs: 1000,
    maxWaitSeconds: 30,
    runId: `sys-${Date.now()}`,
  },
};

// ============================================================================
// Utility Functions
// ============================================================================

function log(level, message, data) {
  const timestamp = new Date().toISOString();
  const prefix = `[${timestamp}] [${level}]`;
  if (data) {
    console.log(`${prefix} ${message}`, data);
  } else {
    console.log(`${prefix} ${message}`);
  }
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function toPhpAtom(date) {
  const iso = date.toISOString();
  return iso.replace('.000Z', '+00:00');
}

async function makeHttpRequest(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const httpModule = config.app.useHttps ? https : http;
    const options = {
      hostname: config.app.host,
      port: config.app.port,
      path,
      method,
      headers: {
        'Content-Type': 'application/json',
      },
      rejectUnauthorized: false, // Allow self-signed certs
    };

    const req = httpModule.request(options, (res) => {
      // Handle redirects
      if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
        resolve(makeHttpRequest(method, res.headers.location, body));
        return;
      }

      let data = '';
      res.on('data', (chunk) => {
        data += chunk;
      });
      res.on('end', () => {
        try {
          resolve({
            statusCode: res.statusCode,
            body: data ? JSON.parse(data) : null,
          });
        } catch (error) {
          resolve({
            statusCode: res.statusCode,
            body: data,
          });
        }
      });
    });

    req.on('error', reject);

    if (body) {
      req.write(JSON.stringify(body));
    }
    req.end();
  });
}

async function getDatabaseClient() {
  const client = new Client(config.database);
  await client.connect();
  return client;
}

async function queryRabbitMqQueue(queueName) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: config.rabbitmq.host,
      port: config.rabbitmq.port,
      path: `/api/queues/%2F/${encodeURIComponent(queueName)}`,
      method: 'GET',
      auth: `${config.rabbitmq.user}:${config.rabbitmq.password}`,
    };

    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => {
        data += chunk;
      });
      res.on('end', () => {
        try {
          const queueInfo = JSON.parse(data);
          resolve(queueInfo.messages || 0);
        } catch (error) {
          reject(error);
        }
      });
    });

    req.on('error', reject);
    req.end();
  });
}

// ============================================================================
// Test Scenario Generation
// ============================================================================

function generateTestCoordinates() {
  const coordinates = [];

  for (let i = 0; i < config.test.totalCoordinates; i++) {
    const scenario = Math.floor(i / 100); // Divide into three 100-blocks
    let speedKmh = 78.0;
    let latitude = 40.4168 + ((i % 10) * 0.001);
    let longitude = -3.7038 - ((i % 10) * 0.001);

    // Generate different alert scenarios
    if (scenario === 0) {
      // Scenario 1: Speed exceeded
      speedKmh = (i % 50) === 0 ? 132.0 : 88.0 + (i % 12);
    } else if (scenario === 1) {
      // Scenario 2: Geofence breach
      latitude = 40.2 + ((i % 10) * 0.001); // Outside bounds
    } else {
      // Scenario 3: Idle
      speedKmh = 0.1 + ((i % 5) * 0.08); // Below idle threshold
    }

    const deviceTimestamp = new Date('2026-01-02T08:00:00Z');
    deviceTimestamp.setSeconds(deviceTimestamp.getSeconds() + i);

    coordinates.push({
      externalId: `${config.test.runId}-${String(i).padStart(3, '0')}`,
      vehicleId:
        i % 3 === 0
          ? '88888888-8888-4888-8888-888888888888'
          : i % 3 === 1
            ? '99999999-9999-4999-8999-999999999999'
            : 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      latitude,
      longitude,
      altitude: 20 + (i % 50),
      speedKmh,
      accuracy: 3 + (i % 4),
      deviceTimestamp: toPhpAtom(deviceTimestamp),
    });
  }

  return coordinates;
}

// ============================================================================
// Test Execution
// ============================================================================

async function cleanupDatabase(client) {
  log('INFO', 'Cleaning up test database...');
  await client.query('DELETE FROM alerts WHERE created_at >= NOW() - INTERVAL \'1 day\'');
  await client.query(
    'DELETE FROM gps_coordinates WHERE external_id LIKE $1',
    [`${config.test.runId}-%`]
  );
  log('INFO', 'Database cleanup complete');
}

async function readAlertBaseline(client) {
  const result = await client.query(
    `SELECT t.code, COUNT(*)::int AS count
     FROM alert_types t
     LEFT JOIN alerts a ON a.alert_type_id = t.id
     GROUP BY t.code
     ORDER BY t.code`
  );

  return Object.fromEntries(result.rows.map((row) => [row.code, row.count]));
}

async function cleanupRabbitMq() {
  log('INFO', 'Purging RabbitMQ queues...');

  // Note: Full queue purge via management API requires admin permissions
  // For now, we just check depth; actual purge happens during consumer processing
  log('INFO', 'RabbitMQ queue state will be verified via polling');
}

async function sendTestCoordinates(coordinates) {
  log('INFO', `Sending ${coordinates.length} GPS coordinates in batches...`);

  const coordinatesPerRequest = config.test.coordinatesPerRequest;
  const requestCount = Math.ceil(
    coordinates.length / coordinatesPerRequest
  );
  let accepted = 0;

  for (let i = 0; i < requestCount; i++) {
    const start = i * coordinatesPerRequest;
    const end = Math.min(
      start + coordinatesPerRequest,
      coordinates.length
    );
    const batchCoords = coordinates.slice(start, end);

    const response = await makeHttpRequest(
      'POST',
      '/api/gps-coordinates/batch',
      { coordinates: batchCoords }
    );

    if (response.statusCode !== 202) {
      throw new Error(
        `HTTP ${response.statusCode}: Expected 202 Accepted, got ${JSON.stringify(response.body)}`
      );
    }

    accepted += response.body.accepted || 0;
    log('DEBUG', `Batch ${i + 1}/${requestCount} sent (${response.body.accepted} coordinates accepted)`);
  }

  log('INFO', `All coordinates sent: ${accepted}/${coordinates.length} accepted`);
  return accepted;
}

async function pollForCompletion(client, alertBaseline) {
  log('INFO', 'Polling for worker completion...');

  const maxWaitMs = config.test.maxWaitSeconds * 1000;
  const startTime = Date.now();
  const pollIntervalMs = config.test.pollingIntervalMs;

  let lastState = null;

  while (Date.now() - startTime < maxWaitMs) {
    const coordinatesProcessed = await client.query(
      'SELECT COUNT(*) as count FROM gps_coordinates WHERE external_id LIKE $1',
      [`${config.test.runId}-%`]
    );

    const alertCounts = await readAlertBaseline(client);

    const mainQueueDepth = await queryRabbitMqQueue(config.rabbitmq.queue);
    const dlqDepth = await queryRabbitMqQueue(config.rabbitmq.dlqQueue);

    const state = {
      coordinatesProcessed: parseInt(coordinatesProcessed.rows[0].count, 10),
      speedExceededAlerts: (alertCounts.SPEED_EXCEEDED ?? 0) - (alertBaseline.SPEED_EXCEEDED ?? 0),
      geofenceAlerts: (alertCounts.GEOFENCE_BREACH ?? 0) - (alertBaseline.GEOFENCE_BREACH ?? 0),
      idleAlerts: (alertCounts.IDLE_TOO_LONG ?? 0) - (alertBaseline.IDLE_TOO_LONG ?? 0),
      mainQueueDepth,
      dlqDepth,
      elapsedSeconds: ((Date.now() - startTime) / 1000).toFixed(1),
    };

    if (lastState === null || JSON.stringify(lastState) !== JSON.stringify(state)) {
      log('DEBUG', 'Poll state update:', state);
      lastState = state;
    }

    // Check if all conditions are met
    if (
      state.coordinatesProcessed === config.test.totalCoordinates &&
      state.speedExceededAlerts > 0 &&
      state.geofenceAlerts > 0 &&
      state.idleAlerts > 0 &&
      state.mainQueueDepth === 0 &&
      state.dlqDepth === 0
    ) {
      log(
        'INFO',
        'Test completion criteria met!',
        state
      );
      return state;
    }

    await sleep(pollIntervalMs);
  }

  throw new Error(
    `Timeout waiting for completion after ${config.test.maxWaitSeconds}s. Last state: ${JSON.stringify(lastState)}`
  );
}

async function runSystemTest() {
  log('INFO', '='.repeat(80));
  log('INFO', 'GPS Worker System Test');
  log('INFO', `Run ID: ${config.test.runId}`);
  log('INFO', '='.repeat(80));

  let client;

  try {
    // 1. Connect to database
    log('INFO', 'Connecting to database...');
    client = await getDatabaseClient();
    log('INFO', 'Database connected');

    // 2. Cleanup
    await cleanupDatabase(client);
    await cleanupRabbitMq();
    const alertBaseline = await readAlertBaseline(client);
    log('INFO', 'Alert baseline captured', alertBaseline);

    // 3. Generate test data
    const coordinates = generateTestCoordinates();
    log('INFO', `Generated ${coordinates.length} test coordinates`);

    // 4. Send coordinates
    const sent = await sendTestCoordinates(coordinates);
    if (sent !== coordinates.length) {
      throw new Error(
        `Not all coordinates were accepted: ${sent}/${coordinates.length}`
      );
    }

    // 5. Poll for completion
    const finalState = await pollForCompletion(client, alertBaseline);

    // 6. Report results
    log('INFO', '='.repeat(80));
    log('INFO', 'TEST PASSED ✓');
    log('INFO', '='.repeat(80));
    log('INFO', 'Final Results:', finalState);
    log('INFO', `  Coordinates processed: ${finalState.coordinatesProcessed}/${config.test.totalCoordinates}`);
    log('INFO', `  Speed exceeded alerts: ${finalState.speedExceededAlerts}`);
    log('INFO', `  Geofence breach alerts: ${finalState.geofenceAlerts}`);
    log('INFO', `  Idle too long alerts: ${finalState.idleAlerts}`);
    log('INFO', `  Main queue depth: ${finalState.mainQueueDepth}`);
    log('INFO', `  DLQ depth: ${finalState.dlqDepth}`);
    log('INFO', `  Total elapsed: ${finalState.elapsedSeconds}s`);

    process.exit(0);
  } catch (error) {
    log('ERROR', 'Test failed:', error.message);
    log('ERROR', '='.repeat(80));
    process.exit(1);
  } finally {
    if (client) {
      await client.end();
    }
  }
}

// ============================================================================
// Run Test
// ============================================================================

runSystemTest().catch((error) => {
  log('ERROR', 'Unexpected error:', error);
  process.exit(1);
});
