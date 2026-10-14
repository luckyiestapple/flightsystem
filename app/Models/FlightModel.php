<?php

namespace App\Models;

use CodeIgniter\Model;

class FlightModel extends Model
{
    protected $table = 'airport_operations.flights';
    protected $primaryKey = 'flight_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'flight_id',
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
        'updated_at',
    ];
}
