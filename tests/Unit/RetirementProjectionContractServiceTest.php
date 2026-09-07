<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Retirement\RetirementProjectionContractService;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\Settings\AssumptionsService;
use App\Services\TaxConfigService;
use Illuminate\Database\Eloquent\Collection;

it('reconciles product income into exact commencement age bands', function (): void {
    $products = [
        [
            'resource_type' => 'dc_pension',
            'resource_id' => 1,
            'name' => 'SIPP',
            'commencement_age' => 60,
            'annual_income' => 9_400.00,
        ],
        [
            'resource_type' => 'db_pension',
            'resource_id' => 2,
            'name' => 'DB Scheme',
            'commencement_age' => 65,
            'annual_income' => 8_000.00,
        ],
        [
            'resource_type' => 'state_pension',
            'resource_id' => 3,
            'name' => 'State Pension',
            'commencement_age' => 67,
            'annual_income' => 11_500.00,
        ],
    ];

    $bands = RetirementProjectionContractService::reconcileAgeBands($products, 60, 90);

    expect($bands)->toBe([
        [
            'start_age' => 60,
            'end_age' => 64,
            'annual_income' => 9_400.0,
            'source_ids' => ['dc_pension:1'],
        ],
        [
            'start_age' => 65,
            'end_age' => 66,
            'annual_income' => 17_400.0,
            'source_ids' => ['dc_pension:1', 'db_pension:2'],
        ],
        [
            'start_age' => 67,
            'end_age' => 90,
            'annual_income' => 28_900.0,
            'source_ids' => ['dc_pension:1', 'db_pension:2', 'state_pension:3'],
        ],
    ]);
});

it('omits products that begin after the requested projection horizon', function (): void {
    $products = [
        [
            'resource_type' => 'dc_pension',
            'resource_id' => 10,
            'name' => 'Late pension',
            'commencement_age' => 95,
            'annual_income' => 4_700.00,
        ],
    ];

    expect(RetirementProjectionContractService::reconcileAgeBands($products, 60, 90))->toBe([]);
});

it('calculates the primary DC value from stated assumptions rather than an uncertainty percentile', function (): void {
    expect(RetirementProjectionContractService::calculatePlanningValue(
        currentValue: 100_000.0,
        monthlyContribution: 500.0,
        annualReturnPercent: 4.7,
        years: 10,
        compoundPeriods: 12,
    ))->toBe(236_260.18);
});

it('uses the user target age for a DC pension whose scheme age is later', function (): void {
    $user = (new User)->setRawAttributes([
        'id' => 99,
        'date_of_birth' => '1976-08-10',
        'target_retirement_age' => 60,
    ]);

    $dc = (new DCPension)->setRawAttributes([
        'id' => 1,
        'scheme_name' => 'SIPP',
        'retirement_age' => 67,
        'current_fund_value' => 100_000,
    ]);
    $db = (new DBPension)->setRawAttributes([
        'id' => 2,
        'scheme_name' => 'DB Scheme',
        'normal_retirement_age' => 65,
        'accrued_annual_pension' => 8_000,
        'projected_annual_pension_at_nra_gbp' => 8_000,
    ]);
    $state = (new StatePension)->setRawAttributes([
        'id' => 3,
        'state_pension_age' => 67,
        'state_pension_forecast_annual' => 11_500,
    ]);

    $user->setRelation('dcPensions', new Collection([$dc]));
    $user->setRelation('dbPensions', new Collection([$db]));
    $user->setRelation('statePension', $state);
    $user->setRelation('retirementProfile', null);

    $projector = new class extends RetirementProjectionService
    {
        public function __construct() {}

        public function projectIndividualDCPension(int $pensionId, int $userId): array
        {
            return [
                'pension_id' => $pensionId,
                'retirement_age' => 67,
                'current_value' => 100_000.0,
                'monthly_contribution' => 500.0,
                'years_to_retirement' => 10,
                'percentile_20_at_retirement' => 180_000.0,
                'median_at_retirement' => 200_000.0,
                'expected_return' => 5.5,
                'volatility' => 12.0,
            ];
        }
    };

    $assumptions = new class extends AssumptionsService
    {
        public function __construct() {}

        public function getTypeAssumptions(User $user, string $type): array
        {
            return [
                'inflation_rate' => 2.5,
                'return_rate' => 5.5,
                'compound_periods' => 12,
                'fees' => ['total' => 0.8],
                'has_overrides' => true,
            ];
        }
    };

    $taxConfig = new class extends TaxConfigService
    {
        public function __construct() {}

        public function get(string $key, mixed $default = null): mixed
        {
            return match ($key) {
                'retirement.withdrawal_rates.sustainable' => 0.047,
                'retirement.projection_end_age' => 100,
                default => $default,
            };
        }
    };

    $contract = (new RetirementProjectionContractService(
        $projector,
        $assumptions,
        $taxConfig,
        // W-0516 — the State Pension age resolver. Real: the cohort schedule is the
        // behaviour, and mocking it would pin the literal this item removed.
        app(StatePensionAgeResolver::class),
    ))->build($user);

    expect($contract['contract_version'])->toBe('retirement_projection_v1')
        ->and($contract['planning_total_at_target_age'])->toBe(11_104.23)
        ->and($contract['assumptions']['sustainable_withdrawal_rate']['percent'])->toBe(4.7)
        ->and($contract['products'])->toHaveCount(3)
        ->and($contract['products'][0]['commencement_age'])->toBe(60)
        ->and($contract['products'][0]['monthly_contribution'])->toBe(500.0)
        ->and($contract['products'][0]['projected_value'])->toBe(236_260.18)
        ->and($contract['products'][0]['annual_income'])->toBe(11_104.23)
        ->and($contract['age_bands'][0]['annual_income'])->toBe(11_104.23)
        ->and($contract['age_bands'][1]['annual_income'])->toBe(19_104.23)
        ->and($contract['age_bands'][2]['annual_income'])->toBe(30_604.23)
        ->and($contract['assumptions']['net_growth_rate_percent'])->toBe(4.7)
        ->and($contract['assumptions']['basis'])->toBe('nominal')
        ->and($contract['uncertainty']['products'][0]['percentile_20_at_retirement'])->toBe(180_000.0)
        ->and($contract['uncertainty']['products'][0]['percentile_50_at_retirement'])->toBe(200_000.0);
});
