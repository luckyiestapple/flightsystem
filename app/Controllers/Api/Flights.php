<?php

namespace App\Controllers\Api;

use App\Models\FlightModel;

final class Flights extends ResourceController
{
    protected string $modelClass = FlightModel::class;
    protected string $primaryKey = 'flight_id';
    protected array $writableFields = [
        'flight_number',
        'airline_id',
        'aircraft_id',
        'route_id',
        'departure_gate_id',
        'arrival_gate_id',
        'scheduled_departure',
        'estimated_departure',
        'actual_departure',
        'scheduled_arrival',
        'estimated_arrival',
        'actual_arrival',
        'flight_status',
    ];
    protected array $requiredFields = [
        'flight_number',
        'airline_id',
        'route_id',
        'scheduled_departure',
        'scheduled_arrival',
    ];
    protected array $putRequiredFields = [
        'flight_number',
        'airline_id',
        'route_id',
        'scheduled_departure',
        'scheduled_arrival',
        'flight_status',
    ];
    protected array $nullableFields = [
        'aircraft_id',
        'departure_gate_id',
        'arrival_gate_id',
        'estimated_departure',
        'actual_departure',
        'estimated_arrival',
        'actual_arrival',
    ];
    protected array $searchableFields = ['flight_number', 'flight_status'];
    protected array $filterableFields = [
        'flight_number',
        'airline_id',
        'aircraft_id',
        'route_id',
        'departure_gate_id',
        'arrival_gate_id',
        'flight_status',
        'scheduled_departure',
        'estimated_departure',
        'actual_departure',
        'scheduled_arrival',
        'estimated_arrival',
        'actual_arrival',
    ];
    protected array $sortableFields = [
        'flight_number',
        'flight_status',
        'scheduled_departure',
        'scheduled_arrival',
        'created_at',
        'updated_at',
    ];
    protected array $validationRules = [
        'flight_number' => 'max_length[20]',
        'aircraft_id' => 'regex_match[/^[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$/]',
        'departure_gate_id' => 'regex_match[/^[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$/]',
        'arrival_gate_id' => 'regex_match[/^[0-9a-fA-F]{8}-(?:[0-9a-fA-F]{4}-){3}[0-9a-fA-F]{12}$/]',
        'scheduled_departure' => 'valid_date[Y-m-d H:i:s]',
        'estimated_departure' => 'valid_date[Y-m-d H:i:s]',
        'actual_departure' => 'valid_date[Y-m-d H:i:s]',
        'scheduled_arrival' => 'valid_date[Y-m-d H:i:s]',
        'estimated_arrival' => 'valid_date[Y-m-d H:i:s]',
        'actual_arrival' => 'valid_date[Y-m-d H:i:s]',
        'flight_status' => 'in_list[SCHEDULED,BOARDING,DELAYED,DEPARTED,ARRIVED,CANCELLED,DIVERTED]',
    ];
    protected array $referenceFields = [
        'airline_id' => ['airport_operations.airlines', 'airline_id'],
        'route_id' => ['airport_operations.routes', 'route_id'],
    ];

    protected function recordErrors(array $payload): array
    {
        if (isset($payload['scheduled_departure'], $payload['scheduled_arrival'])) {
            $departure = strtotime($payload['scheduled_departure']);
            $arrival = strtotime($payload['scheduled_arrival']);

            if ($departure !== false && $arrival !== false && $arrival <= $departure) {
                return ['scheduled_arrival' => 'Waktu tiba terjadwal harus setelah waktu berangkat.'];
            }
        }

        return [];
    }
}
