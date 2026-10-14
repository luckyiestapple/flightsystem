<?php

namespace App\Models;

use CodeIgniter\Model;

class AirportModel extends Model
{
    protected $table = 'airport_operations.airports';
    protected $primaryKey = 'airport_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'airport_id',
        'airport_code',
        'airport_name',
        'city',
        'country',
        'timezone',
        'status',
        'updated_at',
    ];
}
