<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_token' => fake()->sha256(),
            'device_id' => fake()->uuid(),
            'platform' => fake()->randomElement(['ios', 'android']),
            'device_name' => fake()->randomElement(['iPhone 15 Pro', 'Samsung Galaxy S24', 'Pixel 8']),
            'app_version' => '1.0.0',
            'os_version' => fake()->randomElement(['iOS 17.4', 'Android 14']),
            'last_used_at' => now(),
        ];
    }

    public function ios(): static
    {
        return $this->state(fn () => [
            'platform' => 'ios',
            'device_name' => 'iPhone 15 Pro',
            'os_version' => 'iOS 17.4',
        ]);
    }

    public function android(): static
    {
        return $this->state(fn () => [
            'platform' => 'android',
            'device_name' => 'Samsung Galaxy S24',
            'os_version' => 'Android 14',
        ]);
    }
}
