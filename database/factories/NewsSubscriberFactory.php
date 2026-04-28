<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\News\NewsSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewsSubscriberFactory extends Factory
{
    protected $model = NewsSubscriber::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'confirmation_token' => NewsSubscriber::generateToken(),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'ip_address' => fake()->ipv4(),
            'source' => 'news_hub',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['confirmed_at' => now()]);
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'confirmed_at' => now()->subDays(30),
            'unsubscribed_at' => now(),
        ]);
    }
}
