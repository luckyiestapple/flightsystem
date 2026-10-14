<?php

namespace App\Models;

use CodeIgniter\Model;

class AirlineModel extends Model
{
    protected $table = 'airport_operations.airlines';
    protected $primaryKey = 'airline_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'airline_id',
        'airline_code',
        'airline_name',
        'country',
        'contact',
        'status',
    ];
}
