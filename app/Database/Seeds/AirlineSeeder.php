<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AirlineSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
    ['airline_id' => 'd3c30c43-4fc1-4fb8-842b-412e15607eec', 'airline_code' => 'GA', 'airline_name' => 'Garuda Indonesia', 'country' => 'Indonesia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:05:08.921585'],
    ['airline_id' => 'f2465d65-6253-41c4-8519-896805bd6fcb', 'airline_code' => 'QZ', 'airline_name' => 'Indonesia AirAsia', 'country' => 'Indonesia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:05:08.921585'],
    ['airline_id' => 'eb640017-6364-4b2a-843c-2b3dc11a27c5', 'airline_code' => 'SQ', 'airline_name' => 'Singapore Airlines', 'country' => 'Singapore', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:22:26.895338'],
    ['airline_id' => '1a59bfb0-8125-4ed4-89e2-9e43e4a3e928', 'airline_code' => 'JT', 'airline_name' => 'Lion Air', 'country' => 'Indonesia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:05:08.921585'],
    ['airline_id' => 'cec0a454-1b2b-4289-95f4-f53395ab2014', 'airline_code' => 'ID', 'airline_name' => 'Batik Air', 'country' => 'Indonesia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:05:08.921585'],
    ['airline_id' => '17ba877b-a2a4-4417-bf40-e2d9f7cb5d0a', 'airline_code' => 'OD', 'airline_name' => 'Batik Air Malaysia', 'country' => 'Malaysia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:51:22.29119'],
    ['airline_id' => 'ec7c494b-0be7-46fa-8e8a-d481acdd5d5f', 'airline_code' => 'IU', 'airline_name' => 'Super Air Jet', 'country' => 'Indonesia', 'contact' => null, 'status' => 'ACTIVE', 'created_at' => '2026-09-23 00:51:22.29119'],
];

        if ($rows !== []) {
            $this->db->table('airport_operations.airlines')->insertBatch($rows, 100);
        }
    }
}
