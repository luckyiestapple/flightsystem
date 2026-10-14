<?php

namespace App\Controllers\Api;

use App\Models\AirlineModel;

final class Airlines extends ResourceController
{
    protected string $modelClass = AirlineModel::class;
    protected string $primaryKey = 'airline_id';
    protected array $writableFields = ['airline_code', 'airline_name', 'country', 'contact', 'status'];
    protected array $requiredFields = ['airline_code', 'airline_name', 'country'];
    protected array $putRequiredFields = ['airline_code', 'airline_name', 'country', 'status'];
    protected array $nullableFields = ['contact'];
    protected array $validationRules = [
        'airline_code' => 'max_length[10]|alpha_numeric|is_unique[airport_operations.airlines.airline_code]',
        'airline_name' => 'max_length[150]',
        'country' => 'max_length[100]',
        'contact' => 'max_length[100]',
        'status' => 'in_list[ACTIVE,INACTIVE]',
    ];
    protected array $references = [
        'airport_operations.flights' => ['airline_id'],
    ];
}
