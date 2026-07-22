<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one user per paid lifecycle campaign for end-to-end verification.
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
        // Clear any pre-existing test users (idempotent reseeds). User uses
        // SoftDeletes, so we include trashed rows in the lookup and use
        // forceDelete() to fully remove them — otherwise the unique email
        // constraint would block a second run.
        User::withTrashed()
            ->where('is_lifecycle_test_user', true)
            ->each(function (User $u) {
                $u->subscriptions()->withTrashed()->forceDelete();
                $u->properties()->withTrashed()->forceDelete();
                $u->dcPensions()->withTrashed()->forceDelete();
                $u->savingsAccounts()->withTrashed()->forceDelete();
                $u->investmentAccounts()->withTrashed()->forceDelete();
                $u->lifeInsurancePolicies()->withTrashed()->forceDelete();
                $u->goals()->withTrashed()->forceDelete();
                $u->forceDelete();
            });

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

    private function createChurnedSubscriber(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestChurned',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-4@'.self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'free',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(60));

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'cancelled',
            'current_period_start' => now()->subDays(53),
            'current_period_end' => now()->addDays(20),
            'cancelled_at' => now()->subDays(3)->setTime(12, 0),
            'cancellation_reason' => 'test',
            'amount' => 1099,
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'completed',
        ]);
    }

    private function createLapsedSubscriber(): void
    {
        $user = $this->createUser([
            'first_name' => 'TestLapsed',
            'surname' => 'User',
            'email' => 'lifecycle-e2e-5@'.self::EMAIL_DOMAIN,
            'password' => Hash::make(self::PASSWORD),
            'plan' => 'standard',
            'is_lifecycle_test_user' => true,
        ], now()->subDays(60));

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'past_due',
            'current_period_start' => now()->subDays(36),
            'current_period_end' => now()->subDays(6),
            'amount' => 1099,
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'completed',
        ]);
    }
}
