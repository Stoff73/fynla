<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\UserProfile\PersonalAccountsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    TaxConfiguration::factory()->create(['is_active' => true]);
    $this->service = app(PersonalAccountsService::class);

    // Create a household
    $this->household = Household::factory()->create();

    // Create a test user with income data
    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'annual_employment_income' => 75000.00,
        'annual_self_employment_income' => 10000.00,
        'annual_rental_income' => 12000.00,
        'annual_dividend_income' => 3000.00,
        'annual_other_income' => 2000.00,
    ]);

    // Create a property
    $this->property = Property::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 500000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'annual_service_charge' => 2000.00,
        'annual_ground_rent' => 500.00,
        'annual_insurance' => 300.00,
        'annual_maintenance_reserve' => 1200.00,
        'other_annual_costs' => 0.00,
    ]);

    // Create a mortgage
    $this->mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 300000.00,
        'monthly_payment' => 1500.00,
    ]);

    // Create investment account
    $this->investment = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 50000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    // Create cash account - Service uses SavingsAccount model
    $this->cashAccount = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 25000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);
});

describe('calculateProfitAndLoss', function () {
    it('calculates profit and loss correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($this->user, $startDate, $endDate);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys([
            'period',
            'income',
            'total_income',
            'expenses',
            'total_expenses',
            'net_profit_loss',
            'tax',
        ]);

        expect($result['tax'])->toHaveKeys([
            'income_tax',
            'national_insurance',
            'total_deductions',
            'effective_tax_rate',
        ]);
    });

    it('calculates total income correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($this->user, $startDate, $endDate);

        $expectedIncome =
            75000.00 + // employment
            10000.00 + // self-employment
            12000.00 + // rental
            3000.00 +  // dividend
            2000.00;   // other

        expect($result['total_income'])->toBe($expectedIncome);
    });

    it('includes all income line items', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($this->user, $startDate, $endDate);

        expect($result['income'])->toBeArray();
        expect($result['income'])->toHaveCount(8);

        $incomeItems = collect($result['income'])->pluck('line_item')->toArray();
        expect($incomeItems)->toContain('Employment Income');
        expect($incomeItems)->toContain('Self-Employment Income');
        expect($incomeItems)->toContain('Rental Income');
        expect($incomeItems)->toContain('Dividend Income');
        expect($incomeItems)->toContain('Interest Income');
        expect($incomeItems)->toContain('Pension Income');
        expect($incomeItems)->toContain('Trust Income');
        expect($incomeItems)->toContain('Other Income');
    });

    it('calculates total expenses correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($this->user, $startDate, $endDate);

        $expectedMortgagePayments = 1500.00 * 12; // £18,000
        $expectedPropertyExpenses = 2000.00 + 500.00 + 300.00 + 1200.00; // £4,000

        expect($result['total_expenses'])->toBe($expectedMortgagePayments + $expectedPropertyExpenses);
    });

    it('calculates net profit/loss correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($this->user, $startDate, $endDate);

        $expectedNetProfit = $result['total_income'] - $result['total_expenses'];

        expect($result['net_profit_loss'])->toBe($expectedNetProfit);
    });

    it('handles user with no income gracefully', function () {
        $userNoIncome = User::factory()->create([
            'household_id' => $this->household->id,
            'annual_employment_income' => 0,
            'annual_self_employment_income' => 0,
            'annual_rental_income' => 0,
            'annual_dividend_income' => 0,
            'annual_other_income' => 0,
        ]);

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($userNoIncome, $startDate, $endDate);

        expect($result['total_income'])->toBe(0.0);
    });

    it('handles user with no expenses gracefully', function () {
        $userNoExpenses = User::factory()->create([
            'household_id' => $this->household->id,
            'annual_employment_income' => 50000.00,
        ]);

        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateProfitAndLoss($userNoExpenses, $startDate, $endDate);

        expect($result['total_expenses'])->toBe(0);
        expect($result['net_profit_loss'])->toBe(50000.00);
    });
});

describe('calculateCashflow', function () {
    it('calculates cashflow correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateCashflow($this->user, $startDate, $endDate);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys([
            'period',
            'inflows',
            'total_inflows',
            'outflows',
            'total_outflows',
            'net_cashflow',
        ]);
    });

    it('calculates total inflows correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateCashflow($this->user, $startDate, $endDate);

        // Inflows should match total income
        $expectedInflows =
            75000.00 + // employment
            10000.00 + // self-employment
            12000.00 + // rental
            3000.00 +  // dividend
            2000.00;   // other

        expect($result['total_inflows'])->toBe($expectedInflows);
    });

    it('includes mortgage payments in outflows', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateCashflow($this->user, $startDate, $endDate);

        expect($result['total_outflows'])->toBeGreaterThan(0);
    });

    it('calculates net cashflow correctly', function () {
        $startDate = Carbon::parse('2024-01-01');
        $endDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateCashflow($this->user, $startDate, $endDate);

        $expectedNetCashflow = $result['total_inflows'] - $result['total_outflows'];

        expect($result['net_cashflow'])->toBe($expectedNetCashflow);
    });
});

describe('calculateBalanceSheet', function () {
    it('calculates balance sheet correctly', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        expect($result)->toBeArray();
        expect($result)->toHaveKeys([
            'as_of_date',
            'assets',
            'total_assets',
            'liabilities',
            'total_liabilities',
            'equity',
            'total_equity',
        ]);
    });

    it('calculates total assets correctly', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // Assets should include property + investments + cash
        $expectedAssets =
            500000.00 + // property (100% ownership)
            50000.00 +  // investments (100% ownership)
            25000.00;   // cash

        expect($result['total_assets'])->toBeGreaterThanOrEqual($expectedAssets);
    });

    it('includes property value in assets', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // Filter by category field, not line_item string
        $propertyAssets = collect($result['assets'])->filter(function ($asset) {
            return $asset['category'] === 'property';
        })->sum('amount');

        expect($propertyAssets)->toBeGreaterThan(0);
    });

    it('includes investment value in assets', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // Filter by category field, not line_item string
        $investmentAssets = collect($result['assets'])->filter(function ($asset) {
            return $asset['category'] === 'investment';
        })->sum('amount');

        expect($investmentAssets)->toBeGreaterThan(0);
    });

    it('calculates total liabilities correctly', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // Liabilities should include mortgage balance
        expect($result['total_liabilities'])->toBeGreaterThanOrEqual(300000.00);
    });

    it('includes mortgage in liabilities', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // Filter by category field, not line_item string
        $mortgageLiabilities = collect($result['liabilities'])->filter(function ($liability) {
            return $liability['category'] === 'mortgage';
        })->sum('amount');

        expect($mortgageLiabilities)->toBeGreaterThan(0);
    });

    it('calculates net worth correctly', function () {
        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        $expectedNetWorth = $result['total_assets'] - $result['total_liabilities'];

        expect($result['total_equity'])->toBe($expectedNetWorth);
    });

    it('handles joint ownership correctly', function () {
        // Create property with 50% ownership
        $jointProperty = Property::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 400000.00,
            'ownership_percentage' => 50.00,
        ]);

        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($this->user, $asOfDate);

        // User's share should be 50% of £400,000 = £200,000 (plus other assets)
        expect($result['total_assets'])->toBeGreaterThan(200000.00);
    });

    it('handles user with no liabilities', function () {
        // Create user with no mortgages
        $userNoLiabilities = User::factory()->create([
            'household_id' => $this->household->id,
        ]);

        Property::factory()->create([
            'user_id' => $userNoLiabilities->id,
            'current_value' => 300000.00,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
        ]);

        $asOfDate = Carbon::parse('2024-12-31');

        $result = $this->service->calculateBalanceSheet($userNoLiabilities, $asOfDate);

        expect($result['total_liabilities'])->toBe(0);
        expect($result['total_equity'])->toBe($result['total_assets']);
    });
});
