<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\UserProfile\UserProfileService;

trait ResolvesExpenditure
{
    /**
     * Resolve monthly expenditure from available data sources.
     *
     * Returns the amount and the source used, following a priority chain:
     * 1. ExpenditureProfile (Cashflow Profile) — self-contained, housing included
     * 2. Manual entry + financial commitments (Profile Monthly / categories)
     * 3. User.annual_expenditure / 12 + financial commitments (Profile Annual)
     *
     * **The manual columns hold no housing line at all** (W-0531). There is no
     * mortgage, council tax, utilities or maintenance column on `users`; those
     * live on the property and the mortgage, and are only reachable through
     * `UserProfileService::getFinancialCommitments()`. Returning the bare column
     * therefore answered a different question from the Expenditure tab, and the
     * emergency runway — cash divided by this figure — was overstated by up to
     * 4.7x for every mortgaged household.
     *
     * The addition itself is not repeated here: `getExpenditureBreakdown()` is the
     * one home for it and also respects `expenditure_entry_mode`, which this chain
     * used to ignore.
     *
     * `source` still answers the narrower question "has the user recorded their
     * expenditure?" — it is driven by the manual entry alone. A household with a
     * mortgage and no recorded spending still resolves `none` / `0.0`, so W-0495's
     * "cannot be calculated" runway is not quietly replaced by a figure derived
     * from the mortgage on its own.
     *
     * @return array{amount: float, source: string, label: string}
     */
    protected function resolveMonthlyExpenditure(User $user): array
    {
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();

        if ($expenditureProfile && $expenditureProfile->total_monthly_expenditure > 0) {
            return [
                'amount' => (float) $expenditureProfile->total_monthly_expenditure,
                'source' => 'expenditure_profile',
                'label' => 'Cashflow Profile',
            ];
        }

        $breakdown = app(UserProfileService::class)->getExpenditureBreakdown($user);
        $manual = (float) $breakdown['monthly_manual'];
        $commitments = (float) $breakdown['monthly_commitments'];

        // The breakdown reads the category columns whenever entry mode says
        // `category`, which is what the Expenditure tab shows. A user who switched
        // mode without filling the categories in still has a figure on the column,
        // and dropping it here would report "nothing recorded" for a household that
        // recorded something.
        if ($manual <= 0 && $user->monthly_expenditure > 0) {
            $manual = (float) $user->monthly_expenditure;
        }

        if ($manual > 0) {
            return [
                'amount' => $manual + $commitments,
                'source' => 'user_monthly',
                'label' => 'Profile (Monthly)',
            ];
        }

        if ($user->annual_expenditure > 0) {
            return [
                'amount' => ((float) $user->annual_expenditure / 12) + $commitments,
                'source' => 'user_annual',
                'label' => 'Profile (Annual / 12)',
            ];
        }

        return [
            'amount' => 0.0,
            'source' => 'none',
            'label' => 'Not Set',
        ];
    }
}
