<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NativeDeviceSession;
use App\Models\NativeRefreshToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NativeRefreshToken>
 */
class NativeRefreshTokenFactory extends Factory
{
    protected $model = NativeRefreshToken::class;

    public function definition(): array
    {
        return [
            'native_device_session_id' => NativeDeviceSession::factory(),
            'token_hash' => hash('sha256', random_bytes(32)),
            'expires_at' => now()->addDays(30),
            'used_at' => null,
            'revoked_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (): array => ['used_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
