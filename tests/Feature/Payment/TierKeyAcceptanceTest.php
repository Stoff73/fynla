<?php

declare(strict_types=1);

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

/**
 * SP2 PR5 — Folded-in payment-backend tier-key acceptance tests.
 *
 * Verifies that PaymentController methods accept tier keys (free, tier1,
 * tier2, tier3) in their validation rather than 422-ing with "The selected
 * plan is invalid."
 */
describe('POST /api/payment/create-order — tier-key validation', function () {

    it('rejects old legacy slugs that were never valid — sanity check', function () {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
            'plan' => 'enterprise',
            'billing_cycle' => 'monthly',
        ]);
        $response->assertStatus(422);
    });

    it('accepts free tier and returns 422 with no-order message (not a plan validation failure)', function () {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
            'plan' => 'free',
            'billing_cycle' => 'monthly',
        ]);
        // Free tier is accepted by validation but short-circuited — no Revolut order.
        // We get a 422 with our domain message, not the generic "The selected plan is invalid".
        $response->assertStatus(422)
            ->assertJsonPath('message', 'The Free tier has no payment. No order created.');
    });

    it('passes validation for tier1 and reaches Revolut (resolves price from store)', function () {
        $user = User::factory()->create(['revolut_customer_id' => 'cust_test_123']);

        Http::fake([
            '*' => Http::response([
                'id' => 'order_abc',
                'token' => 'tok_abc',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
            'plan' => 'tier1',
            'billing_cycle' => 'monthly',
        ]);

        // Should not 422 on validation — plan is accepted.
        // (May succeed with 200 or fail with 500 if env not configured — we
        // test that it is NOT a 422 validation error on 'plan'.)
        $response->assertSuccessful();
    });

    it('passes validation for tier2 and reaches Revolut', function () {
        $user = User::factory()->create(['revolut_customer_id' => 'cust_test_456']);

        Http::fake([
            '*' => Http::response([
                'id' => 'order_def',
                'token' => 'tok_def',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
            'plan' => 'tier2',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSuccessful();
    });

    it('passes validation for tier3 and reaches Revolut', function () {
        $user = User::factory()->create(['revolut_customer_id' => 'cust_test_789']);

        Http::fake([
            '*' => Http::response([
                'id' => 'order_ghi',
                'token' => 'tok_ghi',
                'state' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], 200),
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/create-order', [
            'plan' => 'tier3',
            'billing_cycle' => 'monthly',
        ]);

        $response->assertSuccessful();
    });

});

describe('POST /api/payment/validate-discount — tier-key validation', function () {

    it('accepts free tier key without plan validation error', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/validate-discount', [
            'code' => 'TESTCODE',
            'plan' => 'free',
            'billing_cycle' => 'monthly',
        ]);

        // Should not fail with "The selected plan is invalid" (422 from plan rule).
        // It will return success:false because TESTCODE does not exist, but plan is accepted.
        $response->assertStatus(200)
            ->assertJsonPath('success', false);

        // The response message should be about the code, not the plan.
        $message = $response->json('message');
        expect($message)->not->toContain('plan');
    });

    it('accepts tier1 key and resolves price from the tier store', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payment/validate-discount', [
            'code' => 'NOTEXIST',
            'plan' => 'tier1',
            'billing_cycle' => 'monthly',
        ]);

        // Plan validation passes; code validation may fail — that is expected.
        $response->assertStatus(200);
        $message = $response->json('message');
        expect($message)->not->toContain('selected plan is invalid');
    });

});
