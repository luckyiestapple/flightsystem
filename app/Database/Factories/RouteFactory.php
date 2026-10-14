<?php

namespace App\Database\Factories;

use Faker\Factory as FakerFactory;

final class RouteFactory
{
    public static function make(string $originAirportId, string $destinationAirportId, array $overrides = []): array
    {
        $faker = FakerFactory::create('id_ID');

        return array_merge([
            'route_id' => self::uuid(),
            'origin_airport_id' => $originAirportId,
            'destination_airport_id' => $destinationAirportId,
            'distance_km' => $faker->randomFloat(2, 100, 3000),
            'estimated_duration_minutes' => $faker->numberBetween(45, 360),
            'created_at' => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
