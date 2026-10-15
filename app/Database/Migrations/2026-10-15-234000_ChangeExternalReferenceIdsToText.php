<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class ChangeExternalReferenceIdsToText extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
ALTER TABLE airport_operations.flights
    ALTER COLUMN place_id TYPE VARCHAR(255) USING place_id::text,
    ALTER COLUMN pilot_id TYPE VARCHAR(255) USING pilot_id::text
SQL);
    }

    public function down(): void
    {
        $result = $this->db->query(<<<'SQL'
SELECT COUNT(*) AS invalid_ids
FROM airport_operations.flights
WHERE (place_id IS NOT NULL AND place_id !~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
   OR (pilot_id IS NOT NULL AND pilot_id !~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$')
SQL);

        if ((int) $result->getRow()->invalid_ids > 0) {
            throw new RuntimeException(
                'Cannot revert external reference IDs to UUID while non-UUID Place or Pilot IDs exist.',
            );
        }

        $this->db->query(<<<'SQL'
ALTER TABLE airport_operations.flights
    ALTER COLUMN place_id TYPE UUID USING place_id::uuid,
    ALTER COLUMN pilot_id TYPE UUID USING pilot_id::uuid
SQL);
    }
}
