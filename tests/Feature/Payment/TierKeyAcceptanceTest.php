<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);
});

describe('paid plan validation', function () {
    it('accepts Premium for a new payment order', function () {
        $user = User::factory()->create([
            'tier' => 'free',
            'revolut_customer_id' => 'cust_premium_validation',
        ]);

        Http::fake([
            '*' => Http::response([
                'id' => 'order_premium_validation',
                'token' => 'token_premium_validation',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/create-order', [
                'plan' => 'premium',
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $provisional = Subscription::where('user_id', $user->id)->sole();

        expect($provisional->status)->toBe('pending')
            ->and($provisional->trial_started_at)->toBeNull()
            ->and($provisional->trial_ends_at)->toBeNull()
            ->and($provisional->current_period_start)->toBeNull()
            ->and($provisional->current_period_end)->toBeNull()
            ->and($user->fresh()->tier)->toBe('free');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payment/subscription-status')
            ->assertOk()
            ->assertJsonPath('tier', 'free')
            ->assertJsonPath('subscription_status', 'pending');
    });

    it('rejects non-Premium keys for a new payment order', function (string $plan) {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/create-order', [
                'plan' => $plan,
                'billing_cycle' => 'monthly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    })->with(['free', 'trial', 'student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3']);

    it('accepts Premium at the upgrade validation boundary', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => 'premium'])
            ->assertForbidden()
            ->assertJsonPath('message', 'You must have an active subscription to upgrade');
    });

    it('rejects non-Premium keys at the upgrade validation boundary', function (string $plan) {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/upgrade', ['plan' => $plan])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    })->with(['free', 'trial', 'student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3']);
});

describe('discount validation', function () {
    it('accepts Premium and resolves its price from the tier store', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/validate-discount', [
                'code' => 'NOTEXIST',
                'plan' => 'premium',
                'billing_cycle' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('success', false);
    });

    it('rejects non-Premium keys', function (string $plan) {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payment/validate-discount', [
                'code' => 'NOTEXIST',
                'plan' => $plan,
                'billing_cycle' => 'monthly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    })->with(['free', 'trial', 'student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3']);
});
