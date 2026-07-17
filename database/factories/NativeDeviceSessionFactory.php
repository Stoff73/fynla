<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NativeDeviceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NativeDeviceSession>
 */
class NativeDeviceSessionFactory extends Factory
{
    protected $model = NativeDeviceSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'ios',
            'device_label' => fake()->randomElement(['iPhone', 'iPhone 11']),
            'app_version' => '1.0.0',
            'app_build' => '1',
            'current_access_token_id' => null,
            'authenticated_at' => now(),
            'absolute_expires_at' => now()->addDays(90),
            'last_used_at' => now(),
            'revoked_at' => null,
            'revoke_reason' => null,
        ];
    }

    public function revoked(string $reason = 'test_revocation'): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ]);
    }
}
