<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates logistics master data, GPS hypertable, projections, and alert tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS postgis');
        $this->addSql('CREATE EXTENSION IF NOT EXISTS timescaledb');

        $this->addSql('CREATE TABLE vehicle_types (id UUID NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VEHICLE_TYPES_CODE ON vehicle_types (code)');
        $this->addSql('CREATE TABLE alert_types (id UUID NOT NULL, code VARCHAR(100) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, active BOOLEAN NOT NULL, default_severity VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ALERT_TYPES_CODE ON alert_types (code)');
        $this->addSql('CREATE TABLE vehicles (id UUID NOT NULL, vehicle_type_id UUID NOT NULL, plate VARCHAR(32) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_VEHICLES_PLATE ON vehicles (plate)');
        $this->addSql('CREATE INDEX IDX_VEHICLES_VEHICLE_TYPE_ID ON vehicles (vehicle_type_id)');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_VEHICLES_VEHICLE_TYPE FOREIGN KEY (vehicle_type_id) REFERENCES vehicle_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE vehicle_last_positions (vehicle_id UUID NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, altitude DOUBLE PRECISION DEFAULT NULL, speed_kmh DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION DEFAULT NULL, device_timestamp TIMESTAMP(0) WITH TIME ZONE NOT NULL, received_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(vehicle_id))');
        $this->addSql('CREATE INDEX IDX_VEHICLE_LAST_POSITIONS_VEHICLE_ID ON vehicle_last_positions (vehicle_id)');
        $this->addSql('ALTER TABLE vehicle_last_positions ADD CONSTRAINT FK_VLP_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE alerts (id UUID NOT NULL, vehicle_id UUID NOT NULL, alert_type_id UUID NOT NULL, message TEXT NOT NULL, severity VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ALERTS_VEHICLE_CREATED_AT ON alerts (vehicle_id, created_at DESC)');
        $this->addSql('ALTER TABLE alerts ADD CONSTRAINT FK_ALERTS_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE alerts ADD CONSTRAINT FK_ALERTS_ALERT_TYPE FOREIGN KEY (alert_type_id) REFERENCES alert_types (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE gps_coordinates (id UUID NOT NULL, external_id VARCHAR(255) DEFAULT NULL, vehicle_id UUID NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, altitude DOUBLE PRECISION DEFAULT NULL, speed_kmh DOUBLE PRECISION NOT NULL, accuracy DOUBLE PRECISION DEFAULT NULL, device_timestamp TIMESTAMP(0) WITH TIME ZONE NOT NULL, received_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, location geometry(Point, 4326) GENERATED ALWAYS AS (ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)) STORED, PRIMARY KEY(id, device_timestamp))');
        $this->addSql('CREATE TABLE gps_coordinate_ingestion_keys (coordinate_id UUID NOT NULL, vehicle_id UUID NOT NULL, external_id VARCHAR(255) DEFAULT NULL, device_timestamp TIMESTAMP(0) WITH TIME ZONE NOT NULL, latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, PRIMARY KEY(coordinate_id))');
        $this->addSql('ALTER TABLE gps_coordinates ADD CONSTRAINT FK_GPS_COORDINATES_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE gps_coordinate_ingestion_keys ADD CONSTRAINT FK_GPS_INGESTION_KEYS_VEHICLE FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_gps_ingestion_keys_vehicle_external_id ON gps_coordinate_ingestion_keys (vehicle_id, external_id) WHERE external_id IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_gps_ingestion_keys_vehicle_natural ON gps_coordinate_ingestion_keys (vehicle_id, device_timestamp, latitude, longitude) WHERE external_id IS NULL');
        $this->addSql('CREATE INDEX idx_gps_coordinates_vehicle_timestamp_desc ON gps_coordinates (vehicle_id, device_timestamp DESC)');
        $this->addSql('CREATE INDEX idx_gps_coordinates_timestamp_desc ON gps_coordinates (device_timestamp DESC)');
        $this->addSql("SELECT create_hypertable('gps_coordinates', 'device_timestamp', if_not_exists => TRUE, migrate_data => TRUE)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS gps_coordinate_ingestion_keys');
        $this->addSql('DROP TABLE IF EXISTS gps_coordinates');
        $this->addSql('DROP TABLE IF EXISTS alerts');
        $this->addSql('DROP TABLE IF EXISTS vehicle_last_positions');
        $this->addSql('DROP TABLE IF EXISTS vehicles');
        $this->addSql('DROP TABLE IF EXISTS alert_types');
        $this->addSql('DROP TABLE IF EXISTS vehicle_types');
    }
}
