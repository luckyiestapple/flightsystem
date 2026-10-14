<?php

namespace App\Controllers\Api;

use App\Models\RouteModel;

final class Routes extends ResourceController
{
    protected string $modelClass = RouteModel::class;
    protected string $primaryKey = 'route_id';
    protected array $writableFields = [
        'origin_airport_id',
        'destination_airport_id',
        'distance_km',
        'estimated_duration_minutes',
    ];
    protected array $requiredFields = ['origin_airport_id', 'destination_airport_id'];
    protected array $validationRules = [
        'origin_airport_id' => 'is_not_unique[airport_operations.airports.airport_id]',
        'destination_airport_id' => 'is_not_unique[airport_operations.airports.airport_id]',
        'distance_km' => 'decimal|greater_than[0]',
        'estimated_duration_minutes' => 'integer|greater_than[0]',
    ];

    protected function recordErrors(array $payload): array
    {
        if (
            isset($payload['origin_airport_id'], $payload['destination_airport_id'])
            && $payload['origin_airport_id'] === $payload['destination_airport_id']
        ) {
            return ['destination_airport_id' => 'Bandara asal dan tujuan harus berbeda.'];
        }

        return [];
    }
}
