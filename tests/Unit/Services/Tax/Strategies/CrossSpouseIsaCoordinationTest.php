<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\Tax\Strategies\CrossSpouseBundleStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * "Use your spouse's ISA allowance" read only the ISA balance typed about the
 * spouse. A partner whose linked account shares its data is no longer asked
 * (their ISAs are on their own account), so their own records answer it.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

/** @return array{0: User, 1: User} a dual-earner user who has used this year's ISA allowance, linked to a partner */
function isaCoordinationCouple(): array
{
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'employment_status' => 'full_time', 'annual_employment_income' => 60000, 'household_calculation_mode' => 'dual_earner']);
    $partner = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'employment_status' => 'full_time', 'annual_employment_income' => 30000]);
    app(SpouseLinkingService::class)->establishAcceptedLink($user, $partner);
    TaxStrategyHouseholdInput::updateOrCreate(['user_id' => $user->id], ['spouse_annual_income' => 30000]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'isa', 'isa_subscription_current_year' => 20000]);

    return [$user->fresh(), $partner->fresh()];
}

function isaCoordinationTypes(User $user): array
{
    $context = new TaxStrategyContext($user, null, $user->taxStrategyHouseholdInput, 'dual_earner');

    return array_map(fn ($rec) => $rec->type, app(CrossSpouseBundleStrategy::class)->generate($context));
}

it('offers the partner\'s ISA allowance when their own account holds no ISA', function (): void {
    [$user] = isaCoordinationCouple();

    expect(isaCoordinationTypes($user))->toContain('isa_coordination');
});

it('does not offer it when their own account holds an ISA', function (): void {
    [$user, $partner] = isaCoordinationCouple();
    InvestmentAccount::factory()->create(['user_id' => $partner->id, 'account_type' => 'isa']);

    expect(isaCoordinationTypes($user))->not->toContain('isa_coordination');
});
