<?php

namespace App\Models;

use CodeIgniter\Model;

class RouteModel extends Model
{
    protected $table = 'airport_operations.routes';
    protected $primaryKey = 'route_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'route_id',
        'origin_airport_id',
        'destination_airport_id',
        'distance_km',
        'estimated_duration_minutes',
    ];
}
