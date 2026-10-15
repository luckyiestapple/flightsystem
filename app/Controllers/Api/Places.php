<?php

namespace App\Controllers\Api;

use Config\ExternalApis;

final class Places extends ExternalResourceController
{
    protected string $serviceName = 'Place';
    protected string $collectionPath = 'api/place/';
    protected string $detailPath = 'api/places/';

    public function __construct()
    {
        $this->baseUrl = config(ExternalApis::class)->placeBaseUrl;
    }
}
