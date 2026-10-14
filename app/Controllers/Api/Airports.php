<?php

namespace App\Controllers\Api;

use App\Models\AirportModel;

final class Airports extends ResourceController
{
    protected string $modelClass = AirportModel::class;
    protected string $primaryKey = 'airport_id';
    protected array $writableFields = ['airport_code', 'airport_name', 'city', 'country', 'timezone', 'status'];
    protected array $requiredFields = ['airport_code', 'airport_name', 'city', 'country', 'timezone'];
    protected array $validationRules = [
        'airport_code' => 'max_length[10]|alpha_numeric|is_unique[airport_operations.airports.airport_code]',
        'airport_name' => 'max_length[150]',
        'city' => 'max_length[100]',
        'country' => 'max_length[100]',
        'timezone' => 'max_length[50]',
        'status' => 'in_list[ACTIVE,INACTIVE]',
    ];
}
