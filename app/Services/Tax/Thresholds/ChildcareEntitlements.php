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
        // A disabled child qualifies to a later age and for a higher cap
        // (`disabled_child_age_limit`, `max_disabled_contribution`), both seeded.
        $ageLimit = (int) ($tfc['child_age_limit'] ?? 11);
        $disabledAgeLimit = (int) ($tfc['disabled_child_age_limit'] ?? 16);
        $capPerChild = (float) ($tfc['max_government_contribution'] ?? 2000);
        $capPerDisabledChild = (float) ($tfc['max_disabled_contribution'] ?? 4000);
        $eligible = $children->filter(fn (FamilyMember $m): bool => (int) $m->age <= ($m->is_disabled ? $disabledAgeLimit : $ageLimit));
        $annualSpend = (float) ($user->childcare ?? 0) * 12;
        if ($eligible->isNotEmpty() && $annualSpend > 0) {
            $rate = (float) ($tfc['government_top_up_rate'] ?? 0.25);
            $cap = (float) $eligible->sum(fn (FamilyMember $m): float => $m->is_disabled ? $capPerDisabledChild : $capPerChild);
            $disabledCount = $eligible->filter(fn (FamilyMember $m): bool => (bool) $m->is_disabled)->count();
            $items[] = [
                'label' => 'Tax-Free Childcare',
                'detail' => $disabledCount > 0
                    ? sprintf('%s eligible, up to £%s each and £%s for a disabled child (to age %d)', $this->count($eligible->count(), 'child', 'children'), number_format($capPerChild), number_format($capPerDisabledChild), $disabledAgeLimit)
                    : sprintf('%s under %d, up to £%s each', $this->count($eligible->count(), 'child', 'children'), $ageLimit + 1, number_format($capPerChild)),
                'amount' => round(min($annualSpend * $rate, $cap), 2),
            ];
        }

        $funding = $this->taxConfig->getEarlyYearsFunding();
        $hoursValue = 0.0;
        $names = [];
        foreach ($children as $child) {
            $months = (int) Carbon::parse($child->date_of_birth)->diffInMonths(Carbon::today());
            // Band membership comes from the seeded ages, never from literal month counts
            // (Rule 2): `eligible_age_from` / `eligible_age_to` are whole years, and the
            // under-2 band starts at `eligible_age_from_months`.
            $band = $this->bandFor($months, $funding);
            if ($band === null || empty($funding[$band]['hourly_rate'])) {
                continue;
            }
            $fullHours = (float) ($funding[$band]['hours_per_week'] ?? 0);
            $hours = $fullHours;
            if ($band === 'working_parents_30hrs') {
                // Only the extension is income-tested; the universal 15 hours stay.
                $universalHours = (float) ($funding['universal_15hrs']['hours_per_week'] ?? 15);
                $hours -= $universalHours;
                $names[] = sprintf('%d hours drops to %d for your %s', (int) $fullHours, (int) ($fullHours - $universalHours), $this->ageWord($months));
            } else {
                $names[] = sprintf('%d hours for your %s', (int) $fullHours, $this->ageWord($months));
            }
            $hoursValue += $hours * (float) ($funding[$band]['weeks_per_year'] ?? 38) * (float) $funding[$band]['hourly_rate'];
        }
        if ($hoursValue > 0) {
            $items[] = ['label' => 'Funded childcare hours', 'detail' => implode(', ', $names), 'amount' => round($hoursValue, 2)];
        }

        return $items;
    }

    /**
     * The income-tested band a child of `$months` falls in, from the config's own
     * age keys. Each band starts at `eligible_age_from_months` if present, else
     * `eligible_age_from` years; it ends where the next band starts, and the top
     * band ends the day the child turns `eligible_age_to` + 1 (a 4 year old stays
     * in the 3-4 band until their fifth birthday). The seeder's `eligible_age_to`
     * is not uniform across bands, which is why the end is taken from the next start.
     *
     * @param  array<string, mixed>  $funding
     */
    private function bandFor(int $months, array $funding): ?string
    {
        $starts = [];
        foreach (['working_parents_under_2', 'working_parents_2yr', 'working_parents_30hrs'] as $band) {
            $cfg = $funding[$band] ?? [];
            if ($cfg === []) {
                continue;
            }
            $starts[$band] = isset($cfg['eligible_age_from_months'])
                ? (int) $cfg['eligible_age_from_months']
                : (int) ($cfg['eligible_age_from'] ?? 0) * 12;
        }
        asort($starts);
        $bands = array_keys($starts);
        foreach ($bands as $i => $band) {
            $from = $starts[$band];
            $to = isset($bands[$i + 1])
                ? $starts[$bands[$i + 1]]
                : ((int) ($funding[$band]['eligible_age_to'] ?? 4) + 1) * 12;
            if ($months >= $from && $months < $to) {
                return $band;
            }
        }

        return null;
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
