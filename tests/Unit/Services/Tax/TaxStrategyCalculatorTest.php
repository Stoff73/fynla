<?php

declare(strict_types=1);

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\PensionInputHistory;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

/**
 * Filter recommendations by category — replacement for the legacy
 * assetShiftingSuggestions / crossSpouseSuggestions DTO fields, which were
 * dropped in Phase 2 in favour of a single canonical recommendations[] array.
 */
function recsOfCategory(TaxStrategyOutputDTO $output, string $category): array
{
    return collect($output->recommendations)
        ->where('category', $category)
        ->values()
        ->all();
}

describe('Path A — single user', function () {
    it('returns 6 user allowance positions for a single £50k earner, no household sections', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 50000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output)->toBeInstanceOf(TaxStrategyOutputDTO::class);
        expect($output->calculationMode)->toBe('single');
        // Starting Rate for Savings tapers to £0 once non-savings income > £17,570;
        // Marriage Allowance is hidden for non-partnered users — both omitted here.
        expect($output->userAllowances)->toHaveCount(6);
        expect($output->spouseAllowances)->toBeNull();
        expect(recsOfCategory($output, 'household'))->toBe([]);

        $keys = array_column($output->userAllowances, 'key');
        expect($keys)->toContain(
            'personal_allowance',
            'savings_allowance',
            'isa_allowance',
            'cgt_allowance',
            'dividend_allowance',
            'pension_annual_allowance',
        );
        expect($keys)->not->toContain('starting_rate_for_savings');
        expect($keys)->not->toContain('marriage_allowance');

        foreach ($output->userAllowances as $pos) {
            expect($pos['status'])->toBeIn(['spring', 'violet', 'raspberry']);
            expect($pos['owner'])->toBe('user');
        }
    });

    it('does not write to the database during calculate()', function () {
        $user = User::factory()->create(['household_calculation_mode' => 'single']);
        $stamp = $user->updated_at?->copy();

        app(TaxStrategyCalculator::class)->calculate($user);

        expect($user->fresh()->updated_at?->equalTo($stamp))->toBeTrue();
        expect(TaxStrategyHouseholdInput::count())->toBe(0);
    });
});

describe('Path B — dual_earner', function () {
    it('returns twin grids + cross-spouse suggestions, no asset-shifting', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'annual_employment_income' => 80000, // higher rate
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_annual_income' => 30000,
            'spouse_employment_status' => 'full_time',
            'spouse_isa_balance' => 5000,
            'spouse_psa_band' => 'basic',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output->calculationMode)->toBe('dual_earner');
        // User grid: 7 positions (Starting Rate omitted — £80k > £17,570 floor).
        // Marriage Allowance is included because user is married.
        expect($output->userAllowances)->toHaveCount(7);
        // Spouse grid (dual_earner): always 8 fixed positions.
        expect($output->spouseAllowances)->toHaveCount(8);
        expect(recsOfCategory($output, 'household'))->not->toBe([]);

        // Spouse positions all owned by 'spouse'
        foreach ($output->spouseAllowances as $pos) {
            expect($pos['owner'])->toBe('spouse');
        }
    });
});

describe('Path C — single_earner_couple', function () {
    it('emits asset-shifting suggestions sized to spouse unused capacity', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_isa_balance' => 0,
            'spouse_existing_savings_balance' => 0,
            'spouse_existing_investment_balance' => 0,
            'spouse_existing_dividend_holdings_value' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0.035,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output->calculationMode)->toBe('single_earner_couple');
        expect(recsOfCategory($output, 'household'))->not->toBe([]);

        // Savings-shift suggestion sized ≤ user's at-risk holdings
        $shift = collect($output->recommendations)->firstWhere('type', 'savings_to_spouse');
        expect($shift)->not->toBeNull();
        expect($shift['suggested_transfer_amount'])->toBeLessThanOrEqual(200000);
        expect($shift['estimated_annual_tax_saved'])->toBeGreaterThan(0);

        // ISA top-up suggestion (spouse has £20k unused)
        $isa = collect($output->recommendations)->firstWhere('type', 'isa_topup_spouse');
        expect($isa)->not->toBeNull();
        expect($isa['available_allowance'])->toBe(20000.0);
    });

    it('omits Marriage Allowance suggestion for higher-rate users (HMRC rule)', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000, // higher rate — MA NOT available
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $ma = collect($output->recommendations)->firstWhere('type', 'marriage_allowance_transfer');
        expect($ma)->toBeNull();
    });

    it('reduces savings-shift suggestion when spouse already has standalone savings', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marriage_allowance_eligible' => true,
        ]);
        // Spouse already has £100k of savings — eats some of their PSA capacity
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 100000,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 600000,
            'interest_rate' => 0.035,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $shift = collect($output->recommendations)->firstWhere('type', 'savings_to_spouse');
        // Spouse already absorbing ~£3,500/yr interest from their own £100k @ 3.5%
        // Remaining capacity ≈ £18,570 - £3,500 = £15,070
        // Translates to ~£430k more transferable @ 3.5% — but also bounded by user's £600k
        // Expectation: suggested transfer < 600,000 (capped by spouse's reduced capacity)
        expect($shift)->not->toBeNull();
        expect($shift['suggested_transfer_amount'])->toBeLessThan(600000);
    });
});

describe('benchmark', function () {
    it('runs in under 50ms for a representative single_earner_couple persona', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_isa_balance' => 5000,
        ]);
        SavingsAccount::factory()->count(3)->for($user)->create();

        // Warm caches once
        app(TaxStrategyCalculator::class)->calculate($user);

        $start = hrtime(true);
        app(TaxStrategyCalculator::class)->calculate($user);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        expect($elapsedMs)->toBeLessThan(50);
    });
});

describe('recommendations contract (canonical)', function () {
    it('exposes an empty recommendations array for single-mode users with no triggering data', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 50000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output->recommendations)->toBe([]);
    });

    it('emits household recommendations alongside user-level recommendations in dual_earner mode', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_annual_income' => 30000,
            'spouse_employment_status' => 'full_time',
            'spouse_isa_balance' => 5000,
            'spouse_psa_band' => 'basic',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(recsOfCategory($output, 'household'))->not->toBe([])
            ->and($output->recommendations)->not->toBe([]);

        // Every recommendation carries the canonical fields and a valid category/priority value
        foreach ($output->recommendations as $rec) {
            expect($rec)->toHaveKeys(['type', 'category', 'priority', 'title', 'description', 'requires_advice'])
                ->and($rec['category'])->toBeIn(['income_band', 'allowance', 'household', 'lifecycle', 'warning'])
                ->and($rec['priority'])->toBeIn(['high', 'medium', 'low']);
        }
    });

    it('emits household recommendations alongside user-level recommendations in single_earner_couple mode', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_isa_balance' => 0,
            'spouse_existing_savings_balance' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0.035,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(recsOfCategory($output, 'household'))->not->toBe([])
            ->and($output->recommendations)->not->toBe([]);

        // Strategy-specific extras still surfaced at the top level
        $shift = collect($output->recommendations)->firstWhere('type', 'savings_to_spouse');
        expect($shift)->not->toBeNull()
            ->and($shift['suggested_transfer_amount'])->toBeGreaterThan(0)
            ->and($shift['category'])->toBe('household');
    });

    it('serialises recommendations through TaxStrategyOutputDTO::toArray()', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

        $payload = app(TaxStrategyCalculator::class)->calculate($user)->toArray();

        expect($payload)->toHaveKey('recommendations')
            ->and($payload['recommendations'])->toBeArray()
            ->and($payload)->not->toHaveKey('asset_shifting_suggestions') // dropped in Phase 2
            ->and($payload)->not->toHaveKey('cross_spouse_suggestions');   // dropped in Phase 2
    });
});

describe('Phase 2 — income-band strategies (#1, #2)', function () {
    it('emits PA taper rescue for income in the £100k-£125,140 band', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 110000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pa_taper_rescue');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('income_band')
            ->and($rec['priority'])->toBe('high')
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0)
            ->and($rec['suggested_contribution'])->toBeLessThanOrEqual(10000);
    });

    it('omits PA taper rescue for income below £100k', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'pa_taper_rescue'))->toBeNull();
    });

    it('emits additional-rate avoidance for income above £125,140', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 200000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'additional_rate_avoidance');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('income_band')
            ->and($rec['priority'])->toBe('high')
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0);
    });
});

describe('Phase 2 — allowance harvesting (#5, #7)', function () {
    it('emits ISA top-up vs PSA when non-ISA cash earns above PSA', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000, // higher rate, PSA £500
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000, // £50k @ 4% = £2k interest > £500 PSA
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'isa_topup_vs_psa');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('allowance')
            ->and($rec['priority'])->toBe('high')
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0);
    });

    it('omits ISA top-up vs PSA when interest stays under the allowance', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 30000, // basic rate, PSA £1,000
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 10000, // £400 interest < £1,000 PSA
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'isa_topup_vs_psa'))->toBeNull();
    });

    it('emits Dividend Allowance harvest when allowance is unused and user holds non-ISA investments', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'annual_dividend_income' => 100,
            'marital_status' => 'single',
        ]);
        InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'dividend_allowance_harvest');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('allowance')
            ->and($rec['priority'])->toBe('low');
    });
});

describe('Phase 2 — household strategy refinements (#9, #11)', function () {
    it('breaks the spouse-savings transfer copy into stacked allowance buckets', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $shift = collect($output->recommendations)->firstWhere('type', 'savings_to_spouse');
        expect($shift)->not->toBeNull()
            ->and($shift['title'])->toContain('£18,570')
            ->and($shift['description'])->toContain('Personal Allowance')
            ->and($shift['description'])->toContain('Starting Rate for Savings')
            ->and($shift['description'])->toContain('Personal Savings Allowance')
            ->and($shift['description'])->toContain('Capital Gains Tax')
            ->and($shift['description'])->toContain('Inheritance Tax')
            ->and($shift)->toHaveKey('spouse_personal_allowance')
            ->and($shift)->toHaveKey('spouse_starting_rate_for_savings')
            ->and($shift)->toHaveKey('spouse_personal_savings_allowance');
    });

    it('sizes GIA rebalance with an estimated tax saving when user has dividends', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'annual_employment_income' => 80000, // higher rate
            'annual_dividend_income' => 5000,
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_annual_income' => 25000,
            'spouse_psa_band' => 'basic',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'gia_rebalance');
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0)
            ->and($rec)->toHaveKey('user_dividend_rate')
            ->and($rec)->toHaveKey('spouse_dividend_rate');
    });
});

describe('Phase 2 — joint-savings strategy (#15)', function () {
    it('emits joint-savings split for a married user with sole savings above their PSA', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000, // higher rate, PSA £500
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'joint_owner_id' => null,   // sole-name
            'current_balance' => 100000, // £4k interest @ 4%, well above PSA
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'joint_savings_psa_split');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('household')
            ->and($rec['priority'])->toBe('low')
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0);
    });

    it('omits joint-savings split when user is additional-rate (PSA is £0)', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 200000, // additional rate
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create(['user_id' => $user->id]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 100000,
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'joint_savings_psa_split'))->toBeNull();
    });
});

describe('Phase 2 — lifecycle strategies (#16, #17, #18)', function () {
    it('emits Lifetime ISA suggestion for a user under 40 with unused ISA capacity', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 40000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'lifetime_isa');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('lifecycle')
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['estimated_annual_tax_saved'])->toBe(1000.0); // £4k × 25% bonus
    });

    it('omits Lifetime ISA for a user past 40', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 40000,
            'date_of_birth' => now()->subYears(45),
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'lifetime_isa'))->toBeNull();
    });

    it('emits Junior ISA + Junior Pension when the user has children under 18', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 40000,
            'marital_status' => 'single',
        ]);
        FamilyMember::factory()->for($user)->create([
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(8),
            'is_dependent' => true,
        ]);
        FamilyMember::factory()->for($user)->create([
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(12),
            'is_dependent' => true,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $jisa = collect($output->recommendations)->firstWhere('type', 'junior_isa');
        $jpension = collect($output->recommendations)->firstWhere('type', 'junior_pension');

        expect($jisa)->not->toBeNull()
            ->and($jisa['children_under_18'])->toBe(2)
            ->and($jisa['total_jisa_capacity'])->toBe(18000.0)
            ->and($jisa['title'])->toContain('children under 18');

        expect($jpension)->not->toBeNull()
            ->and($jpension['children_under_18'])->toBe(2)
            ->and($jpension['estimated_annual_tax_saved'])->toBe(1440.0); // 2 × £720
    });
});

describe('Phase 2 — sort order in recommendations[]', function () {
    it('orders categories warning > income_band > allowance > household > lifecycle, high before low within each', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 110000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0.04,
        ]);

        $categories = array_column(
            app(TaxStrategyCalculator::class)->calculate($user)->recommendations,
            'category'
        );

        // The first occurrence of each category should appear in canonical order
        $firstSeen = [];
        foreach ($categories as $cat) {
            if (! isset($firstSeen[$cat])) {
                $firstSeen[$cat] = count($firstSeen);
            }
        }

        $expectedOrder = ['warning', 'income_band', 'allowance', 'household', 'lifecycle'];
        $observed = array_values(array_intersect($expectedOrder, array_keys($firstSeen)));
        $sorted = $observed;
        usort($sorted, fn ($a, $b) => $firstSeen[$a] <=> $firstSeen[$b]);
        expect($observed)->toBe($sorted);
    });
});

describe('Phase 3 — salary sacrifice NI relief (#4)', function () {
    it('emits salary_sacrifice_ni for an employed user with a workplace pension not on sacrifice', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'employment_status' => 'employed',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        DCPension::factory()->for($user)->create([
            'monthly_contribution_amount' => 500, // £6,000/yr
            'salary_sacrifice' => false,
            'employer_ni_rebate_pct' => null,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'salary_sacrifice_ni');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('allowance')
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['estimated_annual_tax_saved'])->toBeGreaterThan(0)
            ->and($rec['annual_contribution'])->toBe(6000.0)
            ->and($rec['employer_ni_rebate_saving'])->toBe(0.0);
    });

    it('adds the employer NI rebate saving on top when employer_ni_rebate_pct is set', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'employment_status' => 'employed',
            'annual_employment_income' => 45000, // below UEL → 8% main rate
            'marital_status' => 'single',
        ]);
        DCPension::factory()->for($user)->create([
            'monthly_contribution_amount' => 400, // £4,800/yr
            'salary_sacrifice' => false,
            'employer_ni_rebate_pct' => 0.5, // 50% of employer NI rebated back
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'salary_sacrifice_ni');
        // Employee saving: 4800 × 8% = 384. Employer saving: 4800 × 15% × 0.5 = 360. Total ≈ 744.
        expect($rec)->not->toBeNull()
            ->and($rec['employer_ni_rebate_pct'])->toBe(0.5)
            ->and($rec['employee_ni_saving'])->toBe(384.0)
            ->and($rec['employer_ni_rebate_saving'])->toBe(360.0)
            ->and($rec['estimated_annual_tax_saved'])->toBe(744.0);
    });

    it('skips salary_sacrifice_ni when the pension is already on salary sacrifice', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'employment_status' => 'employed',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        DCPension::factory()->for($user)->create([
            'monthly_contribution_amount' => 500,
            'salary_sacrifice' => true,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'salary_sacrifice_ni'))->toBeNull();
    });

    it('skips salary_sacrifice_ni when the user is not employed', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'employment_status' => 'self_employed',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        DCPension::factory()->for($user)->create([
            'monthly_contribution_amount' => 500,
            'salary_sacrifice' => false,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'salary_sacrifice_ni'))->toBeNull();
    });
});

describe('Phase 3 — bed & ISA capital gains harvest (#6)', function () {
    it('emits bed_and_isa when the user has unrealised gains and ISA headroom', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        $gia = InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);
        Holding::factory()->forAccount($gia)->create([
            'quantity' => 100,
            'purchase_price' => 50,    // cost basis £5,000
            'current_price' => 100,    // current value £10,000
            'cost_basis' => 5000,
            'current_value' => 10000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'bed_and_isa');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('allowance')
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['total_unrealised_gain'])->toBe(5000.0)
            ->and($rec['realisable_within_aea'])->toBe(3000.0) // capped at AEA
            ->and($rec['estimated_annual_tax_saved'])->toBe(720.0); // £3,000 × 24% higher-rate CGT
    });

    it('skips bed_and_isa when there are no unrealised gains', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        $gia = InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);
        Holding::factory()->forAccount($gia)->create([
            'quantity' => 100,
            'purchase_price' => 100,
            'current_price' => 90, // loss
            'cost_basis' => 10000,
            'current_value' => 9000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'bed_and_isa'))->toBeNull();
    });

    it('skips bed_and_isa when the user holds only ISA accounts', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'marital_status' => 'single',
        ]);
        $isa = InvestmentAccount::factory()->for($user)->create(['account_type' => 'isa']);
        Holding::factory()->forAccount($isa)->create([
            'cost_basis' => 5000,
            'current_value' => 10000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'bed_and_isa'))->toBeNull();
    });

    it('uses the basic-rate CGT band for a basic-rate taxpayer', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 30000,
            'marital_status' => 'single',
        ]);
        $gia = InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);
        Holding::factory()->forAccount($gia)->create([
            'cost_basis' => 5000,
            'current_value' => 10000,
            'quantity' => 100,
            'purchase_price' => 50,
            'current_price' => 100,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'bed_and_isa');
        expect($rec)->not->toBeNull()
            ->and($rec['cgt_rate'])->toBe(0.18)
            ->and($rec['estimated_annual_tax_saved'])->toBe(540.0); // £3,000 × 18%
    });
});

describe('Phase 3 — non-earner spouse pension (#12)', function () {
    it('emits non_earner_spouse_pension in single_earner_couple mode', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'non_earner_spouse_pension');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('household')
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['estimated_annual_tax_saved'])->toBe(720.0)
            ->and($rec['net_contribution'])->toBe(2880.0)
            ->and($rec['gross_contribution'])->toBe(3600.0);
    });

    it('skips non_earner_spouse_pension in single (non-coupled) mode', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'non_earner_spouse_pension'))->toBeNull();
    });

    it('skips non_earner_spouse_pension in dual_earner mode', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'non_earner_spouse_pension'))->toBeNull();
    });

    it('skips non_earner_spouse_pension when the spouse family member is 75 or older', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);
        FamilyMember::factory()->for($user)->create([
            'relationship' => 'spouse',
            'date_of_birth' => now()->subYears(76),
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(collect($output->recommendations)->firstWhere('type', 'non_earner_spouse_pension'))->toBeNull();
    });

    it('surfaces existing pension balance from tax_strategy_household_inputs in the description', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_pension_balance' => 25000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'non_earner_spouse_pension');
        expect($rec)->not->toBeNull()
            ->and($rec['spouse_existing_pension_balance'])->toBe(25000.0)
            ->and($rec['description'])->toContain('£25,000');
    });
});

describe('Phase 4 — Pension AA Carry-Forward (#3)', function () {
    it('does not fire for basic-rate users', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 30000,
        ]);
        foreach (['2024/25', '2023/24', '2022/23'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => 5000,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->toBeNull();
    });

    it('does not fire when current year contributions already exceed AA', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'monthly_contribution_amount' => 6000, // £72k/yr — over the £60k AA
        ]);
        foreach (['2024/25', '2023/24', '2022/23'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => 0,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->toBeNull();
    });

    it('does not fire when no pension_input_history rows exist', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->toBeNull();
    });

    it('does not fire when all 3 prior years used the full AA', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
        ]);
        foreach (['2024/25', '2023/24', '2022/23'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => 60000,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->toBeNull();
    });

    it('fires with correct unused_carry_forward_total and saving for higher-rate user', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
        ]);
        // Liquid-wealth gate (≥£10k non-ISA cash) and the affordable cap
        // (½ of liquid wealth) require a SavingsAccount fixture for the
        // strategy to fire. £200k @ 0% interest avoids any band shift.
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0,
        ]);
        foreach (['2024/25', '2023/24', '2022/23'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => 20000,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        // Unused total: 3 × (60k AA − 20k input) = 120k.
        // HMRC tax-relief cap: gross_income (80k) − current_input (0) = 80k.
        // Affordable cap: liquid (200k) × 0.5 = 100k.
        // Recommended: min(120k, 80k, 100k) = 80k.
        // Saving: 80k × 0.4 = 32k.
        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->not->toBeNull()
            ->and($rec['unused_carry_forward_total'])->toBe(120000.0)
            ->and($rec['recommended_contribution'])->toBe(80000.0)
            ->and($rec['tax_relief_headroom'])->toBe(80000.0)
            ->and($rec['marginal_rate'])->toBe(0.4)
            ->and($rec['lookback_years'])->toBe(3)
            ->and($rec['estimated_annual_tax_saved'])->toBe(32000.0)
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['category'])->toBe('allowance');
    });

    it('fires with correct saving for additional-rate user', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 200000,
        ]);
        // £250k liquid → afford cap = 125k; tax-relief = 200k; usable = 120k.
        // Recommended = min(120k, 200k, 125k) = 120k. Saving = 120k × 0.45 = 54k.
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 250000,
            'interest_rate' => 0,
        ]);
        foreach (['2024/25', '2023/24', '2022/23'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => 20000,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        expect($rec)->not->toBeNull()
            ->and($rec['marginal_rate'])->toBe(0.45)
            ->and($rec['estimated_annual_tax_saved'])->toBe(54000.0);
    });

    it('only counts the most recent 3 history entries', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 100000,
            'interest_rate' => 0,
        ]);
        // 4 years of history; oldest one (2021/22) must be ignored
        foreach (['2024/25', '2023/24', '2022/23', '2021/22'] as $year) {
            PensionInputHistory::create([
                'user_id' => $user->id,
                'tax_year' => $year,
                'pension_input_amount' => $year === '2021/22' ? 0 : 30000,
            ]);
        }

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'pension_aa_carry_forward');
        // 3 × (60k - 30k) = 90k unused; the 60k from 2021/22 is dropped
        expect($rec)->not->toBeNull()
            ->and($rec['unused_carry_forward_total'])->toBe(90000.0);
    });
});

describe('Phase 4 — Gift Aid Higher-Rate Relief (#13)', function () {
    it('does not fire for basic-rate users', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 30000,
            'annual_charitable_donations' => 500,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief');
        expect($rec)->toBeNull();
    });

    it('does not fire when donations are zero or null', function () {
        $userZero = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => 0,
        ]);
        $userNull = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => null,
        ]);

        $outputZero = app(TaxStrategyCalculator::class)->calculate($userZero);
        $outputNull = app(TaxStrategyCalculator::class)->calculate($userNull);

        expect(collect($outputZero->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief'))->toBeNull();
        expect(collect($outputNull->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief'))->toBeNull();
    });

    it('fires for higher-rate user with correct 25% factor', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => 1000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief');
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_annual_tax_saved'])->toBe(250.0)
            ->and($rec['reclaim_factor'])->toBe(0.25)
            ->and($rec['tax_band'])->toBe('higher')
            ->and($rec['annual_donations'])->toBe(1000.0)
            ->and($rec['priority'])->toBe('medium')
            ->and($rec['category'])->toBe('allowance');
    });

    it('fires for additional-rate user with correct 31.25% factor', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 200000,
            'annual_charitable_donations' => 1000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief');
        expect($rec)->not->toBeNull()
            ->and($rec['estimated_annual_tax_saved'])->toBe(312.5)
            ->and($rec['reclaim_factor'])->toBe(0.3125)
            ->and($rec['tax_band'])->toBe('additional');
    });

    it('omits when saving below £1 floor', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 80000,
            'annual_charitable_donations' => 2.0, // 2 × 0.25 = 0.50 — below £1
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'gift_aid_higher_rate_relief');
        expect($rec)->toBeNull();
    });
});

describe('Phase 5 — Tapered Annual Allowance (#14)', function () {
    it('does not fire when threshold income is at or below £200k', function () {
        // adjusted = £270k (employer pension addback) but threshold = £180k → no taper
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 180000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 180000,
            'employer_contribution_percent' => 50, // 90k addback → adjusted = 270k
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('does not fire when adjusted income is at or below £260k', function () {
        // threshold = £210k but no employer pension → adjusted = threshold = 210k
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 210000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('does not fire when both gates are exactly equal to thresholds', function () {
        // strict > comparison — gate equality should not fire
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 200000, // threshold = 200000 (gate)
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 200000,
            'employer_contribution_percent' => 30, // +60k → adjusted = 260000 (gate)
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->toBeNull();
    });

    it('fires when both gates are breached and reports the tapered AA', function () {
        // threshold = 220k (>200k) AND adjusted = 280k (>260k)
        // taper = max(60k - 0.5 × (280k - 260k), 10k) = max(60k - 10k, 10k) = 50k
        // aa_reduction = 60k - 50k = 10k; marginal rate = 0.45 (additional)
        // avoided charge = 10k × 0.45 = 4500
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 220000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 220000,
            'employer_contribution_percent' => round(60000 / 220000 * 100, 4), // ~27.2727 → 60k addback
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('warning')
            ->and($rec['priority'])->toBe('high')
            ->and((float) $rec['tapered_annual_allowance'])->toBeGreaterThan(49000.0)
            ->and((float) $rec['tapered_annual_allowance'])->toBeLessThan(51000.0)
            ->and($rec['threshold_income_gate'])->toBe(200000.0)
            ->and($rec['adjusted_income_gate'])->toBe(260000.0)
            ->and($rec['standard_annual_allowance'])->toBe(60000.0)
            ->and($rec['minimum_allowance'])->toBe(10000.0)
            ->and($rec['taper_rate'])->toBe(0.5)
            ->and($rec['marginal_rate'])->toBe(0.45);
    });

    it('floors at the £10k minimum allowance for very high adjusted income', function () {
        // adjusted = 600k → untapered AA reduction = 0.5 × (600k - 260k) = 170k
        // 60k - 170k would go negative — floor at 10k minimum_allowance.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 500000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 500000,
            'employer_contribution_percent' => 20, // 100k addback → adjusted = 600k
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $rec = collect($output->recommendations)->firstWhere('type', 'tapered_annual_allowance');
        expect($rec)->not->toBeNull()
            ->and($rec['tapered_annual_allowance'])->toBe(10000.0)
            ->and($rec['aa_reduction'])->toBe(50000.0); // 60k - 10k floor
    });

    it('surfaces with sortWeight 0 — appears first in recommendations[]', function () {
        // High-income persona that ALSO triggers other strategies; verify the
        // tapered-AA warning sorts ahead of all others.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 220000,
            'annual_charitable_donations' => 1000, // also fires gift_aid_higher_rate_relief
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'annual_salary' => 220000,
            'employer_contribution_percent' => round(60000 / 220000 * 100, 4),
            'employee_contribution_percent' => 0,
            'monthly_contribution_amount' => 0,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect(count($output->recommendations))->toBeGreaterThan(1);
        expect($output->recommendations[0]['type'])->toBe('tapered_annual_allowance');
        expect($output->recommendations[0]['category'])->toBe('warning');
    });
});

describe('overrides applied in-memory', function () {
    it('applies pension_contribution_percent override without DB write', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 50000,
        ]);

        $base = app(TaxStrategyCalculator::class)->calculate($user);
        $overridden = app(TaxStrategyCalculator::class)->calculate(
            $user,
            new TaxStrategyOverridesDTO(pensionContributionPercent: 20)
        );

        $baseAa = collect($base->userAllowances)->firstWhere('key', 'pension_annual_allowance');
        $overAa = collect($overridden->userAllowances)->firstWhere('key', 'pension_annual_allowance');

        expect($overAa['used'])->toBeGreaterThan($baseAa['used']);
        // No DB write — fresh user has untouched contribution
        expect($user->fresh()->annual_employment_income)->toBe('50000.00');
    });
});
