<?php

namespace App\Database\Factories;

use Faker\Factory as FakerFactory;

final class AirlineFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = FakerFactory::create('id_ID');

        return array_merge([
            'airline_id' => self::uuid(),
            'airline_code' => strtoupper($faker->unique()->lexify('??')),
            'airline_name' => $faker->company(),
            'country' => 'Indonesia',
            'contact' => $faker->phoneNumber(),
            'status' => 'ACTIVE',
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
