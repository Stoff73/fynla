<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Expenditure\HouseholdExpenditureWriter;

/**
 * When one account of a shared household goes, put the survivor's stored halves
 * back into household terms. W-0477.
 *
 * Under the shared mode the row IS the half: every writer divides on the way in and
 * every reader trusts what is stored. That is only true while there are two accounts.
 * The halves do not change when one is deleted, so the survivor keeps £600 of
 * groceries that means £1,200 of household spending — and every reader downstream
 * takes it for the whole. Spending understated, disposable income OVERSTATED, and
 * disposable income is what every affordability statement rests on.
 *
 * **Why an observer rather than a line in the delete endpoint.** This codebase's
 * recurring failure is the hand-maintained list: a rule applied at the call sites
 * somebody remembered, and silently skipped by the next one added (see
 * `app/Http/CLAUDE.md` on enumerated mappings, and W-0473, W-0471, W-0479 for what it
 * costs). A deletion is a deletion however it is issued — admin panel, self-service,
 * a console command, a future path nobody has written yet — and this sees them all.
 *
 * The one path it cannot see is `RetentionPurgeService`, which nulls `spouse_id`
 * through the query builder and fires no model events; it calls the writer directly.
 */
final class SurvivingSpouseExpenditureObserver
{
    public function __construct(
        private readonly HouseholdExpenditureWriter $householdExpenditure,
    ) {}

    public function deleted(User $user): void
    {
        // `spouse_id` still points at the deleted account — this is exactly the state
        // `liveSpouse()` exists to describe — so the survivor is found by the link
        // TO the deleted row, not from it.
        User::where('spouse_id', $user->id)
            ->get()
            ->each(fn (User $survivor) => $this->householdExpenditure->promoteSharesToHousehold($survivor));
    }
}
