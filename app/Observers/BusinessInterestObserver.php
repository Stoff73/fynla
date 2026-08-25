<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\BusinessInterest;
use App\Services\Business\CompaniesHouseService;

/**
 * Keeps a business interest's Companies House filing dates in step with its
 * company number.
 *
 * Four paths can set or change that number — the web controller's store and
 * update, Fyn's create_business_interest, and Fyn's update_record — and a
 * filing-date refresh hung off each of them separately is exactly the kind of
 * parallel mechanism that drifts (GOLDEN RULE 20). This observer is the single
 * home: set the number anywhere, on any surface, and the dates follow.
 *
 * Split across created/updated rather than a single saved() hook, because
 * Eloquent only calls syncChanges() from performUpdate() — on an insert
 * wasChanged() is false for every attribute, so a saved() guard would silently
 * never fire for a newly created business.
 */
class BusinessInterestObserver
{
    public function created(BusinessInterest $business): void
    {
        if (filled($business->company_number)) {
            app(CompaniesHouseService::class)->sync($business);
        }
    }

    public function updated(BusinessInterest $business): void
    {
        // The re-entrant save from sync() below writes only the three date
        // columns, so this guard is false second time round and cannot loop.
        if (! $business->wasChanged('company_number')) {
            return;
        }

        // A cleared or replaced number invalidates whatever was cached against
        // the old one — never leave one company's deadlines on another.
        $business->forceFill([
            'accounts_due_on' => null,
            'confirmation_statement_due_on' => null,
            'companies_house_synced_at' => null,
        ])->saveQuietly();

        if (filled($business->company_number)) {
            app(CompaniesHouseService::class)->sync($business);
        }
    }
}
