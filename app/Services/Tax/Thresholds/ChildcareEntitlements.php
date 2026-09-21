<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Number;

/**
 * The childcare half of the £100,000 line. `getTaxFreeChildcare()` and
 * `getEarlyYearsFunding()` were seeded with the right thresholds and read by
 * nothing (2026-09-17); this is their first caller.
 *
 * `users.childcare` is a MONTHLY expenditure figure (UserProfileService::getExpenditureBreakdown).
 */
final class ChildcareEntitlements
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @return list<array{label: string, detail: string, amount: float}> */
    public function for(User $user): array
    {
        $user->loadMissing('familyMembers');
        $children = $user->familyMembers
            ->filter(fn (FamilyMember $m): bool => in_array($m->relationship, ['child', 'step_child'], true) && $m->date_of_birth !== null)
            ->values();
        if ($children->isEmpty()) {
            return [];
        }

        $items = [];
        $tfc = $this->taxConfig->getTaxFreeChildcare();
        $ageLimit = (int) ($tfc['child_age_limit'] ?? 11);
        $underLimit = $children->filter(fn (FamilyMember $m): bool => (int) $m->age <= $ageLimit);
        $annualSpend = (float) ($user->childcare ?? 0) * 12;
        if ($underLimit->isNotEmpty() && $annualSpend > 0) {
            $rate = (float) ($tfc['government_top_up_rate'] ?? 0.25);
            $cap = (float) ($tfc['max_government_contribution'] ?? 2000) * $underLimit->count();
            $items[] = [
                'label' => 'Tax-Free Childcare',
                'detail' => sprintf('%s under %d, up to £%s each', $this->count($underLimit->count(), 'child', 'children'), $ageLimit + 1, number_format($cap / $underLimit->count())),
                'amount' => round(min($annualSpend * $rate, $cap), 2),
            ];
        }

        $funding = $this->taxConfig->getEarlyYearsFunding();
        $hoursValue = 0.0;
        $names = [];
        foreach ($children as $child) {
            $months = (int) Carbon::parse($child->date_of_birth)->diffInMonths(Carbon::today());
            $band = match (true) {
                $months >= 36 && $months < 60 => 'working_parents_30hrs',
                $months >= 24 && $months < 36 => 'working_parents_2yr',
                $months >= (int) ($funding['working_parents_under_2']['eligible_age_from_months'] ?? 9) && $months < 24 => 'working_parents_under_2',
                default => null,
            };
            if ($band === null || empty($funding[$band]['hourly_rate'])) {
                continue;
            }
            $hours = (float) ($funding[$band]['hours_per_week'] ?? 0);
            if ($band === 'working_parents_30hrs') {
                // Only the extension is income-tested; the universal 15 hours stay.
                $hours -= (float) ($funding['universal_15hrs']['hours_per_week'] ?? 15);
            }
            $hoursValue += $hours * (float) ($funding[$band]['weeks_per_year'] ?? 38) * (float) $funding[$band]['hourly_rate'];
            $names[] = sprintf('%d hours for your %s', (int) ($funding[$band]['hours_per_week'] ?? 0), $this->ageWord($months));
        }
        if ($hoursValue > 0) {
            $items[] = ['label' => 'Funded childcare hours', 'detail' => implode(', ', $names), 'amount' => round($hoursValue, 2)];
        }

        return $items;
    }

    private function count(int $n, string $one, string $many): string
    {
        return $n === 1 ? 'One '.$one : ucfirst(Number::spell($n)).' '.$many;
    }

    private function ageWord(int $months): string
    {
        return $months < 24 ? 'baby' : sprintf('%d-year-old', intdiv($months, 12));
    }
}
