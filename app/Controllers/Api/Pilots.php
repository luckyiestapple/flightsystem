<?php

namespace App\Controllers\Api;

use Config\ExternalApis;

final class Pilots extends ExternalResourceController
{
    protected string $serviceName = 'Pilot';
    protected string $collectionPath = 'api/pilots';
    protected string $detailPath = 'api/pilots/';

    public function __construct()
    {
        $this->baseUrl = config(ExternalApis::class)->pilotBaseUrl;
    }
}
