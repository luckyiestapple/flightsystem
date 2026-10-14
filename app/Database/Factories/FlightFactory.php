<?php

namespace App\Database\Factories;

use Faker\Factory as FakerFactory;

final class FlightFactory
{
    public static function make(string $airlineId, string $routeId, array $overrides = []): array
    {
        $faker = FakerFactory::create('id_ID');
        $departure = $faker->dateTimeBetween('now', '+30 days');
        $arrival = (clone $departure)->modify('+' . $faker->numberBetween(60, 240) . ' minutes');

        return array_merge([
            'flight_id' => self::uuid(),
            'flight_number' => strtoupper($faker->randomElement(['GA', 'QZ', 'JT', 'ID', 'SQ'])) . $faker->numberBetween(100, 9999),
            'airline_id' => $airlineId,
            'aircraft_id' => self::uuid(),
            'route_id' => $routeId,
            'departure_gate_id' => null,
            'arrival_gate_id' => null,
            'scheduled_departure' => $departure->format('Y-m-d H:i:s'),
            'estimated_departure' => null,
            'actual_departure' => null,
            'scheduled_arrival' => $arrival->format('Y-m-d H:i:s'),
            'estimated_arrival' => null,
            'actual_arrival' => null,
            'flight_status' => 'SCHEDULED',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
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
