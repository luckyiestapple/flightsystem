<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('AirlineSeeder');
        $this->call('AirportSeeder');
        $this->call('RouteSeeder');
        $this->call('FlightSeeder');
    }
}
