<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdPositionService;
use App\Services\Tax\Thresholds\ThresholdResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    expect(app(ThresholdPositionService::class))->toBeInstanceOf(ThresholdPositionService::class);
});
