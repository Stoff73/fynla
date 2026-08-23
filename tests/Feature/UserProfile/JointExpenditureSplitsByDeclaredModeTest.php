<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
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

it('leaves nothing uncharged — the two halves are the household figure, from ONE request', function () {
    // AMENDED (W-0412). This case used to fire BOTH endpoints itself, back to
    // back, and then assert the halves added up. That proved the two requests
    // agreed with each other; it could not see that the household total DEPENDED
    // on both arriving. It stayed green through the night the second one did not.
    $this->actingAs($this->user)
        ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())->assertOk();

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

/**
 * W-0412 — the residual of W-0190.
 *
 * W-0190 made the profile path apply the household's declared rule to the
 * account doing the typing. It gave that path nowhere to put the OTHER half:
 * the spouse's row was written by a SECOND, INDEPENDENT HTTP request the
 * frontend was trusted to send. The backend never required it, never verified
 * it, and could not compensate when it did not arrive — and on 2026-08-22 at
 * 20:24 it did not (audit_logs #1376 moved David's row and there is no matching
 * row for Sarah).
 *
 * The screen then read: "Joint (50/50) expenditure", David £1,250, Sarah
 * £1,225, Household £2,475 — a declared 50/50 reading 50.5 / 49.5, and a
 * household £25 short of the categories it is made of, because the household
 * was being computed as *half plus half* rather than being the source of truth.
 *
 * COLLISION NOTE (tests/CLAUDE.md §4). Asserting "the household comes to
 * £2,500" passes trivially — it is the number the payload carried in. And a
 * 50/50 split makes the two halves the same number, so a suite that starts both
 * rows equal cannot tell a mirrored write from no write at all. Every case
 * below therefore starts the two rows OUT OF STEP, exactly as the live data
 * was, and asserts that the NON-EDITING spouse's stored row MOVES.
 */
describe('the second account is not left to a second request', function () {
    /**
     * The live shape: both rows written once, then the owner's row moved on and
     * the spouse's left behind. Healthcare is the divergent category — £50 on
     * one row, £25 on the other, where the household spends £100.
     */
    function putHouseholdOutOfStep(User $owner, User $spouse): void
    {
        $owner->update([
            'expenditure_entry_mode' => 'category',
            'food_groceries' => 225,
            'healthcare_medical' => 50,
            'monthly_expenditure' => 1_250,
            'annual_expenditure' => 15_000,
        ]);
        $spouse->update([
            'expenditure_entry_mode' => 'category',
            'food_groceries' => 225,
            'healthcare_medical' => 25,
            'monthly_expenditure' => 1_225,
            'annual_expenditure' => 14_700,
        ]);
    }

    it('moves the non-editing spouse\'s half when the owner edits a category', function () {
        putHouseholdOutOfStep($this->user, $this->spouse);

        expect((float) $this->spouse->fresh()->healthcare_medical)->toBe(25.0);

        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', [
                'use_simple_entry' => false,
                'use_separate_expenditure' => false,
                'food_groceries' => 450,
                'healthcare_medical' => 100,
                'monthly_expenditure' => 2_500,
                'annual_expenditure' => 30_000,
            ])->assertOk();

        $spouse = $this->spouse->fresh();

        // 25 is what a row that was never written reads. 50 is the household's
        // £100 divided. The two hypotheses give different numbers here, which
        // is the whole point of starting them out of step.
        expect((float) $spouse->healthcare_medical)->toBe(50.0)
            ->and((float) $spouse->monthly_expenditure)->toBe(1250.0);
    });

    it('leaves the declared 50/50 reading 50/50 — the two halves are equal', function () {
        putHouseholdOutOfStep($this->user, $this->spouse);

        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', [
                'use_simple_entry' => false,
                'use_separate_expenditure' => false,
                'food_groceries' => 450,
                'healthcare_medical' => 100,
                'monthly_expenditure' => 2_500,
                'annual_expenditure' => 30_000,
            ])->assertOk();

        $user = $this->user->fresh();
        $spouse = $this->spouse->fresh();

        expect((float) $user->monthly_expenditure)->toBe((float) $spouse->monthly_expenditure)
            ->and((float) $user->healthcare_medical)->toBe((float) $spouse->healthcare_medical)
            // And the household is the sum of the categories, not £25 short of it.
            ->and((float) $user->monthly_expenditure + (float) $spouse->monthly_expenditure)->toBe(2500.0)
            ->and((float) $user->healthcare_medical + (float) $spouse->healthcare_medical)->toBe(100.0);
    });

    it('is not doubled when the frontend also sends the spouse request', function () {
        // The redundant second call is gone from the form, but an older bundle
        // or a direct API client can still make it. It must be idempotent.
        $payload = householdExpenditurePayload();

        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', $payload)->assertOk();
        $this->actingAs($this->user)
            ->putJson("/api/users/{$this->spouse->id}/expenditure", $payload)->assertOk();

        expect((float) $this->user->fresh()->monthly_expenditure)->toBe(1225.0)
            ->and((float) $this->spouse->fresh()->monthly_expenditure)->toBe(1225.0);
    });

    it('moves both accounts on a PARTIAL update, which is how Fyn writes', function () {
        putHouseholdOutOfStep($this->user, $this->spouse);

        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', ['healthcare_medical' => 100])
            ->assertOk();

        $user = $this->user->fresh();
        $spouse = $this->spouse->fresh();

        expect((float) $user->healthcare_medical)->toBe(50.0)
            ->and((float) $spouse->healthcare_medical)->toBe(50.0)
            // A partial update stays partial — it does not zero what it did not name.
            ->and((float) $spouse->food_groceries)->toBe(225.0);
    });

    it('gives both accounts an ExpenditureProfile carrying the same total', function () {
        // ResolvesExpenditure prefers this row over users.monthly_expenditure,
        // so an account with one and an account without report from different
        // sources — which is why Sarah\'s emergency-fund runway divided by a
        // stale figure the profile page had already moved on from.
        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())->assertOk();

        $userProfile = ExpenditureProfile::where('user_id', $this->user->id)->first();
        $spouseProfile = ExpenditureProfile::where('user_id', $this->spouse->id)->first();

        expect($userProfile)->not->toBeNull()
            ->and($spouseProfile)->not->toBeNull()
            ->and((float) $userProfile->total_monthly_expenditure)->toBe(1225.0)
            ->and((float) $spouseProfile->total_monthly_expenditure)->toBe(1225.0);
    });

    it('does not touch the spouse\'s own figures when the household spends separately', function () {
        $this->user->update(['expenditure_sharing_mode' => SharedExpenditure::MODE_SEPARATE]);
        $this->spouse->update([
            'expenditure_sharing_mode' => SharedExpenditure::MODE_SEPARATE,
            'food_groceries' => 300,
            'monthly_expenditure' => 900,
        ]);

        $this->actingAs($this->user)
            ->putJson('/api/user/profile/expenditure', householdExpenditurePayload([
                'use_separate_expenditure' => true,
            ]))->assertOk();

        $spouse = $this->spouse->fresh();

        // Under separate spending each account carries what THAT person spends;
        // mirroring here would overwrite it.
        expect((float) $spouse->food_groceries)->toBe(300.0)
            ->and((float) $spouse->monthly_expenditure)->toBe(900.0);
    });

    it('stores the whole of it when the spouse\'s account has been deleted', function () {
        // `spouse_id` survives the deletion and the record is retained, so the
        // old `spouse_id !== null` predicate halved a household\'s spending into
        // a row nobody could read. The live-spouse test is what stops that.
        $this->spouse->delete();

        $this->actingAs($this->user->fresh())
            ->putJson('/api/user/profile/expenditure', householdExpenditurePayload())->assertOk();

        expect((float) $this->user->fresh()->monthly_expenditure)->toBe(2450.0);
    });
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
