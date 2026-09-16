<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Onboarding\SpouseHoldingTransfer;
use App\Services\Onboarding\SpouseLinkingService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * CSJ 2026-09-16: the spouse facts held on the inviter's account during
 * onboarding are copied onto the spouse's account the moment it links, once.
 * The ISA is assumed to be a Stocks and Shares ISA.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    Mail::fake();
    Notification::fake();
});

afterEach(function (): void {
    Mockery::close();
});

it('copies the held spouse facts onto the linked account once', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'household_calculation_mode' => 'dual_earner']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => null, 'employment_status' => null, 'annual_employment_income' => null, 'annual_self_employment_income' => null]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $requester->id, 'spouse_annual_income' => 32000, 'spouse_employment_status' => 'full_time',
        'spouse_isa_balance' => 9000, 'spouse_isa_provider' => 'Halifax',
        'spouse_pension_input_annual' => 2400, 'spouse_existing_pension_balance' => 31000, 'spouse_pension_provider' => 'Aviva',
        'spouse_existing_savings_balance' => 6500, 'spouse_existing_investment_balance' => 12000,
    ]);
    FamilyMember::create(['user_id' => $requester->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22', 'annual_income' => 38000]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    $spouse->refresh();
    expect($spouse->spouse_id)->toBe($requester->id)
        ->and($spouse->date_of_birth->format('Y-m-d'))->toBe('1985-08-22')
        ->and($spouse->employment_status)->toBe('full_time')
        ->and((float) $spouse->annual_employment_income)->toBe(32000.0);

    $savings = SavingsAccount::where('user_id', $spouse->id)->get();
    $investments = InvestmentAccount::where('user_id', $spouse->id)->get();
    $pensions = DCPension::where('user_id', $spouse->id)->get();
    expect($savings)->toHaveCount(1)
        ->and((float) $savings[0]->current_balance)->toBe(6500.0)
        ->and($investments)->toHaveCount(2)
        ->and((float) $investments->firstWhere('account_type', 'isa')->current_value)->toBe(9000.0)
        ->and($investments->firstWhere('account_type', 'isa')->provider)->toBe('Halifax')
        ->and((float) $investments->firstWhere('account_type', 'gia')->current_value)->toBe(12000.0)
        ->and($pensions)->toHaveCount(1)
        ->and($pensions[0]->provider)->toBe('Aviva')
        ->and((float) $pensions[0]->current_fund_value)->toBe(31000.0)
        ->and((float) $pensions[0]->monthly_contribution_amount)->toBe(200.0)
        ->and(TaxStrategyHouseholdInput::where('user_id', $requester->id)->first()->spouse_holding_transferred_at)->not->toBeNull();

    // A second link attempt copies nothing more.
    app(SpouseHoldingTransfer::class)->transfer($requester->fresh(), $spouse->fresh());
    expect(SavingsAccount::where('user_id', $spouse->id)->count())->toBe(1)
        ->and(InvestmentAccount::where('user_id', $spouse->id)->count())->toBe(2)
        ->and(DCPension::where('user_id', $spouse->id)->count())->toBe(1);
});

it('copies the journey spouse card alone when no household row was filled', function (): void {
    $requester = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => null, 'employment_status' => 'full_time', 'annual_employment_income' => null]);
    FamilyMember::create(['user_id' => $requester->id, 'relationship' => 'spouse', 'first_name' => 'Robin', 'last_name' => 'Walk', 'date_of_birth' => '1985-08-22', 'annual_income' => 38000]);

    app(SpouseLinkingService::class)->establishAcceptedLink($requester, $spouse);

    $spouse->refresh();
    expect($spouse->date_of_birth->format('Y-m-d'))->toBe('1985-08-22')
        ->and((float) $spouse->annual_employment_income)->toBe(38000.0)
        ->and(SavingsAccount::where('user_id', $spouse->id)->count())->toBe(0);
});
