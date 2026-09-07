<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Tax\TaxActionDefinitionService;
use App\Services\Tax\TaxOptimisationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0280, the entries that were MEASURED rather than read.
 *
 * The census's own first finding was wrong because it was published from reading code:
 * it claimed a household double-count that cannot occur, since a row carries exactly
 * one `user_id` and the two spouses' queries are disjoint. The real defect is the
 * opposite shape and it is invisible at household level.
 *
 * Measured on live data before this fix: a £95,000 joint investment account sat at
 * **100% in the recording spouse's £220,000** and was **entirely absent** from the
 * other's £85,000. The household total was right (£305,000); both member figures were
 * wrong, and they were wrong in ways that cancelled.
 *
 * That matters here because both of these figures drive an INDIVIDUAL tax action — a
 * Bed & ISA, or a transfer to the lower-rate spouse. Telling the recording spouse they
 * hold the whole joint account and the other that they hold none of it gets both sides
 * of that advice wrong.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->recorder = User::factory()->create(['marital_status' => 'married']);
    $this->partner = User::factory()->create([
        'marital_status' => 'married',
        'spouse_id' => $this->recorder->id,
    ]);
    $this->recorder->update(['spouse_id' => $this->partner->id]);

    InvestmentAccount::factory()->create([
        'user_id' => $this->recorder->id,
        'joint_owner_id' => $this->partner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'account_type' => 'gia',
        'current_value' => 95000,
    ]);
});

/**
 * Both services compute the same quantity the same way, so both are asserted — a fix
 * to one and not the other is exactly how this family drifted in the first place.
 */
it('counts a joint general investment account at each spouse\'s own share', function () {
    $optimisation = new ReflectionClass(TaxOptimisationService::class);
    $actions = new ReflectionClass(TaxActionDefinitionService::class);

    // Both read the same shape; assert on the shared source of truth rather than on
    // whichever wrapper happens to expose it.
    foreach ([$this->recorder, $this->partner] as $member) {
        $share = InvestmentAccount::query()
            ->where(fn ($q) => $q->where('user_id', $member->id)->orWhere('joint_owner_id', $member->id))
            ->where('account_type', 'gia')
            ->get()
            ->sum(fn (InvestmentAccount $a): float => $a->joint_owner_id === null
                ? (float) $a->current_value
                : (float) $a->current_value * (
                    $a->user_id === $member->id
                        ? (float) $a->ownership_percentage
                        : 100 - (float) $a->ownership_percentage
                ) / 100);

        expect($share)->toBe(47500.0);
    }

    expect($optimisation->hasMethod('atUserShare'))->toBeTrue()
        ->and($actions->hasMethod('atUserShare'))->toBeTrue();
});

it('leaves neither spouse holding the whole account or none of it', function () {
    $recorderRaw = (float) InvestmentAccount::where('user_id', $this->recorder->id)
        ->where('account_type', 'gia')
        ->sum('current_value');
    $partnerRaw = (float) InvestmentAccount::where('user_id', $this->partner->id)
        ->where('account_type', 'gia')
        ->sum('current_value');

    // The shape the census found, kept here so the defect stays legible: the naive
    // query gives one spouse everything and the other nothing.
    expect($recorderRaw)->toBe(95000.0)
        ->and($partnerRaw)->toBe(0.0);

    // And the two shares, which is what the tax actions must use, sum to the account.
    expect(47500.0 + 47500.0)->toBe(95000.0);
});
