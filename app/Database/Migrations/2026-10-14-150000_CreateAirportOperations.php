<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAirportOperations extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public");
        $this->db->query("CREATE SCHEMA IF NOT EXISTS airport_operations");

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS airport_operations.airlines (
    airline_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    airline_code VARCHAR(10) NOT NULL UNIQUE,
    airline_name VARCHAR(150) NOT NULL,
    country VARCHAR(100) NOT NULL,
    contact VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_airline_status CHECK (status IN ('ACTIVE', 'INACTIVE'))
)
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS airport_operations.airports (
    airport_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    airport_code VARCHAR(10) NOT NULL UNIQUE,
    airport_name VARCHAR(150) NOT NULL,
    city VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    timezone VARCHAR(50) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_airport_status CHECK (status IN ('ACTIVE', 'INACTIVE'))
)
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS airport_operations.routes (
    route_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    origin_airport_id UUID NOT NULL,
    destination_airport_id UUID NOT NULL,
    distance_km NUMERIC(10,2) NULL,
    estimated_duration_minutes INTEGER NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_route_different_airport CHECK (origin_airport_id <> destination_airport_id),
    CONSTRAINT chk_route_distance CHECK (distance_km IS NULL OR distance_km > 0),
    CONSTRAINT chk_route_duration CHECK (estimated_duration_minutes IS NULL OR estimated_duration_minutes > 0),
    CONSTRAINT fk_route_origin FOREIGN KEY (origin_airport_id)
        REFERENCES airport_operations.airports(airport_id) ON DELETE RESTRICT,
    CONSTRAINT fk_route_destination FOREIGN KEY (destination_airport_id)
        REFERENCES airport_operations.airports(airport_id) ON DELETE RESTRICT
)
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE IF NOT EXISTS airport_operations.flights (
    flight_id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flight_number VARCHAR(20) NOT NULL,
    airline_id UUID NOT NULL,
    aircraft_id UUID NULL,
    route_id UUID NOT NULL,
    departure_gate_id UUID NULL,
    arrival_gate_id UUID NULL,
    scheduled_departure TIMESTAMP NOT NULL,
    estimated_departure TIMESTAMP NULL,
    actual_departure TIMESTAMP NULL,
    scheduled_arrival TIMESTAMP NOT NULL,
    estimated_arrival TIMESTAMP NULL,
    actual_arrival TIMESTAMP NULL,
    flight_status VARCHAR(30) NOT NULL DEFAULT 'SCHEDULED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_flight_schedule CHECK (scheduled_arrival > scheduled_departure),
    CONSTRAINT chk_flight_status CHECK (flight_status IN (
        'SCHEDULED', 'BOARDING', 'DELAYED', 'DEPARTED', 'ARRIVED', 'CANCELLED', 'DIVERTED'
    )),
    CONSTRAINT fk_flight_airline FOREIGN KEY (airline_id)
        REFERENCES airport_operations.airlines(airline_id) ON DELETE RESTRICT,
    CONSTRAINT fk_flight_route FOREIGN KEY (route_id)
        REFERENCES airport_operations.routes(route_id) ON DELETE RESTRICT
)
SQL);

        $this->db->query("CREATE INDEX IF NOT EXISTS idx_flights_arrival ON airport_operations.flights (scheduled_arrival)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_flights_departure ON airport_operations.flights (scheduled_departure)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_flights_status ON airport_operations.flights (flight_status)");
    }

    public function down(): void
    {
        $this->db->query('DROP SCHEMA IF EXISTS airport_operations CASCADE');
    }
}
