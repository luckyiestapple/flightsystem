<?php

namespace App\Controllers\Api;

use Config\ExternalApis;

final class Pilots extends ExternalResourceController
{
    protected string $serviceName = 'Pilot';
    protected string $collectionPath = 'api/pilot';
    protected string $detailPath = 'api/pilot/';

    public function __construct()
    {
        $this->baseUrl = config(ExternalApis::class)->pilotBaseUrl;
    }
}
