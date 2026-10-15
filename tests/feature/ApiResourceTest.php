<?php

namespace Tests\Feature;

use Config\Database;
use Config\ExternalApis;
use Config\Services;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class ApiResourceTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testCreateRejectsMalformedJson(): void
    {
        $result = $this->withBody('{')->post('/api/airlines');

        $result->assertStatus(400);
        $result->assertJSONFragment([
            'status' => 400,
            'error' => 400,
            'messages' => ['_request' => 'Body harus berupa JSON yang valid.'],
        ]);
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $result = $this->withBody('')->post('/api/airlines');

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
            ], JSON_THROW_ON_ERROR))->post('/api/airlines');

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

    public function testCreateStoresNonUuidExternalPlaceAndPilotIds(): void
    {
        $db = $this->attachFlightsTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $routeId = '01a423ce-cc53-4c18-a522-4de8ba31b2d5';
        $placeId = 'GATE-A12';
        $pilotId = 73002;
        $db->table('airport_operations.airlines')->insert(['airline_id' => $airlineId]);
        $db->table('airport_operations.routes')->insert(['route_id' => $routeId]);

        try {
            $result = $this->withBody(json_encode([
                'flight_number' => 'GA204',
                'airline_id' => $airlineId,
                'route_id' => $routeId,
                'place_id' => $placeId,
                'pilot_id' => $pilotId,
                'scheduled_departure' => '2026-11-10 08:00:00',
                'scheduled_arrival' => '2026-11-10 10:00:00',
            ], JSON_THROW_ON_ERROR))->post('/api/flights');

            $result->assertStatus(201);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($placeId, $body['data']['place_id']);
            $this->assertSame((string) $pilotId, $body['data']['pilot_id']);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testCreateRejectsInvalidExternalPlaceAndPilotIdValues(): void
    {
        $db = $this->attachFlightsTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $routeId = '01a423ce-cc53-4c18-a522-4de8ba31b2d5';
        $db->table('airport_operations.airlines')->insert(['airline_id' => $airlineId]);
        $db->table('airport_operations.routes')->insert(['route_id' => $routeId]);

        try {
            $result = $this->withBody(json_encode([
                'flight_number' => 'GA204',
                'airline_id' => $airlineId,
                'route_id' => $routeId,
                'place_id' => ['invalid'],
                'pilot_id' => str_repeat('x', 256),
                'scheduled_departure' => '2026-11-10 08:00:00',
                'scheduled_arrival' => '2026-11-10 10:00:00',
            ], JSON_THROW_ON_ERROR))->post('/api/flights');

            $result->assertStatus(400);
            $body = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('place_id', $body['messages']);
            $this->assertArrayHasKey('pilot_id', $body['messages']);
            $this->assertSame('Field harus berupa nilai scalar.', $body['messages']['place_id']);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testAirportResourceSupportsFullCrudLifecycle(): void
    {
        $db = $this->attachAirportsTable();

        try {
            $create = $this->withBody(json_encode([
                'airport_code' => 'JKT',
                'airport_name' => 'Jakarta International Airport',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'timezone' => 'Asia/Jakarta',
            ], JSON_THROW_ON_ERROR))->post('/api/airports');

            $create->assertStatus(201);
            $airport = json_decode($create->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $airportId = $airport['airport_id'];

            $this->assertSame('JKT', $airport['airport_code']);
            $this->get('/api/airports/' . $airportId)->assertStatus(200);
            $search = $this->get('/api/search/airports?q=Jakarta&filter[status]=ACTIVE');
            $search->assertStatus(200);
            $this->assertSame(
                [$airportId],
                array_column(json_decode($search->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'], 'airport_id'),
            );

            $update = $this->withBody(json_encode([
                'airport_code' => 'JKT',
                'airport_name' => 'Jakarta International Airport',
                'city' => 'Jakarta Raya',
                'country' => 'Indonesia',
                'timezone' => 'Asia/Jakarta',
                'status' => 'ACTIVE',
            ], JSON_THROW_ON_ERROR))->put('/api/airports/' . $airportId);

            $update->assertStatus(200);
            $this->assertSame(
                'Jakarta Raya',
                json_decode($update->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['city'],
            );

            $patch = $this->withBody(json_encode([
                'status' => 'INACTIVE',
            ], JSON_THROW_ON_ERROR))->call('PATCH', '/api/airports/' . $airportId);

            $patch->assertStatus(200);
            $this->assertSame(
                'INACTIVE',
                json_decode($patch->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['status'],
            );

            $this->delete('/api/airports/' . $airportId)->assertStatus(200);
            $this->get('/api/airports/' . $airportId)->assertStatus(404);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testFlightResourceSupportsFullCrudLifecycle(): void
    {
        $db = $this->attachFlightsTable();
        $airlineId = '850a46d0-79c0-4db4-9241-51bdaea8f57d';
        $routeId = '01a423ce-cc53-4c18-a522-4de8ba31b2d5';
        $db->table('airport_operations.airlines')->insert(['airline_id' => $airlineId]);
        $db->table('airport_operations.routes')->insert(['route_id' => $routeId]);

        try {
            $create = $this->withBody(json_encode([
                'flight_number' => 'GA204',
                'airline_id' => $airlineId,
                'route_id' => $routeId,
                'scheduled_departure' => '2026-11-10 08:00:00',
                'scheduled_arrival' => '2026-11-10 10:00:00',
            ], JSON_THROW_ON_ERROR))->post('/api/flights');

            $create->assertStatus(201);
            $flight = json_decode($create->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $flightId = $flight['flight_id'];

            $this->assertSame('GA204', $flight['flight_number']);
            $this->get('/api/flights/' . $flightId)->assertStatus(200);
            $search = $this->get('/api/search/flights?q=GA204&filter[flight_status]=SCHEDULED');
            $search->assertStatus(200);
            $this->assertSame(
                [$flightId],
                array_column(json_decode($search->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'], 'flight_id'),
            );

            $update = $this->withBody(json_encode([
                'flight_number' => 'GA204',
                'airline_id' => $airlineId,
                'route_id' => $routeId,
                'scheduled_departure' => '2026-11-10 08:00:00',
                'scheduled_arrival' => '2026-11-10 10:00:00',
                'flight_status' => 'BOARDING',
            ], JSON_THROW_ON_ERROR))->put('/api/flights/' . $flightId);

            $update->assertStatus(200);
            $this->assertSame(
                'BOARDING',
                json_decode($update->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['flight_status'],
            );

            $patch = $this->withBody(json_encode([
                'flight_status' => 'DELAYED',
            ], JSON_THROW_ON_ERROR))->call('PATCH', '/api/flights/' . $flightId);

            $patch->assertStatus(200);
            $this->assertSame(
                'DELAYED',
                json_decode($patch->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data']['flight_status'],
            );

            $this->delete('/api/flights/' . $flightId)->assertStatus(200);
            $this->get('/api/flights/' . $flightId)->assertStatus(404);
        } finally {
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testPlaceProxyForwardsCollectionAndDetailRequests(): void
    {
        $apis = config(ExternalApis::class);
        $originalBaseUrl = $apis->placeBaseUrl;
        $apis->placeBaseUrl = 'http://place.test';
        $collectionResponse = $this->jsonResponse(['data' => [['id' => 'place-1']]]);
        $detailResponse = $this->jsonResponse(['data' => ['id' => 'place-1', 'name' => 'Gate A']]);
        $expectedUrls = [
            'http://place.test/api/place/',
            'http://place.test/api/places/place-1',
        ];
        $responses = [$collectionResponse, $detailResponse];
        $requestIndex = 0;
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($expectedUrls, $responses, &$requestIndex): Response {
                $this->assertSame($expectedUrls[$requestIndex], $url);

                return $responses[$requestIndex++];
            });
        Services::injectMock('curlrequest', $client);

        try {
            $collection = $this->get('/api/place/');
            $collection->assertStatus(200);
            $this->assertSame(
                ['data' => [['id' => 'place-1']]],
                json_decode($collection->getJSON(), true, 512, JSON_THROW_ON_ERROR),
            );

            $detail = $this->get('/api/places/place-1');
            $detail->assertStatus(200);
            $this->assertSame(
                ['data' => ['id' => 'place-1', 'name' => 'Gate A']],
                json_decode($detail->getJSON(), true, 512, JSON_THROW_ON_ERROR),
            );
        } finally {
            $apis->placeBaseUrl = $originalBaseUrl;
            Services::resetSingle('curlrequest');
        }
    }

    public function testPilotProxyForwardsCollectionAndDetailRequests(): void
    {
        $apis = config(ExternalApis::class);
        $originalBaseUrl = $apis->pilotBaseUrl;
        $apis->pilotBaseUrl = 'http://pilot.test';
        $collectionResponse = $this->jsonResponse(['data' => [['id' => 'pilot-1']]]);
        $detailResponse = $this->jsonResponse(['data' => ['id' => 'pilot-1', 'name' => 'Pilot Test']]);
        $expectedUrls = [
            'http://pilot.test/api/pilot',
            'http://pilot.test/api/pilot/pilot-1',
        ];
        $responses = [$collectionResponse, $detailResponse];
        $requestIndex = 0;
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($expectedUrls, $responses, &$requestIndex): Response {
                $this->assertSame($expectedUrls[$requestIndex], $url);

                return $responses[$requestIndex++];
            });
        Services::injectMock('curlrequest', $client);

        try {
            $collection = $this->get('/api/pilot');
            $collection->assertStatus(200);
            $this->assertSame(
                ['data' => [['id' => 'pilot-1']]],
                json_decode($collection->getJSON(), true, 512, JSON_THROW_ON_ERROR),
            );

            $detail = $this->get('/api/pilot/pilot-1');
            $detail->assertStatus(200);
            $this->assertSame(
                ['data' => ['id' => 'pilot-1', 'name' => 'Pilot Test']],
                json_decode($detail->getJSON(), true, 512, JSON_THROW_ON_ERROR),
            );
        } finally {
            $apis->pilotBaseUrl = $originalBaseUrl;
            Services::resetSingle('curlrequest');
        }
    }

    public function testFlightCollectionAndDetailIncludePlaceAndPilotRecords(): void
    {
        $db = $this->attachFlightsTable();
        $flightId = '4d206f9c-257d-4db2-a5ca-67a337a7cc1b';
        $placeId = 'GATE-A12';
        $pilotId = '73002';
        $db->table('airport_operations.flights')->insert([
            'flight_id' => $flightId,
            'flight_number' => 'GA204',
            'airline_id' => '850a46d0-79c0-4db4-9241-51bdaea8f57d',
            'route_id' => '01a423ce-cc53-4c18-a522-4de8ba31b2d5',
            'place_id' => $placeId,
            'pilot_id' => $pilotId,
            'scheduled_departure' => '2026-11-10 08:00:00',
            'scheduled_arrival' => '2026-11-10 10:00:00',
        ]);

        $apis = config(ExternalApis::class);
        $originalPlaceUrl = $apis->placeBaseUrl;
        $originalPilotUrl = $apis->pilotBaseUrl;
        $apis->placeBaseUrl = 'http://place.test';
        $apis->pilotBaseUrl = 'http://pilot.test';
        $client = $this->createMock(CURLRequest::class);
        $client->expects($this->exactly(4))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($placeId, $pilotId): Response {
                if ($url === 'http://place.test/api/places/' . $placeId) {
                    return $this->jsonResponse([
                        'status' => 200,
                        'data' => ['id' => $placeId, 'name' => 'Gate A'],
                    ]);
                }

                if ($url === 'http://pilot.test/api/pilot/' . $pilotId) {
                    return $this->jsonResponse([
                        'status' => 200,
                        'data' => ['id' => $pilotId, 'name' => 'Pilot Test'],
                    ]);
                }

                throw new \LogicException('Unexpected external API URL: ' . $url);
            });
        Services::injectMock('curlrequest', $client);

        try {
            $result = $this->get('/api/flights/' . $flightId);

            $result->assertStatus(200);
            $flight = json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'];
            $this->assertSame($placeId, $flight['place_id']);
            $this->assertSame(['id' => $placeId, 'name' => 'Gate A'], $flight['place']);
            $this->assertSame($pilotId, $flight['pilot_id']);
            $this->assertSame(['id' => $pilotId, 'name' => 'Pilot Test'], $flight['pilot']);

            $collection = $this->get('/api/flights');
            $collection->assertStatus(200);
            $listedFlight = json_decode($collection->getJSON(), true, 512, JSON_THROW_ON_ERROR)['data'][0];
            $this->assertSame($flightId, $listedFlight['flight_id']);
            $this->assertSame(['id' => $placeId, 'name' => 'Gate A'], $listedFlight['place']);
            $this->assertSame(['id' => $pilotId, 'name' => 'Pilot Test'], $listedFlight['pilot']);
        } finally {
            $apis->placeBaseUrl = $originalPlaceUrl;
            $apis->pilotBaseUrl = $originalPilotUrl;
            Services::resetSingle('curlrequest');
            $db->query('DETACH DATABASE airport_operations');
        }
    }

    public function testPlaceProxyReportsMissingBaseUrl(): void
    {
        $apis = config(ExternalApis::class);
        $originalBaseUrl = $apis->placeBaseUrl;
        $apis->placeBaseUrl = '';

        try {
            $result = $this->get('/api/place');

            $result->assertStatus(503);
            $this->assertSame(
                'URL API Place belum dikonfigurasi.',
                json_decode($result->getJSON(), true, 512, JSON_THROW_ON_ERROR)['messages']['_request'],
            );
        } finally {
            $apis->placeBaseUrl = $originalBaseUrl;
        }
    }

    public function testDetailRejectsInvalidUuid(): void
    {
        $result = $this->get('/api/airlines/not-a-uuid');

        $result->assertStatus(404);
        $result->assertJSONFragment([
            'status' => 404,
            'error' => 404,
            'messages' => ['_request' => 'Data tidak ditemukan.'],
        ]);
    }

    public function testCreateRejectsMissingRequiredFields(): void
    {
        $result = $this->withBody('{}')->post('/api/airlines');

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
        ], JSON_THROW_ON_ERROR))->post('/api/airlines');

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
            ], JSON_THROW_ON_ERROR))->post('/api/airlines');

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
            ], JSON_THROW_ON_ERROR))->put('/api/airlines/' . $airlineId);

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
            ], JSON_THROW_ON_ERROR))->post('/api/routes');

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
        $result = $this->delete('/api/airlines/not-a-uuid');

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
            $result = $this->delete('/api/airlines/850a46d0-79c0-4db4-9241-51bdaea8f57d');

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
            ], JSON_THROW_ON_ERROR))->put('/api/airlines/' . $airlineId);

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
            ], JSON_THROW_ON_ERROR))->put('/api/airlines/' . $airlineId);

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
            ], JSON_THROW_ON_ERROR))->call('PATCH', '/api/airlines/' . $airlineId);

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

            $result = $this->get('/api/airlines?page=1&per_page=1');

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
            $result = $this->delete('/api/airlines/' . $airlineId);

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

    private function attachFlightsTable(): BaseConnection
    {
        $db = db_connect('tests');
        $db->query("ATTACH DATABASE ':memory:' AS airport_operations");
        $db->query(
            'CREATE TABLE airport_operations.db_airlines (airline_id TEXT PRIMARY KEY)',
        );
        $db->query(
            'CREATE TABLE airport_operations.db_routes (route_id TEXT PRIMARY KEY)',
        );
        $db->query(
            'CREATE TABLE airport_operations.db_flights ('
            . 'flight_id TEXT PRIMARY KEY, flight_number TEXT NOT NULL, airline_id TEXT NOT NULL, '
            . 'aircraft_id TEXT, place_id TEXT, pilot_id TEXT, route_id TEXT NOT NULL, '
            . 'departure_gate_id TEXT, arrival_gate_id TEXT, scheduled_departure TEXT NOT NULL, '
            . 'estimated_departure TEXT, actual_departure TEXT, scheduled_arrival TEXT NOT NULL, '
            . 'estimated_arrival TEXT, actual_arrival TEXT, '
            . 'flight_status TEXT NOT NULL DEFAULT \'SCHEDULED\', '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );

        return $db;
    }

    private function attachAirportsTable(): BaseConnection
    {
        $db = db_connect('tests');
        $db->query("ATTACH DATABASE ':memory:' AS airport_operations");
        $db->query(
            'CREATE TABLE airport_operations.db_airports ('
            . 'airport_id TEXT PRIMARY KEY, airport_code TEXT NOT NULL UNIQUE, '
            . 'airport_name TEXT NOT NULL, city TEXT NOT NULL, country TEXT NOT NULL, '
            . 'timezone TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'ACTIVE\', '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
        );
        $db->query(
            'CREATE TABLE airport_operations.db_routes ('
            . 'route_id TEXT PRIMARY KEY, origin_airport_id TEXT, destination_airport_id TEXT)',
        );

        return $db;
    }

    private function jsonResponse(array $body): Response
    {
        $response = new Response(config(\Config\App::class));
        $response->setJSON($body);

        return $response;
    }

}
