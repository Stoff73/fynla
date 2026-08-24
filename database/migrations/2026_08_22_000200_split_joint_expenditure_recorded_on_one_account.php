<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Support\SharedExpenditure;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill counterpart to the W-0190 write-path fix.
 *
 * A household on `expenditure_sharing_mode = 'joint'` should hold half its
 * spending on each account. The profile path never applied that, so households
 * that entered their spending through the form carry the whole of it on whoever
 * typed it and nothing on the other account. Removing the bad write fixes every
 * FUTURE save; the rows already stored stay wrong until someone saves again.
 *
 * **Deliberately narrow, because only one case is unambiguous.** Two rows storing
 * the same non-zero figure are indistinguishable: they might be two correct halves
 * written by the onboarding path, or one household total mirrored whole by the
 * profile path. Halving those blindly would corrupt every household that onboarded
 * correctly. So this migration touches only the shape it can read with certainty —
 * one account carrying manual spending and the other carrying none — which is
 * exactly the shape the defect produces.
 *
 * Households where both accounts hold figures are left alone and reported, so the
 * decision about them is taken by someone rather than guessed at here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $candidates = User::query()
            ->whereNotNull('spouse_id')
            ->where(function ($query): void {
                $query->whereNull('expenditure_sharing_mode')
                    ->orWhere('expenditure_sharing_mode', SharedExpenditure::MODE_JOINT);
            })
            ->get();

        $handled = [];

        foreach ($candidates as $user) {
            if (isset($handled[$user->id])) {
                continue;
            }

            $spouse = User::find($user->spouse_id);

            if (! $spouse || $spouse->spouse_id !== $user->id) {
                continue;
            }

            if (! SharedExpenditure::isShared($spouse->expenditure_sharing_mode)) {
                continue;
            }

            $handled[$user->id] = true;
            $handled[$spouse->id] = true;

            $userTotal = $this->recordedTotal($user);
            $spouseTotal = $this->recordedTotal($spouse);

            // Both empty: nothing to divide. Both populated: ambiguous — two correct
            // halves and one whole total mirrored twice look identical from here.
            if (($userTotal > 0) === ($spouseTotal > 0)) {
                continue;
            }

            $recorder = $userTotal > 0 ? $user : $spouse;
            $other = $userTotal > 0 ? $spouse : $user;

            $household = [];
            foreach (SharedExpenditure::SHARED_FIELDS as $field) {
                $household[$field] = (float) ($recorder->getAttribute($field) ?? 0);
            }

            $share = SharedExpenditure::shareOf($household, true);

            $recorder->update($share);
            $other->update($share);

            $this->syncExpenditureProfile($recorder, (float) $share['monthly_expenditure']);
            $this->syncExpenditureProfile($other, (float) $share['monthly_expenditure']);
        }
    }

    public function down(): void
    {
        // Irreversible by design: the figures this replaced charged a shared cost
        // wholly to one spouse, which no surface should reproduce.
    }

    /**
     * The manual spending recorded on this account.
     */
    private function recordedTotal(User $user): float
    {
        $total = 0.0;

        foreach (SharedExpenditure::SHARED_FIELDS as $field) {
            $total += (float) ($user->getAttribute($field) ?? 0);
        }

        return $total;
    }

    /**
     * ResolvesExpenditure prefers the ExpenditureProfile row over the user column,
     * so a household total left there would outrank the share just written.
     */
    private function syncExpenditureProfile(User $user, float $monthly): void
    {
        $profile = ExpenditureProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return;
        }

        $profile->update(['total_monthly_expenditure' => $monthly]);
    }
};
