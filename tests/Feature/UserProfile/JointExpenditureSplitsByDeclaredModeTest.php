<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\SharedExpenditure;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0190 — the expenditure table declared "Joint (50/50) expenditure" in its own
 * subheading and then charged the whole £2,450 to one spouse and £0 to the other,
 * beside a financial-commitments row that IS split by ownership. Two rows, one
 * screen, different rules.
 *
 * The rule was correct and implemented — in the onboarding path only. The profile
 * path stored the whole household figure on whoever typed it and, in joint mode,
 * mirrored the whole figure to the spouse as well, so the declared 50/50 was never
 * applied by either branch.
 *
 * Disposable income is what every affordability statement rests on, so these hold
 * both accounts of one household to the split they were told they had.
 */
beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->withActivePremiumSubscription()->create([
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
    ]);
    $this->spouse = User::factory()->withActivePremiumSubscription()->create([
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
        'spouse_id' => $this->user->id,
    ]);
    $this->user->update(['spouse_id' => $this->spouse->id]);
});

/** The household's fifteen categories, as the form sends them. */
function householdExpenditurePayload(array $overrides = []): array
{
    return array_merge([
        'use_simple_entry' => false,
        'use_separate_expenditure' => false,
        'food_groceries' => 750,
        'mobile_phones' => 120,
        'clothing_personal_care' => 300,
        'children_activities' => 1_230,
        'other_expenditure' => 50,
        'monthly_expenditure' => 2_450,
        'annual_expenditure' => 29_400,
    ], $overrides);
}

it('charges each spouse half of a household expenditure entered as joint', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())
        ->assertOk();

    $stored = $this->user->fresh();

    expect((float) $stored->monthly_expenditure)->toBe(1225.0)
        ->and((float) $stored->annual_expenditure)->toBe(14700.0)
        ->and((float) $stored->food_groceries)->toBe(375.0)
        ->and((float) $stored->children_activities)->toBe(615.0);
});

it('leaves nothing uncharged — the two halves are the household figure', function () {
    $payload = householdExpenditurePayload();

    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', $payload)->assertOk();

    $this->actingAs($this->user)
        ->putJson("/api/users/{$this->spouse->id}/expenditure", $payload)->assertOk();

    $user = $this->user->fresh();
    $spouse = $this->spouse->fresh();

    expect((float) $user->monthly_expenditure + (float) $spouse->monthly_expenditure)->toBe(2450.0)
        ->and((float) $user->food_groceries + (float) $spouse->food_groceries)->toBe(750.0)
        // Neither column is the whole of it, which is the defect.
        ->and((float) $user->monthly_expenditure)->not->toBe(2450.0)
        ->and((float) $spouse->monthly_expenditure)->not->toBe(0.0);
});

it('moves both accounts together when the household figure moves', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())->assertOk();

    expect((float) $this->user->fresh()->monthly_expenditure)->toBe(1225.0);

    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload([
            'monthly_expenditure' => 3_000,
            'annual_expenditure' => 36_000,
        ]))->assertOk();

    expect((float) $this->user->fresh()->monthly_expenditure)->toBe(1500.0);
});

it('stores the whole of it when the household says the spending is separate', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload([
            'use_separate_expenditure' => true,
            'monthly_expenditure' => 900,
            'annual_expenditure' => 10_800,
        ]))->assertOk();

    $stored = $this->user->fresh();

    expect((float) $stored->monthly_expenditure)->toBe(900.0)
        ->and($stored->expenditure_sharing_mode)->toBe(SharedExpenditure::MODE_SEPARATE);
});

it('stores the whole of it when there is nobody to share with', function () {
    $single = User::factory()->withActivePremiumSubscription()->create([
        'spouse_id' => null,
        'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
    ]);

    $this->actingAs($single)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())->assertOk();

    expect((float) $single->fresh()->monthly_expenditure)->toBe(2450.0);
});

it('keeps the sharing mode a fact about the household, not about one row', function () {
    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload([
            'use_separate_expenditure' => true,
        ]))->assertOk();

    // Left on one account, the next save would divide the two halves of one
    // household by two different rules.
    expect($this->spouse->fresh()->expenditure_sharing_mode)->toBe(SharedExpenditure::MODE_SEPARATE);
});

describe('SharedExpenditure', function () {
    it('divides only the money', function () {
        $shared = SharedExpenditure::shareOf([
            'food_groceries' => 750,
            'monthly_expenditure' => 2_450,
            'expenditure_entry_mode' => 'category',
            'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
            'retired_budget_overrides' => ['food_groceries' => 400],
        ], true);

        expect($shared['food_groceries'])->toBe(375.0)
            ->and($shared['monthly_expenditure'])->toBe(1225.0)
            ->and($shared['expenditure_entry_mode'])->toBe('category')
            ->and($shared['expenditure_sharing_mode'])->toBe(SharedExpenditure::MODE_JOINT)
            ->and($shared['retired_budget_overrides'])->toBe(['food_groceries' => 400]);
    });

    it('keeps a partial update partial', function () {
        $shared = SharedExpenditure::shareOf(['food_groceries' => 750], true);

        expect($shared)->toBe(['food_groceries' => 375.0])
            ->and($shared)->not->toHaveKey('mobile_phones');
    });

    it('leaves a null alone rather than writing a zero over it', function () {
        $shared = SharedExpenditure::shareOf(['food_groceries' => null], true);

        expect($shared['food_groceries'])->toBeNull();
    });

    it('treats an unset mode as joint, matching every other default in the app', function () {
        expect(SharedExpenditure::isShared(null))->toBeTrue()
            ->and(SharedExpenditure::isShared(SharedExpenditure::MODE_JOINT))->toBeTrue()
            ->and(SharedExpenditure::isShared(SharedExpenditure::MODE_SEPARATE))->toBeFalse();
    });
});
