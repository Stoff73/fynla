<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Documents\FieldMappers\MortgageMapper;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/**
 * W-0328 — capped and offset are real UK products the column could not hold.
 *
 * CSJ decided both should be supported. The scope is deliberately **record the type**,
 * not model the arithmetic: `monthly_payment` is user-entered (`StoreMortgageRequest:63`),
 * stored as given and read back as stored, and `calculateMonthlyPayment()` never touches
 * it — it backs a standalone what-if endpoint. An offset borrower enters the payment
 * their lender actually charges, so the figure already has the offset in it. Deriving
 * it again would put a second mechanism against a figure the user stated, which is what
 * W-0228 was ruled to end.
 *
 * Adding a `rate_type` value touches NINE places, and the ones that get missed are not
 * the obvious four. These pin the two that decide whether `/m` and native can record
 * the value at all — `resources/mobile/api.js` has no post/put/patch helper, so Fyn is
 * `/m`'s only write path — and the document mapper, which silently rewrote an
 * unrecognised type to `variable`.
 */
beforeEach(function () {
    // The mortgage write path runs through DbTierGate, which reads TierConfiguration.
    // Without it the store 404s on a ModelNotFoundException for the tier row, which
    // the handler renders as "Endpoint not found" — a 404 that looks like a missing
    // route and is not one.
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function mortgageFor(User $user): Mortgage
{
    $property = Property::factory()->create(['user_id' => $user->id]);

    return Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'rate_type' => 'fixed',
    ]);
}

describe('the column can hold what the app now offers', function () {
    it('stores both new rate types', function (string $rateType) {
        $mortgage = mortgageFor($this->user);

        $mortgage->update(['rate_type' => $rateType]);

        expect($mortgage->fresh()->rate_type)->toBe($rateType);
    })->with(['capped', 'offset']);

    it('keeps the five existing values working', function (string $rateType) {
        $mortgage = mortgageFor($this->user);

        $mortgage->update(['rate_type' => $rateType]);

        expect($mortgage->fresh()->rate_type)->toBe($rateType);
    })->with(['fixed', 'variable', 'tracker', 'discount', 'mixed']);

    it('still refuses a value that is not a rate type at all', function () {
        expect(fn () => mortgageFor($this->user)->update(['rate_type' => 'wishful']))
            ->toThrow(QueryException::class);
    });
});

describe('every write path accepts them, not just the web form', function () {
    it('accepts them through the mortgage API', function (string $rateType) {
        $property = Property::factory()->create(['user_id' => $this->user->id]);

        // Create is nested under the property: POST /api/properties/{id}/mortgages
        $this->postJson("/api/properties/{$property->id}/mortgages", [
            'property_id' => $property->id,
            'lender_name' => 'Coventry Building Society',
            'outstanding_balance' => 180000,
            'interest_rate' => 4.25,
            'rate_type' => $rateType,
            'mortgage_type' => 'repayment',
            'remaining_term_months' => 240,
            'monthly_payment' => 1120,
        ])->assertSuccessful();

        expect(Mortgage::query()->latest('id')->first()->rate_type)->toBe($rateType);
    })->with(['capped', 'offset']);

    // The site that would be missed. Fyn is /m's ONLY write path — resources/mobile/api.js
    // has no post, put or patch helper — so a value absent from CoordinatingAgent's own
    // copies of this list is a value web can record and /m and native cannot. That is a
    // Rule 19 break shipped by omission, and it is exactly how estate_will reached
    // production without a native route (W-0044).
    it('is offered by both of Fyn\'s own validation copies', function (string $rateType) {
        $source = file_get_contents(base_path('app/Agents/CoordinatingAgent.php'));

        $lists = preg_match_all(
            "/Rule::in\(\['fixed', 'variable', 'tracker', 'discount', 'mixed'[^\]]*\]\)/",
            $source,
            $matches
        );

        expect($lists)->toBe(2, 'CoordinatingAgent should carry exactly two rate_type lists');

        foreach ($matches[0] as $list) {
            expect($list)->toContain("'".$rateType."'");
        }
    })->with(['capped', 'offset']);

    // The ninth site, found only by sweeping rather than by the item's own list.
    // parseEnum() coerces anything it does not recognise to its default, so before
    // this a capped mortgage read off a statement was silently stored as variable.
    it('does not rewrite them to variable when read off a document', function (string $rateType) {
        $mapped = app(MortgageMapper::class)->map(['rate_type' => $rateType]);

        expect($mapped['rate_type'])->toBe($rateType);
    })->with(['capped', 'offset']);
});

describe('what this deliberately does not do', function () {
    // The scope decision, pinned so a later reader does not "finish" it. rate_type has
    // never driven arithmetic for any of its five existing values either.
    it('does not add an offset-savings link to the schema', function () {
        expect(Schema::hasColumn('savings_accounts', 'offset_mortgage_id'))->toBeFalse()
            ->and(Schema::hasColumn('mortgages', 'offset_benefit'))->toBeFalse();
    });

    it('leaves the payment exactly as the user entered it', function () {
        $mortgage = mortgageFor($this->user);
        $mortgage->update(['rate_type' => 'offset', 'monthly_payment' => 1234.56]);

        // No offset arithmetic runs over the top of a stated figure.
        expect((float) $mortgage->fresh()->monthly_payment)->toBe(1234.56);
    });
});
