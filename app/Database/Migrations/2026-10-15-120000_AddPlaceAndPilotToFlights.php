<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlaceAndPilotToFlights extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE airport_operations.flights '
            . 'ADD COLUMN IF NOT EXISTS place_id UUID NULL, '
            . 'ADD COLUMN IF NOT EXISTS pilot_id UUID NULL',
        );
    }

    public function down(): void
    {
        $this->db->query(
            'ALTER TABLE airport_operations.flights '
            . 'DROP COLUMN IF EXISTS place_id, '
            . 'DROP COLUMN IF EXISTS pilot_id',
        );
    }
}
