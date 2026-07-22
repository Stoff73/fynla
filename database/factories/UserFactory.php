<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional(0.3)->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'marital_status' => 'single',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Mark the user as a preview persona. Preview users are seeded test
     * personas isolated from real users by `is_preview_user = true`;
     * PreviewWriteInterceptor blocks all writes from this flag.
     */
    public function preview(): static
    {
        return $this->state(fn () => [
            'is_preview_user' => true,
        ]);
    }

    /**
     * Mark the user as an advisor. Replaces the DB::table()->update workaround
     * that AdvisorClientSeeder used to bypass the User model's $guarded array.
     */
    public function advisor(): static
    {
        return $this->state(fn () => [
            'is_advisor' => true,
        ]);
    }

    /**
     * Give the user a live canonical Revolut Premium grant.
     */
    public function withActivePremiumSubscription(): static
    {
        return $this->state(fn () => [
            'plan' => 'premium',
            'tier' => 'premium',
        ])->afterCreating(function (User $user): void {
            Subscription::factory()->plan('premium')->create([
                'user_id' => $user->id,
                'status' => 'active',
                'auto_renew' => true,
                'current_period_end' => now()->addMonth(),
            ]);
        });
    }
}
