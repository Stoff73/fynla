<?php

declare(strict_types=1);

use App\Http\Requests\Retirement\StoreDCPensionRequest;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * W-0262 — the per-pension risk control was silently discarded by the validator.
 *
 * `DCPensionForm.vue:323` binds `risk_preference`, the client sends it,
 * `DCPension::$fillable` declares it, `PensionStore::validateDcCanonical` accepts
 * it and `RetirementController:865`, `PensionProjector:291` and
 * `PortfolioPresentationService:204` all read it. `StoreDCPensionRequest` had no
 * rule for it, so `$request->validated()` stripped it before the controller saw
 * it. The row's `updated_at` moved and the platform fee in the SAME submit
 * persisted — because a fee had a rule and this did not — so the save looked
 * entirely successful and lost the field.
 *
 * TEST DESIGN — `tests/CLAUDE.md` §4. Asserting that the request succeeded is
 * exactly the assertion that passed throughout the bug. Every case below asserts
 * the stored value MOVED to something it was not before, and the starting value is
 * set explicitly so "moved" cannot be confused with "was already that".
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);

    $this->pension = DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'Global Finance Corp Pension',
        'current_fund_value' => 250000.00,
        'risk_preference' => 'medium',
        'has_custom_risk' => false,
        'platform_fee_percent' => 0.10,
    ]);
});

it('persists a changed risk preference on a pension', function () {
    // The tester's exact action: choose Upper-Medium, save, read the row back.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'risk_preference' => 'upper_medium',
    ])->assertOk();

    $this->pension->refresh();

    // Not "the request returned 200" — the value is no longer the one it started
    // as. That is the assertion the bug would have failed.
    expect($this->pension->risk_preference)->toBe('upper_medium')
        ->and($this->pension->risk_preference)->not->toBe('medium');
});

it('turns on the flag every reader of the override gates on', function () {
    // Storing the preference alone would have left the feature inert: every
    // consumer tests `has_custom_risk && risk_preference`, and before this fix the
    // only writers of that flag in the whole codebase were the seeders. The
    // normaliser derives it from the act of choosing a level.
    expect($this->pension->has_custom_risk)->toBeFalse();

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'risk_preference' => 'upper_medium',
    ])->assertOk();

    $this->pension->refresh();

    expect($this->pension->has_custom_risk)->toBeTrue();
});

it('clears the override flag when the risk preference is cleared', function () {
    $this->pension->update(['risk_preference' => 'high', 'has_custom_risk' => true]);

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'risk_preference' => null,
    ])->assertOk();

    $this->pension->refresh();

    expect($this->pension->risk_preference)->toBeNull()
        ->and($this->pension->has_custom_risk)->toBeFalse();
});

it('leaves the override alone when an edit does not mention it', function () {
    // An edit of an unrelated field must not clear a choice the user made earlier.
    $this->pension->update(['risk_preference' => 'high', 'has_custom_risk' => true]);

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'platform_fee_percent' => 0.35,
    ])->assertOk();

    $this->pension->refresh();

    expect($this->pension->risk_preference)->toBe('high')
        ->and($this->pension->has_custom_risk)->toBeTrue()
        ->and((float) $this->pension->platform_fee_percent)->toEqual(0.35);
});

it('rejects a risk preference the column cannot hold', function () {
    // `risk_preference` is an enum. The store's rule was `string|max:64`, so a
    // value outside the enum passed validation and died as a QueryException at the
    // column — the same shape as `inflation_protection` before it was tightened.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'risk_preference' => 'extremely_adventurous',
    ])->assertStatus(422);

    $this->pension->refresh();

    expect($this->pension->risk_preference)->toBe('medium');
});

it('persists the other fields the validator was dropping', function () {
    // `risk_preference` was the one the tester caught; five more fields on the same
    // form had the same problem for the same reason.
    $this->pension->update([
        'expected_return_percent' => 4.00,
        'salary_sacrifice' => false,
    ]);

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'expected_return_percent' => 6.50,
        'salary_sacrifice' => true,
        'employer_matching_limit' => 8.00,
    ])->assertOk();

    $this->pension->refresh();

    expect((float) $this->pension->expected_return_percent)->toEqual(6.50)
        ->and($this->pension->salary_sacrifice)->toBeTrue()
        ->and((float) $this->pension->employer_matching_limit)->toEqual(8.00);
});

it('keeps the form request in step with the canonical store validator', function () {
    // The guard against a repeat. `PensionStore::validateDcCanonical` carries the
    // comment "Mirrors StoreDCPensionRequest" and did not: it accepted six fields
    // the outer request had no rule for, and `validated()` stripped every one.
    //
    // The inner validator is the enforcing layer and is deliberately the stricter
    // of the two, so the invariant is one-directional: anything the store will
    // accept must survive the request that feeds it. The reverse is allowed — the
    // request legitimately validates form-only keys such as `holdings` that the
    // store never sees.
    $canonical = (function () {
        $method = new ReflectionMethod(PensionStore::class, 'validateDcCanonical');
        $source = file(__DIR__.'/../../../app/Services/Stores/PensionStore.php');
        $body = implode('', array_slice(
            $source,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        preg_match_all("/^\s+'([a-z_]+)' => /m", $body, $m);

        return $m[1];
    })();

    expect($canonical)->not->toBeEmpty();

    $requestRules = array_keys((new StoreDCPensionRequest)->rules());

    $missing = array_values(array_diff($canonical, $requestRules));

    expect($missing)->toBe([]);
});

it('accepts the exact payload DCPensionForm sends, nulls and all', function () {
    // THE CASE THE REST OF THIS FILE MISSED, and the browser did not.
    //
    // Every case above sends the one or two fields under test. The real form
    // serialises its whole model, so it sends `salary_sacrifice: null`,
    // `expected_return_percent: null`, `employer_matching_limit: null` and
    // `employer_ni_rebate_pct: null` alongside the field the user actually
    // changed. Giving those fields validation rules stopped `validated()`
    // stripping them — which is the fix — and that in turn exposed them to the
    // canonical store for the first time, where `salary_sacrifice` was
    // `sometimes|boolean` with no `nullable` and rejected the null outright.
    //
    // The result was a 422 on a save the user had every right to make. It is the
    // fixture variant of tests/CLAUDE.md §4 pointing at my own fix: a payload
    // narrower than the real one cannot enter the branch that breaks.
    //
    // Captured verbatim from the browser: PUT /api/retirement/pensions/dc/9.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'pension_type' => 'occupational',
        'scheme_type' => 'workplace',
        'scheme_name' => 'Global Finance Corp Pension',
        'provider' => 'Fidelity',
        'current_fund_value' => '180000.00',
        'annual_salary' => '145000.00',
        'employee_contribution_percent' => '8.00',
        'employer_contribution_percent' => '8.00',
        'monthly_contribution_amount' => null,
        'lump_sum_contribution' => null,
        'expected_return_percent' => null,
        'platform_fee_type' => 'percentage',
        'platform_fee_amount' => null,
        'platform_fee_frequency' => 'annually',
        'advisor_fee_percent' => null,
        'retirement_age' => 60,
        'salary_sacrifice' => null,
        'risk_preference' => 'upper_medium',
        'beneficiary_id' => null,
        'beneficiary_name' => '',
        'holdings' => [],
        'member_number' => null,
        'employer_matching_limit' => null,
        'investment_strategy' => null,
        'platform_fee_percent' => '0.3500',
        'projected_value_at_retirement' => null,
        'has_custom_risk' => false,
        'has_flexibly_accessed' => false,
        'flexible_access_date' => null,
        'employer_ni_rebate_pct' => null,
    ])->assertOk();

    $this->pension->refresh();

    expect($this->pension->risk_preference)->toBe('upper_medium')
        ->and($this->pension->has_custom_risk)->toBeTrue();
});

it('drops a null bound for a NOT NULL pension column so its default applies', function () {
    // `current_fund_value` is NOT NULL DEFAULT '0.00' and the sweep in F-0023
    // §6.1 lists it as a column a form sends null for — the same shape that 500'd
    // every investment-account create (W-0052) and every holding create (W-0261).
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", [
        'current_fund_value' => null,
        'pension_type' => null,
        'platform_fee_type' => null,
        'platform_fee_frequency' => null,
        'has_flexibly_accessed' => null,
    ])->assertOk();

    $this->pension->refresh();

    // Dropped, not zeroed: the stored figure survives an edit that did not mean
    // to change it.
    expect((float) $this->pension->current_fund_value)->toEqual(250000.0)
        ->and($this->pension->pension_type)->not->toBeNull();
});

it('keeps the DC NOT NULL list in step with the actual schema', function () {
    $notNull = collect(DB::select(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND IS_NULLABLE = ?
           AND COLUMN_DEFAULT IS NOT NULL',
        ['dc_pensions', 'NO']
    ))->pluck('COLUMN_NAME');

    $actual = $notNull->intersect(array_keys((new StoreDCPensionRequest)->rules()))->sort()->values()->all();

    expect(collect(PensionNormaliser::DC_NOT_NULL_WITH_DEFAULT)->sort()->values()->all())->toBe($actual);
});
