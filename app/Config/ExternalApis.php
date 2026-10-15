<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ExternalApis extends BaseConfig
{
    public string $placeBaseUrl;
    public string $pilotBaseUrl;

    public function __construct()
    {
        parent::__construct();

        $this->placeBaseUrl = (string) env('api.place', '');
        $this->pilotBaseUrl = (string) env('api.pilot', '');
    }
}
