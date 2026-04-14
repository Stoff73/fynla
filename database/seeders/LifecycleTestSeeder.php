<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DCPension;
use App\Models\Goal;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds 5 users, one per lifecycle campaign, for end-to-end verification.
 * Every user carries is_lifecycle_test_user = true so the engine's
 * production dispatch path skips them and the e2e command can target them
 * explicitly via test mode.
 */
class LifecycleTestSeeder extends Seeder
{
    private const PASSWORD = 'Password1!';
    private const EMAIL_DOMAIN = 'fynla.test';

    public function run(): void
    {
        // Clear any pre-existing test users (idempotent reseeds)
        User::where('is_lifecycle_test_user', true)->each(function (User $u) {
            $u->subscriptions()->delete();
            $u->properties()->delete();
            $u->dcPensions()->delete();
            $u->savingsAccounts()->delete();
            $u->investmentAccounts()->delete();
            $u->lifeInsurancePolicies()->delete();
            $u->goals()->delete();
            $u->delete();
        });

        $this->createEmptyTrialer();
        $this->createEngagedTrialer();
        $this->createCancelledTrialer();
        $this->createChurnedSubscriber();
        $this->createLapsedSubscriber();
    }

    /**
     * Helper: create a user with the requested created_at/updated_at timestamps.
     *
     * User::$guarded includes created_at/updated_at, so passing them to
     * User::create() is silently stripped. We set them directly after create
     * via the model's save() path, which skips mass-assignment protection.
     */
    private function createUser(array $attributes, Carbon $createdAt): User
    {
        $user = User::create($attributes);
        $user->created_at = $createdAt;
        $user->updated_at = $createdAt;
        $user->save();

        return $user;
    }

    private function createEmptyTrialer(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestEmpty',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-1@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(9));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'expired',
            'trial_started_at' => now()->subDays(9),
            'trial_ends_at' => now()->subDays(2),
            'data_retention_starts_at' => now()->subDays(2),
            'amount' => 0,
        ]);
    }

    private function createEngagedTrialer(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestEngaged',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-2@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(9));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'expired',
            'trial_started_at' => now()->subDays(9),
            'trial_ends_at' => now()->subDays(2),
            'data_retention_starts_at' => now()->subDays(2),
            'amount' => 0,
        ]);

        // Add module data so they qualify as engaged (not empty).
        Property::factory()->count(2)->create(['user_id' => $user->id]);
        DCPension::factory()->create(['user_id' => $user->id]);
        SavingsAccount::factory()->count(5)->create(['user_id' => $user->id]);
        Goal::factory()->count(2)->create(['user_id' => $user->id]);
    }

    private function createCancelledTrialer(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestCancelled',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-3@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(8));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'pro',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'trial_started_at' => now()->subDays(8),
            'trial_ends_at' => now()->addDays(1),  // future — cancelled BEFORE end
            'cancelled_at' => now()->subDays(3)->setTime(12, 0),
            'cancellation_reason' => 'test',
            'amount' => 0,
        ]);
    }

    private function createChurnedSubscriber(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestChurned',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-4@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(60));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'trial_started_at' => now()->subDays(60),
            'trial_ends_at' => now()->subDays(53),
            'current_period_start' => now()->subDays(53),
            'current_period_end' => now()->addDays(20),
            'cancelled_at' => now()->subDays(3)->setTime(12, 0),
            'cancellation_reason' => 'test',
            'amount' => 1099,
        ]);
    }

    private function createLapsedSubscriber(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestLapsed',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-5@' . self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'standard',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(60));

        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'past_due',
            'trial_started_at' => now()->subDays(60),
            'trial_ends_at' => now()->subDays(53),
            'current_period_start' => now()->subDays(36),
            'current_period_end' => now()->subDays(6),
            'amount' => 1099,
        ]);
    }
}
