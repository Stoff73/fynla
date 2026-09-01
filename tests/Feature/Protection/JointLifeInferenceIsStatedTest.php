<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** Finds the life policy row wherever the index nests it. */
function policyRow(array $payload): array
{
    $found = null;
    $walk = function ($node) use (&$walk, &$found) {
        if ($found !== null || ! is_array($node)) {
            return;
        }
        if (array_key_exists('joint_life_with', $node)) {
            $found = $node;

            return;
        }
        foreach ($node as $child) {
            $walk($child);
        }
    };
    $walk($payload);

    expect($found)->not->toBeNull('no policy row carrying joint_life_with was returned');

    return $found;
}

/**
 * W-0200. `life_insurance_policies` carries `joint_life` (a boolean) and a free-text
 * `beneficiaries` string. It has no `joint_owner_id`, no `ownership_type` and no
 * `ownership_percentage` — alone among every shared record in the application.
 *
 * So "who is the other life assured" has exactly one available answer,
 * `users.spouse_id`, and until now the application presented that inference as fact:
 * a user was told "Joint life with Sarah" as though they had entered it. A business
 * partner on a shareholder-protection policy, an unmarried couple or a parent and
 * adult child cannot be expressed at all, and the app silently names the spouse.
 *
 * Whether a second life assured becomes a first-class field is a product call this
 * item is gated on. Stating the inference is right under either answer.
 */
beforeEach(function () {
    $this->user = User::factory()->create(['marital_status' => 'married']);
    $this->spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Weber',
        'marital_status' => 'married',
        'spouse_id' => $this->user->id,
    ]);
    $this->user->update(['spouse_id' => $this->spouse->id]);
    Sanctum::actingAs($this->user->fresh());
});

it('says the other life assured was inferred rather than recorded', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'joint_life' => true,
    ]);

    $response = $this->getJson('/api/protection');
    $response->assertOk();

    $policy = policyRow($response->json());

    expect($policy['joint_life_with'])->toBe('Sarah Weber')
        ->and($policy['joint_life_with_source'])->toBe('inferred_from_spouse');
});

it('carries no source when there is no name to qualify', function () {
    $solo = User::factory()->create(['marital_status' => 'single']);
    Sanctum::actingAs($solo);

    LifeInsurancePolicy::factory()->create([
        'user_id' => $solo->id,
        'joint_life' => true,
    ]);

    $response = $this->getJson('/api/protection');
    $response->assertOk();

    $policy = policyRow($response->json());

    // Nothing is named, so there is nothing to qualify — and no source is invented.
    expect($policy['joint_life_with'])->toBeNull()
        ->and($policy['joint_life_with_source'])->toBeNull();
});
