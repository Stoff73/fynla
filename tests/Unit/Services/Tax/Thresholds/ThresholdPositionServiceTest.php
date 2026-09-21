<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdPositionService;
use App\Services\Tax\Thresholds\ThresholdResult;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function fakeLine(string $key, float $distance, string $unit, bool $lever): ThresholdLine
{
    return new class($key, $distance, $unit, $lever) implements ThresholdLine
    {
        public function __construct(private string $k, private float $d, private string $u, private bool $l) {}

        public function key(): string
        {
            return $this->k;
        }

        public function evaluate(ThresholdContext $context): ?ThresholdResult
        {
            return new ThresholdResult($this->k, $this->k, null, ['value' => $this->d, 'distance' => $this->d, 'unit' => $this->u, 'over' => $this->d > 0], 'h', 'b', 'e', new ThresholdCost, $this->l ? ['title' => 't', 'amount' => 1.0, 'recovers' => 1.0, 'downside' => '', 'action' => ['route' => '/x']] : null);
        }
    };
}

it('orders money lines by proximity, dates after money, and leads with the nearest line that has a lever', function () {
    $user = User::factory()->create();
    $service = new ThresholdPositionService(app(IncomeDefinitionsService::class), [
        fakeLine('far', 20000.0, 'gbp', true),
        fakeLine('date', 30.0, 'days', false),
        fakeLine('near_no_lever', -500.0, 'gbp', false),
        fakeLine('near', 1200.0, 'gbp', true),
    ]);

    $out = $service->evaluate($user);

    expect(array_column($out['lines'], 'key'))->toBe(['near_no_lever', 'near', 'far', 'date'])
        ->and($out['strip'])->toBe('near');
});

it('returns an empty list and no strip when nothing applies', function () {
    $user = User::factory()->create();
    $none = new class implements ThresholdLine
    {
        public function key(): string
        {
            return 'none';
        }

        public function evaluate(ThresholdContext $context): ?ThresholdResult
        {
            return null;
        }
    };

    $out = (new ThresholdPositionService(app(IncomeDefinitionsService::class), [$none]))->evaluate($user);

    expect($out)->toBe(['strip' => null, 'lines' => []]);
});

it('is resolvable from the container with the catalogue tagged', function () {
    $service = app(ThresholdPositionService::class);
    expect($service)->toBeInstanceOf(ThresholdPositionService::class);

    // Resolving the service alone proves nothing about the catalogue: `tagged()`
    // returns a lazy RewindableGenerator, so a misspelt or unresolvable line class
    // in AppServiceProvider would still pass a bare instanceof check. Calling
    // evaluate() iterates the generator and constructs every one of the nine real
    // line classes through the container — a plain earner with no estate, pensions
    // or children clears every line's guard, so the real catalogue should agree with
    // the fake-line tests above and return nothing.
    $user = User::factory()->create(['annual_employment_income' => 30000]);

    expect($service->evaluate($user))->toBe(['strip' => null, 'lines' => []]);
});
