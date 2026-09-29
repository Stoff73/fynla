<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Employment;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Income\EmploymentIncomeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair the income rows SpouseHoldingTransfer wrote before employments.is_estimate
 * existed (production 2026-09-29, docs/testing/2026-09-29-prod-savetax-mobile-couple.md, C1).
 *
 * On link, the inviter's figure for the spouse's income was copied across as an
 * unnamed job. The spouse's own named job then became a second row and the two
 * were summed — £32,000 + £32,000 taxed as £64,000. The code fix cannot reach
 * rows already written, so this does, per linked spouse:
 *
 * - DOUBLED: the copied row is untouched and the spouse has since recorded a job
 *   of their own. The copied row is soft-deleted (restorable) and the totals
 *   re-synced from what remains — the spouse's own figures.
 * - PENDING: the copied row is the spouse's only job. It is flagged as an
 *   estimate, so the deployed fix replaces it when the spouse gives their own.
 *   Without this, every spouse linked before the deploy would still double.
 * - SKIPPED (reported, never written): the copied row was changed after the
 *   copy, so the spouse spoke to it and it may be a real job of theirs; or the
 *   spouse's own rows add up to less than the copy, so removing it could lose
 *   a real figure. Review these by hand.
 *
 * The copied row is recognised by when it was written: in the seconds before
 * the household row's spouse_holding_transferred_at, which the transfer stamps
 * once it has copied everything. The spouse cannot have written anything in
 * that window — the transfer runs at registration. Employer and occupation are
 * not part of the match: a spouse whose first answer was only their job title
 * had it written onto the copied row, and that household must still show up
 * (as SKIPPED) rather than be missed.
 *
 * Dry-run by default, as estate:backfill-mirror-parties: the repair runs inside
 * a transaction, reports the totals it actually produced, then rolls back.
 * Idempotent: a repaired row is deleted or flagged, and neither matches again.
 */
class RepairSpouseIncomeEstimates extends Command
{
    /** How long before spouse_holding_transferred_at the copied row may have been created. */
    private const TRANSFER_WINDOW_SECONDS = 120;

    protected $signature = 'income:repair-spouse-estimates
        {--force : Write the changes (default is a dry-run that rolls back)}
        {--user= : Restrict to one spouse user id}';

    protected $description = 'Repair spouse income doubled by the onboarding income copy (C1). Dry-run unless --force.';

    public function handle(EmploymentIncomeService $income): int
    {
        $force = (bool) $this->option('force');
        $candidates = $this->copiedRows();

        if ($candidates === []) {
            $this->info('Nothing to repair: no copied spouse income rows found.');

            return self::SUCCESS;
        }

        $this->line($force ? 'Repairing.' : 'DRY RUN — nothing will be written. Re-run with --force to apply.');
        $this->newLine();

        $rows = [];
        DB::beginTransaction();

        try {
            foreach ($candidates as ['spouse' => $spouse, 'row' => $row]) {
                $before = (float) $spouse->annual_employment_income + (float) $spouse->annual_self_employment_income;
                // Across both income types: the copy takes the spouse's own
                // employment status (incomeTypeFor), and the spouse's own job
                // supersedes it whichever type either is.
                $ownRows = $spouse->employments()->where('id', '!=', $row->id);
                $others = (clone $ownRows)->count();
                $ownTotal = (float) (clone $ownRows)->sum('annual_income');

                if ($row->updated_at->ne($row->created_at)) {
                    $action = 'SKIPPED — changed after the copy';
                } elseif ($others > 0 && $ownTotal < (float) $row->annual_income) {
                    $action = 'SKIPPED — own rows total less than the copy';
                } elseif ($others > 0) {
                    $row->delete();
                    $action = 'DOUBLED — copied row removed';
                } else {
                    $row->is_estimate = true;
                    $row->saveQuietly();
                    $action = 'PENDING — flagged as estimate';
                }

                $income->syncTotals($spouse);
                $spouse->refresh();
                $after = (float) $spouse->annual_employment_income + (float) $spouse->annual_self_employment_income;

                $rows[] = [$spouse->id, $row->id, number_format((float) $row->annual_income, 2), $others, number_format($before, 2), number_format($after, 2), $action];
            }

            $force ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->table(['Spouse', 'Copied row', 'Copied £', 'Own rows', 'Total before', 'Total after', 'Action'], $rows);

        return self::SUCCESS;
    }

    /**
     * The row each transfer wrote, one per linked spouse, if it is still there.
     *
     * @return list<array{spouse: User, row: Employment}>
     */
    private function copiedRows(): array
    {
        $found = [];

        $holdings = TaxStrategyHouseholdInput::whereNotNull('spouse_holding_transferred_at')->get();
        foreach ($holdings as $holding) {
            $spouse = User::where('spouse_id', $holding->user_id)->where('is_preview_user', false)->first();
            if ($spouse === null || ($this->option('user') !== null && (int) $this->option('user') !== $spouse->id)) {
                continue;
            }

            $transferredAt = $holding->spouse_holding_transferred_at;
            // The copy is always written without a name, so a named row in the
            // window is the copy only if it was changed afterwards.
            $row = $spouse->employments()
                ->where('is_estimate', false)
                ->where(fn ($q) => $q->where(fn ($unnamed) => $unnamed->whereNull('employer')->whereNull('occupation'))
                    ->orWhereColumn('updated_at', '!=', 'created_at'))
                ->whereBetween('created_at', [$transferredAt->copy()->subSeconds(self::TRANSFER_WINDOW_SECONDS), $transferredAt])
                ->oldest('id')
                ->first();

            if ($row !== null) {
                $found[] = ['spouse' => $spouse, 'row' => $row];
            }
        }

        return $found;
    }
}
