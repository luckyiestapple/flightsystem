<?php

namespace Tests\Feature;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ApiResourceTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testCreateRejectsMalformedJson(): void
    {
        $result = $this->withBody('{')->post('/api/flights/airlines');

        $result->assertStatus(400);
        $result->assertJSONFragment([
            'status' => 400,
            'error' => 400,
            'messages' => ['_request' => 'Body harus berupa JSON yang valid.'],
        ]);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $result = $this->withBody('')->post('/api/flights/airlines');

        $result->assertStatus(400);
        $result->assertJSONFragment([
            'status' => 400,
            'error' => 400,
            'messages' => ['_request' => 'Body harus berupa JSON yang valid.'],
        ]);
    }

    public function testCreateUsesNumericSuccessEnvelope(): void
    {
        $db = $this->attachAirlinesTable();

        try {
            $result = $this->withBody(json_encode([
                'airline_code' => 'ZX123',
                'airline_name' => 'Airline Uji',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ], JSON_THROW_ON_ERROR))->post('/api/flights/airlines');

            $result->assertStatus(201);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(['status', 'message', 'data'], array_keys($body));
            $this->assertSame(201, $body['status']);
            $this->assertSame('Data berhasil ditambahkan.', $body['message']);
            $this->assertSame('ZX123', $body['data']['airline_code']);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testDetailRejectsInvalidUuid(): void
    {
        $result = $this->get('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment([
            'status' => 404,
            'error' => 404,
            'messages' => ['_request' => 'Data tidak ditemukan.'],
        ]);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $result = $this->withBody('{}')->post('/api/flights/airlines');

        $result->assertStatus(400);
        $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(400, $body['status']);
        $this->assertSame(400, $body['error']);
        $this->assertArrayHasKey('airline_code', $body['messages']);
        $this->assertArrayHasKey('airline_name', $body['messages']);
        $this->assertArrayHasKey('country', $body['messages']);
    }

    public function testCreateRejectsEmptyValues(): void
    {
        $result = $this->withBody(json_encode([
            'airline_code' => ' ',
            'airline_name' => '',
            'country' => 'Indonesia',
            'status' => null,
        ], JSON_THROW_ON_ERROR))->post('/api/flights/airlines');

        $result->assertStatus(400);
        $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(400, $body['status']);
        $this->assertSame(400, $body['error']);
        $this->assertSame('Field tidak boleh kosong.', $body['messages']['airline_code']);
        $this->assertSame('Field tidak boleh kosong.', $body['messages']['airline_name']);
        $this->assertSame('Field tidak boleh null.', $body['messages']['status']);
    }

    public function testCreateRejectsDuplicateAirlineCodeWithoutDatabaseConnectionError(): void
    {
        $db = $this->attachAirlinesTable();
        $db->table('airport_operations.airlines')->insert([
            'airline_id' => '850a46d0-79c0-4db4-9241-51bdaea8f57d',
            'airline_code' => 'GA',
        ]);

        try {
            $result = $this->withBody(json_encode([
                'airline_code' => 'GA',
                'airline_name' => 'Garuda Indonesia',
                'country' => 'Indonesia',
            ], JSON_THROW_ON_ERROR))->post('/api/flights/airlines');

            $result->assertStatus(400);
            $result->assertJSONFragment([
                'status' => 400,
                'error' => 400,
            ]);
            $this->assertSame(
                'Kode GA sudah digunakan.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['airline_code'],
            );
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testUpdateRejectsAirlineCodeUsedByAnotherRecord(): void
    {
        $db = $this->attachAirlinesTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $db->table('airport_operations.airlines')->insertBatch([
            [
                'airline_id' => $airlineId,
                'airline_code' => 'GA',
                'airline_name' => 'Airline Satu',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ],
            [
                'airline_id' => '01a423ce-cc53-4c18-a522-4de8ba31b2d5',
                'airline_code' => 'QZ',
                'airline_name' => 'Airline Dua',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ],
        ]);

        try {
            $result = $this->withBody(json_encode([
                'airline_code' => 'QZ',
                'airline_name' => 'Airline Satu Diubah',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ], JSON_THROW_ON_ERROR))->put('/api/flights/airlines/' . $airlineId);

            $result->assertStatus(400);
            $this->assertSame(
                'Kode QZ sudah digunakan.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['airline_code'],
            );
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testCreateRejectsUnknownAirportReferenceWithoutDatabaseConnectionError(): void
    {
        $db = $this->attachAirlinesTable();
        $db->query(
            'CREATE TABLE airport_operations.db_airports (airport_id TEXT PRIMARY KEY)',
        );

        try {
            $result = $this->withBody(json_encode([
                'origin_airport_id' => '850a46d0-79c0-4db4-9241-51bdaea8f57d',
                'destination_airport_id' => '01a423ce-cc53-4c18-a522-4de8ba31b2d5',
            ], JSON_THROW_ON_ERROR))->post('/api/flights/routes');

            $result->assertStatus(400);
            $result->assertJSONFragment([
                'status' => 400,
                'error' => 400,
            ]);
            $this->assertSame(
                'Data referensi tidak ditemukan.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['origin_airport_id'],
            );
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $result = $this->delete('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment([
            'status' => 404,
            'error' => 404,
            'messages' => ['_request' => 'Data tidak ditemukan.'],
        ]);
    }

    public function testDeleteReturnsNotFoundWhenRecordDoesNotExist(): void
    {
        $db = $this->attachAirlinesTable();

        try {
            $result = $this->delete('/api/flights/airlines/850a46d0-79c0-4db4-9241-51bdaea8f57d');

            $result->assertStatus(404);
            $result->assertJSONFragment([
                'status' => 404,
                'error' => 404,
                'messages' => ['_request' => 'Data tidak ditemukan.'],
            ]);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testUpdateRejectsEmptyRequiredValue(): void
    {
        $db = $this->attachAirlinesTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $db->table('airport_operations.airlines')->insert([
            'airline_id' => $airlineId,
            'airline_code' => 'GA',
        ]);
        try {
            $result = $this->withBody(json_encode([
                'airline_code' => '',
                'airline_name' => 'Garuda Indonesia',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ], JSON_THROW_ON_ERROR))->put('/api/flights/airlines/' . $airlineId);

            $result->assertStatus(400);
            $result->assertJSONFragment([
                'status' => 400,
                'error' => 400,
            ]);
            $this->assertSame(
                'Field tidak boleh kosong.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['airline_code'],
            );
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testUpdateUsesNumericSuccessEnvelope(): void
    {
        $db = $this->attachAirlinesTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $db->table('airport_operations.airlines')->insert([
            'airline_id' => $airlineId,
            'airline_code' => 'GA',
            'airline_name' => 'Garuda Indonesia',
            'country' => 'Indonesia',
            'status' => 'ACTIVE',
        ]);

        try {
            $result = $this->withBody(json_encode([
                'airline_code' => 'GA',
                'airline_name' => 'Garuda Indonesia Baru',
                'country' => 'Indonesia',
                'status' => 'ACTIVE',
            ], JSON_THROW_ON_ERROR))->put('/api/flights/airlines/' . $airlineId);

            $result->assertStatus(200);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(200, $body['status']);
            $this->assertSame('Data berhasil diperbarui.', $body['message']);
            $this->assertSame('Garuda Indonesia Baru', $body['data']['airline_name']);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testPatchRejectsEmptyValue(): void
    {
        $db = $this->attachAirlinesTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';

        try {
            $db->table('airport_operations.airlines')->insert([
                'airline_id' => $airlineId,
                'airline_code' => 'GA',
            ]);

            $result = $this->withBody(json_encode([
                'airline_name' => ' ',
            ], JSON_THROW_ON_ERROR))->call('PATCH', '/api/flights/airlines/' . $airlineId);

            $result->assertStatus(400);
            $result->assertJSONFragment([
                'status' => 400,
                'error' => 400,
            ]);
            $this->assertSame(
                'Field tidak boleh kosong.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['airline_name'],
            );
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testCollectionReturnsAllRowsInStandardEnvelopeWithoutPagination(): void
    {
        $db = $this->attachAirlinesTable();

        try {
            foreach (['GA', 'QZ', 'JT'] as $index => $code) {
                $db->table('airport_operations.airlines')->insert([
                    'airline_id' => sprintf('850a46d0-79c0-4db4-9241-51bdaea8f5%02d', $index + 1),
                    'airline_code' => $code,
                ]);
            }

            $result = $this->get('/api/flights/airlines?page=1&per_page=1');

            $result->assertStatus(200);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame(['status', 'message', 'data'], array_keys($body));
            $this->assertSame(200, $body['status']);
            $this->assertSame('Data berhasil diambil.', $body['message']);
            $this->assertCount(3, $body['data']);
            $this->assertArrayNotHasKey('meta', $body);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testDeleteUsesNumericSuccessEnvelopeAndConfirmsDeletedId(): void
    {
        $db = $this->attachAirlinesTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $db->table('airport_operations.airlines')->insert([
            'airline_id' => $airlineId,
            'airline_code' => 'GA',
        ]);
        $db->query(
            'CREATE TABLE airport_operations.db_flights (airline_id TEXT)',
        );

        try {
            $result = $this->delete('/api/flights/airlines/' . $airlineId);

            $result->assertStatus(200);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(200, $body['status']);
            $this->assertSame('Data dengan id ' . $airlineId . ' berhasil dihapus.', $body['message']);
            $this->assertNull($body['data']);
            $this->assertSame(0, $db->table('airport_operations.airlines')->countAllResults());
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testDatabaseConfigReadsUsernameFromEnvironmentFile(): void
    {
        $config = new Database();

        $this->assertSame(env('database.default.username'), $config->default['username']);
        $this->assertNotSame('', $config->default['username']);
        $this->assertSame('Postgre', $config->default['DBDriver']);
    }

    private function attachAirlinesTable(): BaseConnection
    {
        $db = db_connect('tests');
        $db->query("ATTACH DATABASE ':memory:' AS airport_operations");
        $db->query(
            'CREATE TABLE airport_operations.db_airlines ('
            . 'airline_id TEXT PRIMARY KEY, airline_code TEXT NOT NULL, '
            . 'airline_name TEXT NOT NULL DEFAULT \'\', country TEXT NOT NULL DEFAULT \'\', '
            . 'contact TEXT, status TEXT NOT NULL DEFAULT \'ACTIVE\')',
        );

        return $db;
    }
}
