<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A Marriage Allowance the couple has made (the Claim Marriage Allowance action
 * marked done by either partner) reaches the Income tab: the receiver's Income
 * Tax falls by the basic rate × the transferable amount, never below nil (ITA
 * 2007 s55B(1),(3), s23 Step 6), and the giver's Personal Allowance falls by
 * the transferable amount (s55B(6)).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function claimMarriageAllowance(User $by): void
{
    RecommendationTracking::create([
        'user_id' => $by->id,
        'recommendation_id' => TaxStrategyMath::MARRIAGE_ALLOWANCE_ACTION_ID,
        'module' => 'tax',
        'recommendation_text' => 'Claim Marriage Allowance',
        'status' => 'completed',
        'completed_at' => now(),
    ]);
}

function incomeTabSummary(User $user): array
{
    return app(UserProfileService::class)->incomeAndTaxFor($user->fresh())['detailed_tax_breakdown']['summary'];
}

function marriageAllowanceReduction(): float
{
    $math = app(TaxStrategyMath::class);

    return round($math->marriageAllowanceAmount() * $math->bandRateForBand('basic'), 2);
}

it('takes the Marriage Allowance off the receiver\'s Income Tax once it is claimed', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 35000,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 0]);

    $before = incomeTabSummary($user);
    expect($before['marriage_allowance_reduction'])->toBe(0.0);

    claimMarriageAllowance($user);
    $after = incomeTabSummary($user);

    expect($after['marriage_allowance_reduction'])->toBe(marriageAllowanceReduction())
        ->and($after['total_income_tax'])->toBe(round($before['total_income_tax'] - marriageAllowanceReduction(), 2))
        ->and($after['net_income'])->toBe(round($before['net_income'] + marriageAllowanceReduction(), 2));
});

it('never takes the receiver\'s Income Tax below nil', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => $pa + 430,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 0]);
    $tax = incomeTabSummary($user)['total_income_tax'];
    expect($tax)->toBeLessThan(marriageAllowanceReduction());

    claimMarriageAllowance($user);
    $after = incomeTabSummary($user);

    expect($after['marriage_allowance_reduction'])->toBe($tax)
        ->and($after['total_income_tax'])->toBe(0.0);
});

it('reduces the giver\'s Personal Allowance and gives the receiver the reduction when either partner claims it', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $amount = app(TaxStrategyMath::class)->marriageAllowanceAmount();

    $receiver = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 35000]);
    $giver = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 12000]);
    $receiver->update(['spouse_id' => $giver->id]);
    $giver->update(['spouse_id' => $receiver->id]);

    $giverBefore = incomeTabSummary($giver);
    claimMarriageAllowance($giver);

    $giverAfter = incomeTabSummary($giver);
    expect($giverAfter['personal_allowance'])->toBe(round($pa - $amount, 2))
        ->and($giverAfter['marriage_allowance_transferred'])->toBe(round($amount, 2))
        ->and($giverAfter['marriage_allowance_reduction'])->toBe(0.0)
        ->and($giverAfter['total_income_tax'])->toBe(round((12000 - ($pa - $amount)) * app(TaxStrategyMath::class)->bandRateForBand('basic'), 2))
        ->and($giverAfter['total_income_tax'])->toBeGreaterThan($giverBefore['total_income_tax']);

    expect(incomeTabSummary($receiver)['marriage_allowance_reduction'])->toBe(marriageAllowanceReduction());

    $taxPosition = app(UserProfileService::class)->getCompleteProfile($giver->fresh())['income_summary']['user']['tax_position'];
    expect($taxPosition['personal_allowance'])->toBe(round($pa - $amount, 2))
        ->and($taxPosition['personal_allowance_label'])->toBe('Personal allowance after Marriage Allowance');
});

it('changes nothing until the action is marked done', function () {
    $receiver = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 35000]);
    $giver = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 12000]);
    $receiver->update(['spouse_id' => $giver->id]);
    $giver->update(['spouse_id' => $receiver->id]);

    expect(app(TaxStrategyMath::class)->marriageAllowanceClaimFor($receiver->fresh()))->toBeNull()
        ->and(incomeTabSummary($giver)['marriage_allowance_transferred'])->toBe(0.0);
});

it('shows the Marriage Allowance as used on the Tax plan once it is claimed', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 35000,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 0]);
    $row = fn () => collect(app(TaxStrategyCalculator::class)->calculate($user->fresh())->userAllowances)->firstWhere('key', 'marriage_allowance');

    expect($row()['used'])->toBe(0.0);

    claimMarriageAllowance($user);

    expect($row()['used'])->toBe(app(TaxStrategyMath::class)->marriageAllowanceAmount());
});

it('stops offering Marriage Allowance as a saving once it is claimed', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 35000,
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 0]);
    $offered = fn () => collect(app(TaxStrategyCalculator::class)->calculate($user->fresh())->recommendations)
        ->contains('type', 'marriage_allowance_transfer');

    expect($offered())->toBeTrue();

    claimMarriageAllowance($user);

    expect($offered())->toBeFalse();
});
