<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BusinessInterest;
use App\Models\User;
use App\Notifications\CompanyFilingDueNotification;
use App\Services\Business\CompaniesHouseService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Daily sweep over business interests that carry a company number, warning the
 * owner as each Companies House filing deadline approaches and after it passes.
 *
 * Entering a company number is the opt-in: these alerts only exist for a
 * business the user has explicitly linked to the register, and clearing the
 * number stops them.
 *
 * ponytail: the rungs are matched on an exact day count against a once-daily
 * cron, so each rung fires exactly once with no per-alert state to store. The
 * ceiling is that a day the scheduler does not run silently drops that rung —
 * add a last_alerted_days column if the misses ever matter.
 */
class SendBusinessFilingAlerts extends Command
{
    protected $signature = 'business:send-filing-alerts';

    protected $description = 'Warn business owners of approaching and overdue Companies House filing deadlines';

    /** Days before the deadline that raise a reminder. */
    private const REMINDER_DAYS = [30, 20, 15, 10, 5, 4, 3, 2, 1, 0];

    /** Days after the deadline that raise an overdue warning. */
    private const OVERDUE_DAYS = [-1, -7, -14, -30];

    public function __construct(private readonly CompaniesHouseService $companiesHouse)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $synced = 0;
        $sent = 0;
        $today = Carbon::today();

        BusinessInterest::query()
            ->whereNotNull('company_number')
            ->where('company_number', '!=', '')
            ->with('user')
            ->chunkById(100, function ($businesses) use (&$synced, &$sent, $today) {
                foreach ($businesses as $business) {
                    if ($this->isStale($business)) {
                        $synced += $this->companiesHouse->sync($business) !== null ? 1 : 0;
                    }

                    $sent += $this->alertFor($business, $today);
                }
            });

        $this->info("Companies House filing sweep: {$synced} refreshed, {$sent} alerts sent.");

        return Command::SUCCESS;
    }

    /**
     * Notify the owners about whichever of the two filings lands on a rung today.
     */
    private function alertFor(BusinessInterest $business, Carbon $today): int
    {
        $sent = 0;

        $filings = [
            CompanyFilingDueNotification::TYPE_ACCOUNTS => $business->accounts_due_on,
            CompanyFilingDueNotification::TYPE_CONFIRMATION => $business->confirmation_statement_due_on,
        ];

        foreach ($filings as $type => $dueDate) {
            if ($dueDate === null) {
                continue;
            }

            $daysUntil = (int) $today->diffInDays(Carbon::parse($dueDate)->startOfDay(), false);

            if (! in_array($daysUntil, self::REMINDER_DAYS, true)
                && ! in_array($daysUntil, self::OVERDUE_DAYS, true)) {
                continue;
            }

            $notification = new CompanyFilingDueNotification(
                $business->business_name ?? 'your business',
                $type,
                Carbon::parse($dueDate)->format('Y-m-d'),
                $daysUntil,
            );

            // Both owners of a jointly held company carry the filing duty.
            foreach ($this->owners($business) as $owner) {
                $owner->notify($notification);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * @return iterable<User>
     */
    private function owners(BusinessInterest $business): iterable
    {
        return collect([$business->user, $business->jointOwner])
            ->filter()
            // jointOwner() is withTrashed(), so a deactivated spouse would
            // otherwise still be notified about a filing they no longer see.
            ->reject(fn ($user) => $user->is_preview_user || $user->deleted_at !== null)
            ->unique('id');
    }

    private function isStale(BusinessInterest $business): bool
    {
        return $business->companies_house_synced_at === null
            || $business->companies_house_synced_at->lt(
                now()->subDays(CompaniesHouseService::STALE_AFTER_DAYS)
            );
    }
}
