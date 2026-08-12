<?php

declare(strict_types=1);

use App\Models\NetWorthForecastAssumption;
use App\Models\User;
use App\Services\NetWorth\NetWorthForecastAssumptionService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->service = app(NetWorthForecastAssumptionService::class);
});

it('returns dated nominal defaults for every forecast category', function (): void {
    $assumptions = $this->service->forUser($this->user);

    expect($assumptions)->toHaveKeys([
        'property',
        'investments',
        'pensions',
        'cash',
        'business',
        'valuables',
        'mortgages',
        'other_liabilities',
    ]);

    foreach ($assumptions as $assumption) {
        expect($assumption)
            ->toHaveKeys(['rate_percent', 'source', 'effective_from', 'basis'])
            ->and($assumption['source'])->toBe('system_default')
            ->and($assumption['effective_from'])->toBe(now()->toDateString())
            ->and($assumption['basis'])->toBe('nominal');
    }
});

it('persists partial user overrides without changing another user', function (): void {
    $this->service->update($this->otherUser, [
        'property' => 7.0,
        'basis' => 'real',
        'effective_from' => '2026-07-01',
    ]);

    $updated = $this->service->update($this->user, [
        'property' => 4.25,
        'investments' => 6.5,
        'basis' => 'nominal',
        'effective_from' => '2026-08-10',
    ]);

    expect($updated['property'])->toBe([
        'rate_percent' => 4.25,
        'source' => 'user_override',
        'effective_from' => '2026-08-10',
        'basis' => 'nominal',
    ])->and($updated['investments']['rate_percent'])->toBe(6.5)
        ->and($updated['cash']['source'])->toBe('system_default')
        ->and($this->service->forUser($this->otherUser)['property']['rate_percent'])->toBe(7.0)
        ->and(NetWorthForecastAssumption::query()->count())->toBe(2);
});

it('rejects rates outside the supported range and an invalid basis', function (array $input): void {
    expect(fn () => $this->service->update($this->user, $input))
        ->toThrow(ValidationException::class);
})->with([
    'rate too low' => [['cash' => -20.001]],
    'rate too high' => [['property' => 30.001]],
    'invalid basis' => [['basis' => 'cash']],
]);

it('resets only the requested users overrides', function (): void {
    $this->service->update($this->user, ['pensions' => 6.25]);
    $this->service->update($this->otherUser, ['pensions' => 7.25]);

    $reset = $this->service->reset($this->user);

    expect($reset['pensions']['source'])->toBe('system_default')
        ->and(NetWorthForecastAssumption::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and(NetWorthForecastAssumption::query()->where('user_id', $this->otherUser->id)->exists())->toBeTrue();
});
