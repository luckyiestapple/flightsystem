<?php

namespace App\Controllers\Api;

use App\Models\AirportModel;

final class Airports extends ResourceController
{
    protected string $modelClass = AirportModel::class;
    protected string $primaryKey = 'airport_id';
    protected array $writableFields = ['airport_code', 'airport_name', 'city', 'country', 'timezone', 'status'];
    protected array $requiredFields = ['airport_code', 'airport_name', 'city', 'country', 'timezone'];
    protected array $putRequiredFields = [
        'airport_code',
        'airport_name',
        'city',
        'country',
        'timezone',
        'status',
    ];
    protected array $validationRules = [
        'airport_code' => 'max_length[10]|alpha_numeric',
        'airport_name' => 'max_length[150]',
        'city' => 'max_length[100]',
        'country' => 'max_length[100]',
        'timezone' => 'max_length[50]',
        'status' => 'in_list[ACTIVE,INACTIVE]',
    ];
    protected array $references = [
        'airport_operations.routes' => ['origin_airport_id', 'destination_airport_id'],
    ];
    protected array $uniqueFields = ['airport_code'];
    protected array $searchableFields = ['airport_code', 'airport_name', 'city', 'country', 'timezone'];
    protected array $filterableFields = ['airport_code', 'city', 'country', 'timezone', 'status'];
    protected array $sortableFields = [
        'airport_code',
        'airport_name',
        'city',
        'country',
        'timezone',
        'status',
        'created_at',
        'updated_at',
    ];
}
