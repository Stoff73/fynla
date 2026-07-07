<?php

declare(strict_types=1);

use App\Http\Resources\PropertyResource;
use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\Property;
use App\Models\User;
use App\Services\Advisor\ClientActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * Regression tests for the July-6 security audit fixes:
 *  (a) shared-asset resources must not leak a co-owner's PII;
 *  (b) an advisor must not be able to re-point an activity at another client;
 *  (c) the login-flow MFA routes must survive PreviewWriteInterceptor.
 */

describe('PropertyResource joint-owner PII exposure', function () {
    it('exposes only id/first_name/surname for owners — never email or income', function () {
        $owner = User::factory()->create([
            'first_name' => 'Olivia',
            'surname' => 'Owner',
            'email' => 'owner-secret@example.com',
            'annual_employment_income' => 82000,
        ]);

        $jointOwner = User::factory()->create([
            'first_name' => 'Jamie',
            'surname' => 'Joint',
            'email' => 'joint-secret@example.com',
            'annual_employment_income' => 45000,
        ]);

        // Suppress model events during setup: an unrelated Property-creation
        // observer resolves a mis-namespaced calculator in this worktree. The
        // resource output under test does not depend on those observers.
        $property = Property::withoutEvents(fn () => Property::factory()->create([
            'user_id' => $owner->id,
            'joint_owner_id' => $jointOwner->id,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]));

        $property->load(['user', 'jointOwner']);

        $json = json_decode(json_encode(new PropertyResource($property)), true);

        // Owner blocks are present and carry the identity fields the UI needs.
        expect($json['joint_owner'])->toMatchArray([
            'id' => $jointOwner->id,
            'first_name' => 'Jamie',
            'surname' => 'Joint',
        ]);
        expect(array_keys($json['joint_owner']))->toEqualCanonicalizing(['id', 'first_name', 'surname']);
        expect(array_keys($json['user']))->toEqualCanonicalizing(['id', 'first_name', 'surname']);

        // The co-owner's PII must be absent from BOTH owner blocks.
        foreach (['email', 'annual_employment_income', 'annual_self_employment_income', 'national_insurance_number', 'monthly_expenditure'] as $leak) {
            expect($json['joint_owner'])->not->toHaveKey($leak);
            expect($json['user'])->not->toHaveKey($leak);
        }

        // And the sensitive values must not appear anywhere in the payload.
        $encoded = json_encode($json);
        expect($encoded)->not->toContain('joint-secret@example.com');
        expect($encoded)->not->toContain('owner-secret@example.com');
    });
});

describe('Advisor activity update client_id mass-assignment', function () {
    it('ignores a client_id in the update payload over HTTP', function () {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();

        $advisorClient = AdvisorClient::factory()->create([
            'advisor_id' => $advisor->id,
            'client_id' => $clientA->id,
            'status' => 'active',
        ]);

        $activity = ClientActivity::factory()->create([
            'advisor_client_id' => $advisorClient->id,
            'advisor_id' => $advisor->id,
            'client_id' => $clientA->id,
            'activity_type' => 'note',
            'summary' => 'Original summary',
            'activity_date' => now(),
        ]);

        Sanctum::actingAs($advisor);

        $response = $this->putJson("/api/advisor/activities/{$activity->id}", [
            'activity_type' => 'note',
            'summary' => 'Updated summary',
            'activity_date' => now()->toDateTimeString(),
            'client_id' => $clientB->id, // malicious re-assignment attempt
        ]);

        $response->assertOk();

        $activity->refresh();
        expect($activity->client_id)->toBe($clientA->id);      // unchanged
        expect($activity->summary)->toBe('Updated summary');   // legit field applied
    });

    it('strips ownership keys defensively at the service layer', function () {
        $advisor = User::factory()->create(['is_advisor' => true]);
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();
        $otherAdvisor = User::factory()->create(['is_advisor' => true]);

        $advisorClient = AdvisorClient::factory()->create([
            'advisor_id' => $advisor->id,
            'client_id' => $clientA->id,
            'status' => 'active',
        ]);

        $activity = ClientActivity::factory()->create([
            'advisor_client_id' => $advisorClient->id,
            'advisor_id' => $advisor->id,
            'client_id' => $clientA->id,
            'summary' => 'Before',
        ]);

        app(ClientActivityService::class)->update($advisor, $activity->id, [
            'summary' => 'After',
            'client_id' => $clientB->id,
            'advisor_id' => $otherAdvisor->id,
            'advisor_client_id' => 999999,
        ]);

        $activity->refresh();
        expect($activity->summary)->toBe('After');
        expect($activity->client_id)->toBe($clientA->id);
        expect($activity->advisor_id)->toBe($advisor->id);
        expect($activity->advisor_client_id)->toBe($advisorClient->id);
    });
});

describe('PreviewWriteInterceptor login-flow MFA exclusion', function () {
    it('lets a preview user reach the real MFA verify handler', function () {
        $preview = User::factory()->create([
            'is_preview_user' => true,
            'email' => 'preview-persona@example.com',
        ]);

        $token = $preview->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/mfa/verify', [
                'code' => '123456',
                'mfa_token' => 'not-a-real-challenge-token',
            ]);

        // Reached MFAController::verify (invalid challenge → generic 401),
        // NOT the interceptor's fake preview response.
        $response->assertStatus(401);
        $response->assertJsonMissing(['preview_mode' => true]);
        expect($response->json('message'))->not->toContain('not saved');
    });

    it('still intercepts the same preview token on a non-excluded write route', function () {
        // Control: proves the token IS detected as a preview user, so the
        // mfa/verify pass-through above is due to the exclusion, not detection.
        $preview = User::factory()->create(['is_preview_user' => true]);
        $token = $preview->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/properties', [
                'address_line_1' => '1 Test Street',
                'postcode' => 'SW1A 1AA',
                'property_type' => 'main_residence',
                'current_value' => 500000,
            ]);

        $response->assertOk();
        $response->assertJson(['preview_mode' => true]);
    });
});
