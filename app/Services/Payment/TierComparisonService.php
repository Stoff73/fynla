<?php

declare(strict_types=1);

namespace App\Services\Payment;

class TierComparisonService
{
    /**
     * Stable display order and canonical British-English presentation copy.
     *
     * @var array<string, array{label: string, cap_key?: string}>
     */
    private const array FEATURES = [
        'dashboard' => ['label' => 'Financial dashboard'],
        'income' => ['label' => 'Income tracking'],
        'expenditure' => ['label' => 'Expenditure tracking'],
        'liabilities' => ['label' => 'Liabilities tracking'],
        'protection' => ['label' => 'Protection module'],
        'savings_account' => ['label' => 'Bank accounts'],
        'investment' => ['label' => 'Investments'],
        'investments_exotic' => ['label' => 'Alternative investments'],
        'pension_account' => ['label' => 'Pensions'],
        'retirement_decumulation' => ['label' => 'Retirement decumulation planning'],
        'property' => ['label' => 'Property'],
        'chattels' => ['label' => 'Valuables'],
        'goals' => ['label' => 'Goals and life events', 'cap_key' => 'goal'],
        'family_module' => ['label' => 'Family module'],
        'benefits_child' => ['label' => 'Child benefit modelling'],
        'estate' => ['label' => 'Estate planning'],
        'letter_to_spouse' => ['label' => 'Letter to spouse and expression of wishes'],
    ];

    /**
     * @return list<array{key: string, label: string, included: bool, availability: string}>
     */
    public function featuresFor(array $matrix, array $caps): array
    {
        $features = [];

        foreach (self::FEATURES as $key => $definition) {
            if (! array_key_exists($key, $matrix)) {
                continue;
            }

            $availability = $this->availability((string) $matrix[$key]);
            $label = $this->label(
                $definition['label'],
                $availability,
                $caps[$definition['cap_key'] ?? $key] ?? null,
            );

            $features[] = [
                'key' => $key,
                'label' => $label,
                'included' => $availability !== 'none',
                'availability' => $availability,
            ];
        }

        return $features;
    }

    private function availability(string $availability): string
    {
        return in_array($availability, ['full', 'limited', 'teaser'], true)
            ? $availability
            : 'none';
    }

    private function label(string $label, string $availability, mixed $cap): string
    {
        return match ($availability) {
            'teaser' => $label.' — preview only',
            'limited' => is_int($cap)
                ? 'Up to '.$cap.' '.strtolower($label)
                : 'Unlimited '.strtolower($label),
            default => $label,
        };
    }
}
