<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ApiResourceTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testCreateRejectsMalformedJson(): void
    {
        $result = $this->withBody('{')->post('/api/v1/airlines');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Body harus berupa JSON yang valid.']);
    }

    public function testDetailRejectsInvalidUuid(): void
    {
        $result = $this->get('/api/v1/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Data tidak ditemukan.']);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $result = $this->withBody('{}')->post('/api/v1/airlines');

        $result->assertStatus(422);
        $result->assertJSONFragment([
            'errors' => [
                'airline_code' => 'The airline_code field is required.',
                'airline_name' => 'The airline_name field is required.',
                'country' => 'The country field is required.',
            ],
        ]);
    }

    public function testCollectionRejectsOutOfRangePageSize(): void
    {
        $result = $this->get('/api/v1/airlines?per_page=101');

        $result->assertStatus(400);
        $result->assertJSONFragment([
            'message' => 'Parameter page harus >= 1 dan per_page harus berada di antara 1 sampai 100.',
        ]);
    }
}
