<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0263 — the values a user is entitled to enter now reach the database.
 *
 * Every column here was `decimal(5,4)`, which stops at 9.9999, behind a rule that
 * promised far more. The rule did not reject the input; it passed it to MySQL,
 * which raised `SQLSTATE[22003] Out of range` and the user got a 500 instead of a
 * validation message. The migration widened the columns; these tests are the
 * proof that the promise is now kept.
 *
 * TEST DESIGN — `tests/CLAUDE.md` §4, the collision variant, which is the whole
 * risk in this file. **A 9% mortgage rate saves today and saved before the fix.**
 * Asserting on it would pass against both hypotheses and prove nothing. Every
 * probe below is therefore a value that **used to 500**:
 *
 *   - 12% and 14.75% mortgage rates (the column stopped below 10)
 *   - a 12.5% savings rate (rule advertised 20, column stopped below 10)
 *   - a 50% private-company shareholding (column stopped below 10, and the table
 *     held zero non-null rows because nobody had ever managed to store one)
 *
 * And each is paired with an assertion in the opposite direction, so "widened"
 * cannot quietly become "unbounded".
 *
 * **Payload shape.** These post the WHOLE form model — every key the Vue form
 * carries, including the nulls — rather than only the fields under test. A sibling
 * batch shipped 21 green tests that missed a regression the browser caught
 * immediately, because the real form serialises its entire model and the tests
 * sent three keys. The mortgage payloads below mirror `PropertyForm.mortgageForm`
 * field for field.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test-token')->plainTextToken;
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
});

/**
 * The complete `mortgageForm` model from `PropertyForm.vue`, nulls included.
 */
function mortgagePayload(array $overrides = []): array
{
    return array_merge([
        'lender_name' => 'Halifax',
        'mortgage_account_number' => null,
        'mortgage_type' => 'repayment',
        'repayment_percentage' => null,
        'interest_only_percentage' => null,
        'original_loan_amount' => 400000,
        'outstanding_balance' => 320000,
        'interest_rate' => 5.25,
        'rate_type' => 'fixed',
        'fixed_rate_percentage' => null,
        'variable_rate_percentage' => null,
        'fixed_interest_rate' => null,
        'variable_interest_rate' => null,
        'rate_fix_end_date' => null,
        'monthly_payment' => 1950.00,
        'monthly_interest_portion' => null,
        'start_date' => '2020-06-01',
        'maturity_date' => '2045-06-01',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => null,
        'joint_owner_name' => null,
    ], $overrides);
}

describe('mortgage interest rates (W-0263 headline)', function () {
    it('stores a 12% fixed rate through the real create payload', function () {
        // THE headline acceptance. A double-digit mortgage rate is most of
        // British history, and it is where adverse-credit and some buy-to-let
        // products sit now. Before the widening this exact request raised
        // SQLSTATE[22003] and the user saw a 500.
        $response = $this->withToken($this->token)->postJson(
            "/api/properties/{$this->property->id}/mortgages",
            mortgagePayload(['rate_type' => 'fixed', 'fixed_interest_rate' => 12])
        );

        $response->assertSuccessful();

        $mortgage = Mortgage::where('property_id', $this->property->id)->firstOrFail();
        expect((float) $mortgage->fixed_interest_rate)->toEqual(12.0);
    });

    it('stores a 14.75% variable rate, decimals and all', function () {
        // Four decimal places must survive the widening — dec(8,4) keeps the
        // scale and only adds integer digits.
        $response = $this->withToken($this->token)->postJson(
            "/api/properties/{$this->property->id}/mortgages",
            mortgagePayload(['rate_type' => 'variable', 'variable_interest_rate' => 14.75])
        );

        $response->assertSuccessful();

        expect((float) Mortgage::where('property_id', $this->property->id)->firstOrFail()->variable_interest_rate)
            ->toEqual(14.75);
    });

    it('stores a 12% rate on UPDATE too, not only on create', function () {
        // Store and Update are separate request classes with separately written
        // rules — the defect was in both, so the proof has to be in both.
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'rate_type' => 'fixed',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/mortgages/{$mortgage->id}", mortgagePayload([
                'rate_type' => 'fixed',
                'fixed_interest_rate' => 12,
            ]))
            ->assertSuccessful();

        expect((float) $mortgage->refresh()->fixed_interest_rate)->toEqual(12.0);
    });

    it('stores a 12% fixed rate on a MIXED-rate mortgage, through the Store', function () {
        // The headline as a user actually reaches it, and the probe that needed
        // two separate fixes to pass.
        //
        // `fixed_interest_rate` only renders on the property form when rate type
        // is `mixed`, so this is the only route to it — and `mixed` was refused
        // by MortgageStore's own `in:` list (W-0326) even though the column
        // stores it and all three form requests allow it. The Store validates
        // separately from the requests, which is why the request-layer sweep
        // could not see it.
        //
        // The probe therefore fails under BOTH previous states and is safe from
        // the collision variant twice over: `mixed` was a 422 at the Store, and
        // `12` was a SQLSTATE[22003] at the decimal(5,4) column.
        $mortgage = Mortgage::factory()->create([
            'property_id' => $this->property->id,
            'user_id' => $this->user->id,
            'rate_type' => 'fixed',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/mortgages/{$mortgage->id}", mortgagePayload([
                'rate_type' => 'mixed',
                'fixed_rate_percentage' => 60,
                'variable_rate_percentage' => 40,
                'fixed_interest_rate' => 12,
                'variable_interest_rate' => 14.75,
            ]))
            ->assertSuccessful();

        $mortgage->refresh();

        expect($mortgage->rate_type)->toBe('mixed')
            ->and((float) $mortgage->fixed_interest_rate)->toEqual(12.0)
            ->and((float) $mortgage->variable_interest_rate)->toEqual(14.75);
    });

    it('still refuses a rate beyond what the rule promises', function () {
        // The widening must not read as "any number now". dec(8,4) reaches
        // 9999.9999, so `max:100` is the binding constraint — the right way
        // round, because the user is now stopped by a rule that says so rather
        // than by a column that crashes.
        $this->withToken($this->token)->postJson(
            "/api/properties/{$this->property->id}/mortgages",
            mortgagePayload(['rate_type' => 'fixed', 'fixed_interest_rate' => 250])
        )->assertStatus(422)->assertJsonValidationErrors(['fixed_interest_rate']);

        expect(Mortgage::where('property_id', $this->property->id)->exists())->toBeFalse();
    });
});

describe('savings interest rate', function () {
    it('stores a 12.5% rate', function () {
        // The rule said max:20 and its own message said "cannot exceed 20%",
        // while the column silently overrode both at 10.
        $response = $this->withToken($this->token)->postJson('/api/savings/accounts', [
            'account_name' => 'Regular Saver',
            'provider' => 'First Direct',
            'account_type' => 'regular_saver',
            'current_balance' => 3600,
            'interest_rate' => 12.5,
            'ownership_type' => 'individual',
        ]);

        $response->assertSuccessful();

        expect((float) SavingsAccount::where('user_id', $this->user->id)->firstOrFail()->interest_rate)
            ->toEqual(12.5);
    });

    it('still holds the advertised 20% ceiling', function () {
        $this->withToken($this->token)->postJson('/api/savings/accounts', [
            'account_name' => 'Impossible Saver',
            'provider' => 'Nowhere Bank',
            'account_type' => 'regular_saver',
            'current_balance' => 1000,
            'interest_rate' => 25,
            'ownership_type' => 'individual',
        ])->assertStatus(422)->assertJsonValidationErrors(['interest_rate']);
    });
});

describe('investment account percentages', function () {
    it('stores a 50% private-company shareholding', function () {
        // `current_ownership_percent` is a percentage — the input is
        // `min="0" max="100" step="0.01"` and the detail view appends `%`. On
        // decimal(5,4) a 50% stake could not be stored AT ALL; the column held
        // zero non-null rows, which is what a field that has never once worked
        // looks like from the outside.
        $response = $this->withToken($this->token)->postJson('/api/investment/accounts', [
            'account_type' => 'private_company',
            'provider' => 'Chen Tech Consulting',
            'current_value' => 250000,
            'current_ownership_percent' => 50,
            'ownership_type' => 'individual',
            // Conditionally required for this account type. Included because the
            // real form sends them, and because a payload that 422s for an
            // unrelated missing field proves nothing about the column under test.
            'company_legal_name' => 'Chen Tech Consulting Ltd',
            'investment_date' => '2021-04-06',
            'investment_amount' => 125000,
            'instrument_type' => 'ordinary_shares',
        ]);

        $response->assertSuccessful();

        expect((float) InvestmentAccount::where('user_id', $this->user->id)->firstOrFail()->current_ownership_percent)
            ->toEqual(50.0);
    });

    it('tells the user a 12% platform fee is too high instead of crashing', function () {
        // The fourth shape: this rule had NO `max:` at all, so the decimal(5,4)
        // column was the only thing between a typed 12 and SQLSTATE[22003] —
        // while its sibling `advisor_fee_percent` on the same form already
        // carried max:10. A 422 is the point; the previous behaviour was a 500.
        $this->withToken($this->token)->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Expensive Platform',
            'current_value' => 50000,
            'platform_fee_type' => 'percentage',
            'platform_fee_percent' => 12,
            'ownership_type' => 'individual',
        ])->assertStatus(422)->assertJsonValidationErrors(['platform_fee_percent']);
    });

    it('stores a fee at exactly 10%, the boundary that used to overflow', function () {
        // `max:10` against decimal(5,4) was satisfiable everywhere except at
        // exactly 10, which is the one value the rule explicitly permits.
        $response = $this->withToken($this->token)->postJson('/api/investment/accounts', [
            'account_type' => 'isa',
            'provider' => 'Boundary Platform',
            'current_value' => 50000,
            'platform_fee_type' => 'percentage',
            'platform_fee_percent' => 10,
            'advisor_fee_percent' => 10,
            'ownership_type' => 'individual',
        ]);

        $response->assertSuccessful();

        $account = InvestmentAccount::where('user_id', $this->user->id)->firstOrFail();
        expect((float) $account->platform_fee_percent)->toEqual(10.0)
            ->and((float) $account->advisor_fee_percent)->toEqual(10.0);
    });
});
