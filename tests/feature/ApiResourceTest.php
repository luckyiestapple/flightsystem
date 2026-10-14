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
        $result->assertJSONFragment(['message' => 'Body harus berupa JSON yang valid.']);
    }

    public function testDetailRejectsInvalidUuid(): void
    {
        $result = $this->get('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Data tidak ditemukan.']);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $result = $this->withBody('{}')->post('/api/flights/airlines');

        $result->assertStatus(422);
        $result->assertJSONFragment([
            'errors' => [
                'airline_code' => 'The airline_code field is required.',
                'airline_name' => 'The airline_name field is required.',
                'country' => 'The country field is required.',
            ],
        ]);
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
        $result->assertJSONFragment([
            'errors' => [
                'airline_code' => 'Field tidak boleh kosong.',
                'airline_name' => 'Field tidak boleh kosong.',
                'status' => 'Field tidak boleh null.',
            ],
        ]);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $result = $this->delete('/api/flights/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Data tidak ditemukan.']);
    }

    public function testDeleteReturnsNotFoundWhenRecordDoesNotExist(): void
    {
        $db = $this->attachAirlinesTable();

        try {
            $result = $this->delete('/api/flights/airlines/850a46d0-79c0-4db4-9241-51bdaea8f57d');

            $result->assertStatus(404);
            $result->assertJSONFragment(['message' => 'Data tidak ditemukan.']);
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
                'errors' => ['airline_code' => 'Field tidak boleh kosong.'],
            ]);
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
                'errors' => ['airline_name' => 'Field tidak boleh kosong.'],
            ]);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testCollectionRejectsOutOfRangePageSize(): void
    {
        $result = $this->get('/api/flights/airlines?per_page=101');

        $result->assertStatus(400);
        $result->assertJSONFragment([
            'message' => 'Parameter page harus >= 1 dan per_page harus berada di antara 1 sampai 100.',
        ]);
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
