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
            'status' => 'error',
            'data' => null,
            'message' => 'Body harus berupa JSON yang valid.',
        ]);
    }

    public function testDetailRejectsInvalidUuid(): void
    {
        $result = $this->get('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment([
            'status' => 'error',
            'data' => null,
            'message' => 'Data tidak ditemukan.',
        ]);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $result = $this->withBody('{}')->post('/api/flights/airlines');

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('error', $body['status']);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('airline_code:', $body['message']);
        $this->assertStringContainsString('airline_name:', $body['message']);
        $this->assertStringContainsString('country:', $body['message']);
    }

    public function testCreateRejectsEmptyValues(): void
    {
        $result = $this->withBody(json_encode([
            'airline_code' => ' ',
            'airline_name' => '',
            'country' => 'Indonesia',
            'status' => null,
        ], JSON_THROW_ON_ERROR))->post('/api/flights/airlines');

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('error', $body['status']);
        $this->assertNull($body['data']);
        $this->assertStringContainsString('airline_code: Field tidak boleh kosong.', $body['message']);
        $this->assertStringContainsString('airline_name: Field tidak boleh kosong.', $body['message']);
        $this->assertStringContainsString('status: Field tidak boleh null.', $body['message']);
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

            $result->assertStatus(422);
            $result->assertJSONFragment([
                'status' => 'error',
                'data' => null,
            ]);
            $this->assertStringContainsString('airline_code: Nilai field harus unik.', $result->getJSON());
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

            $result->assertStatus(422);
            $result->assertJSONFragment([
                'status' => 'error',
                'data' => null,
            ]);
            $this->assertStringContainsString('origin_airport_id: Data referensi tidak ditemukan.', $result->getJSON());
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $result = $this->delete('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment([
            'status' => 'error',
            'data' => null,
            'message' => 'Data tidak ditemukan.',
        ]);
    }

    public function testDeleteReturnsNotFoundWhenRecordDoesNotExist(): void
    {
        $db = $this->attachAirlinesTable();

        try {
            $result = $this->delete('/api/flights/airlines/850a46d0-79c0-4db4-9241-51bdaea8f57d');

            $result->assertStatus(404);
            $result->assertJSONFragment([
                'status' => 'error',
                'data' => null,
                'message' => 'Data tidak ditemukan.',
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

            $result->assertStatus(422);
            $result->assertJSONFragment([
                'status' => 'error',
                'data' => null,
            ]);
            $this->assertStringContainsString('airline_code: Field tidak boleh kosong.', $result->getJSON());
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

            $result->assertStatus(422);
            $result->assertJSONFragment([
                'status' => 'error',
                'data' => null,
            ]);
            $this->assertStringContainsString('airline_name: Field tidak boleh kosong.', $result->getJSON());
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

            $this->assertSame(['status', 'data', 'message'], array_keys($body));
            $this->assertSame('success', $body['status']);
            $this->assertNull($body['message']);
            $this->assertCount(3, $body['data']);
            $this->assertArrayNotHasKey('meta', $body);
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
            . 'airline_id TEXT PRIMARY KEY, airline_code TEXT NOT NULL)',
        );

        return $db;
    }
}
