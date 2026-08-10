<?php

declare(strict_types=1);

namespace App\Services\NetWorth;

use App\Models\NetWorthForecastAssumption;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

final class NetWorthForecastAssumptionService
{
    /** @var array<string, float> */
    private const DEFAULT_RATES = [
        'property' => 3.0,
        'investments' => 5.0,
        'pensions' => 5.0,
        'cash' => 2.0,
        'business' => 3.0,
        'valuables' => 2.0,
        'mortgages' => 0.0,
        'other_liabilities' => 0.0,
    ];

    /**
     * @return array<string, array{
     *     rate_percent: float,
     *     source: string,
     *     effective_from: string,
     *     basis: string
     * }>
     */
    public function forUser(User $user): array
    {
        $record = NetWorthForecastAssumption::query()
            ->where('user_id', $user->id)
            ->first();
        $defaultEffectiveDate = now()->toDateString();
        $result = [];

        foreach (self::DEFAULT_RATES as $category => $defaultRate) {
            $hasOverride = $record !== null && $record->getAttribute($category) !== null;
            $result[$category] = [
                'rate_percent' => $hasOverride
                    ? (float) $record->getAttribute($category)
                    : $defaultRate,
                'source' => $hasOverride ? 'user_override' : 'system_default',
                'effective_from' => $hasOverride
                    ? $record->effective_from->toDateString()
                    : $defaultEffectiveDate,
                'basis' => $hasOverride ? (string) $record->basis : 'nominal',
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{
     *     rate_percent: float,
     *     source: string,
     *     effective_from: string,
     *     basis: string
     * }>
     */
    public function update(User $user, array $input): array
    {
        $rateRules = array_fill_keys(
            array_keys(self::DEFAULT_RATES),
            ['sometimes', 'numeric', 'min:-20', 'max:30'],
        );
        $validated = Validator::make($input, [
            ...$rateRules,
            'basis' => ['sometimes', 'string', 'in:nominal,real'],
            'effective_from' => ['sometimes', 'date_format:Y-m-d'],
        ])->validate();

        if ($validated === []) {
            return $this->forUser($user);
        }

        $record = NetWorthForecastAssumption::query()->firstOrNew([
            'user_id' => $user->id,
        ]);
        $record->fill($validated);
        $record->basis = $validated['basis'] ?? $record->basis ?? 'nominal';
        $record->effective_from = $validated['effective_from']
            ?? $record->effective_from
            ?? now()->toDateString();
        $record->save();

        return $this->forUser($user);
    }

    /**
     * @return array<string, array{
     *     rate_percent: float,
     *     source: string,
     *     effective_from: string,
     *     basis: string
     * }>
     */
    public function reset(User $user): array
    {
        NetWorthForecastAssumption::query()
            ->where('user_id', $user->id)
            ->delete();

        return $this->forUser($user);
    }
}
