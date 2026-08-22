<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Estate\Bequest;
use App\Models\Estate\WillDocument;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\WillAnalysisService;
use App\Services\Estate\WillDocumentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W-0046 — backfill Bequest rows for wills completed before W-0023.
 *
 * `WillDocumentService::syncBequests()` runs on completion, so every will
 * completed before that landed holds its gifts as JSON on the will document and
 * has zero `Bequest` rows. The Estate module cannot see them and
 * `getCharitableBequestTotal()` returns £0 — so a charitable legacy that should
 * move the estate to the reduced Inheritance Tax rate is invisible to the
 * calculation. The user recorded the gift; the app behaves as though they did
 * not.
 *
 * Dry-run by default, matching `fyn:episodic:purge` and `fyn:user:erase`.
 *
 * Idempotent by construction, not by a flag: it calls the SAME sync a completion
 * calls, and that sync clears every will-document-sourced row for the will
 * before writing. Re-running changes nothing; a later re-completion cannot
 * double up. Rows created by hand through the Estate bequest API carry a NULL
 * `will_document_id` and are never touched.
 */
class BackfillWillBequests extends Command
{
    protected $signature = 'estate:backfill-bequests
        {--force : Write the rows (default is a dry-run that rolls back)}
        {--user= : Restrict to one user id}';

    protected $description = 'Create Bequest rows for wills completed before the will-builder sync existed. Dry-run unless --force.';

    public function handle(
        WillDocumentService $wills,
        WillAnalysisService $analysis,
        IHTCalculationService $iht,
        CacheInvalidationService $cache,
    ): int {
        $force = (bool) $this->option('force');

        // AppServiceProvider:208 enables preventLazyLoading outside production.
        // The Inheritance Tax engine walks a wide, conditional relation graph
        // (retirement profile, pensions, life events) that a maintenance command
        // sweeping arbitrary users cannot pre-declare, and guessing at an
        // eager-load list would make this command silently miss whichever
        // relation the engine reaches next. Relaxed for the duration and
        // restored in the finally block below — it is a read concern, and the
        // engine is the same code the application runs.
        $lazyLoadingWasPrevented = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        $documents = $this->documentsToBackfill();

        if ($documents->isEmpty()) {
            Model::preventLazyLoading($lazyLoadingWasPrevented);
            $this->info('Nothing to backfill: no completed will holds gifts that are missing from the bequests table.');

            return self::SUCCESS;
        }

        $this->line($force ? 'Backfilling.' : 'DRY RUN — nothing will be written. Re-run with --force to apply.');
        $this->newLine();

        $report = [];
        $rowsWritten = 0;

        // The whole run is one transaction so a dry-run can produce REAL
        // before/after figures — computed by the real code path against real
        // rows — and then roll them back. Anything less would be a second
        // implementation guessing at the outcome.
        DB::beginTransaction();

        try {
            foreach ($documents as $doc) {
                $user = $doc->user;

                if ($user === null) {
                    $this->warn("Skipped document {$doc->id}: no user.");

                    continue;
                }

                $before = $this->snapshot($user, $analysis, $iht);

                $written = $wills->syncBequestsForDocument($doc);
                $rowsWritten += $written;

                // The cached Inheritance Tax calculation is keyed on a hash that
                // now includes the charitable bequests, so it misses naturally
                // here. Clearing the module caches too keeps the "after" read
                // honest rather than served from the pre-backfill estate
                // analysis.
                $cache->invalidateForUser($user->id);

                $after = $this->snapshot($user->fresh(), $analysis, $iht);

                $report[] = [
                    'user' => $user,
                    'document_id' => $doc->id,
                    'rows' => $written,
                    'before' => $before,
                    'after' => $after,
                ];
            }

            if ($force) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Backfill aborted, nothing written: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            Model::preventLazyLoading($lazyLoadingWasPrevented);
        }

        // After a rollback the caches hold post-backfill figures that no longer
        // match the database. Clear them again so a dry-run leaves nothing behind.
        foreach ($report as $row) {
            $cache->invalidateForUser($row['user']->id);
        }

        $this->renderReport($report, $rowsWritten, $force);

        return self::SUCCESS;
    }

    /**
     * The latest completed document per user that holds gifts.
     *
     * Latest, because a user who completed a second document superseded the
     * first — that document is the will, and syncing a superseded one would
     * restore gifts the user has already replaced.
     *
     * Deliberately not chunked: the set is one row per user who has COMPLETED a
     * will, which is a small fraction of users, and picking the latest per user
     * needs the whole set anyway. If that assumption ever stops holding, chunk
     * over distinct user ids rather than over documents.
     *
     * @return Collection<int, WillDocument>
     */
    private function documentsToBackfill(): Collection
    {
        $query = WillDocument::with('user')
            ->where('status', 'complete')
            ->whereNull('deleted_at')
            ->orderByDesc('id');

        if ($this->option('user') !== null) {
            $query->where('user_id', (int) $this->option('user'));
        }

        return $query->get()
            ->groupBy('user_id')
            ->map(fn ($docs) => $docs->first())
            ->filter(fn (WillDocument $doc) => $this->hasGifts($doc))
            ->values();
    }

    private function hasGifts(WillDocument $doc): bool
    {
        foreach ($doc->specific_gifts ?? [] as $gift) {
            if (is_array($gift) && trim((string) ($gift['beneficiary_name'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{charitable: float, rate: float, bequests: int, charitable_rows: int, charities: list<string>}
     */
    private function snapshot(User $user, WillAnalysisService $analysis, IHTCalculationService $iht): array
    {
        $calculation = $iht->calculate($user);
        $netEstate = (float) ($calculation['total_net_estate'] ?? 0);

        $allRows = Bequest::where('user_id', $user->id)->get();
        $charitableRows = $allRows->filter(fn (Bequest $bequest) => $bequest->isCharitable());

        return [
            'charitable' => $analysis->getCharitableBequestTotal($user, $netEstate),
            'rate' => (float) ($calculation['iht_rate'] ?? 0),
            'bequests' => $allRows->count(),
            'charitable_rows' => $charitableRows->count(),
            'charities' => $charitableRows->pluck('beneficiary_name')->sort()->values()->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $report
     */
    private function renderReport(array $report, int $rowsWritten, bool $force): void
    {
        $verb = $force ? 'Backfilled' : 'Would backfill';

        $this->info("{$verb} ".count($report).' will(s) → '.$rowsWritten.' bequest row(s).');
        $this->newLine();

        $rows = [];
        $rateChanges = [];
        $needsReview = [];

        foreach ($report as $entry) {
            $user = $entry['user'];
            $before = $entry['before'];
            $after = $entry['after'];
            $moved = abs($before['rate'] - $after['rate']) > 0.0001;

            if ($moved) {
                $rateChanges[] = $entry;
            }

            // The document gift and an existing hand-made row are different
            // legacies by name, so they are correctly NOT merged — but a user who
            // now holds two charitable bequests where they held one may be
            // looking at the SAME legacy recorded twice under different names.
            // Pre-W-0024 mirror documents carried the partner's charity verbatim,
            // which produces exactly this shape. Flag it; never merge it, because
            // merging two differently-named charities would be inventing.
            $duplicated = $after['charitable_rows'] > $before['charitable_rows']
                && $before['charitable_rows'] > 0;

            if ($duplicated) {
                $needsReview[] = $entry;
            }

            $flags = array_filter([
                $moved ? 'RATE CHANGED' : null,
                $duplicated ? 'REVIEW' : null,
            ]);

            $rows[] = [
                $user->id,
                $user->name,
                $entry['document_id'],
                $entry['rows'],
                '£'.number_format($before['charitable'], 2).' → £'.number_format($after['charitable'], 2),
                $this->percent($before['rate']).' → '.$this->percent($after['rate']),
                implode(' ', $flags),
            ];
        }

        $this->table(
            ['User', 'Name', 'Doc', 'Rows', 'Charitable total', 'Inheritance Tax rate', ''],
            $rows,
        );

        if ($needsReview !== []) {
            $this->newLine();
            $this->warn(count($needsReview).' user(s) now hold more charitable bequests than before. Check these are separate legacies and not one legacy recorded twice — a mirror will generated before W-0024 carried the partner\'s charity verbatim:');

            foreach ($needsReview as $entry) {
                $this->warn(sprintf(
                    '  user %d (%s): %s → %s',
                    $entry['user']->id,
                    $entry['user']->name,
                    implode(', ', $entry['before']['charities']) ?: 'none',
                    implode(', ', $entry['after']['charities']),
                ));
            }
        }

        if ($rateChanges === []) {
            $this->newLine();
            $this->info('No estate changed its Inheritance Tax rate.');

            return;
        }

        $this->newLine();
        $this->warn(count($rateChanges).' estate(s) changed Inheritance Tax rate. These users were previously shown a different answer:');

        foreach ($rateChanges as $entry) {
            $this->warn(sprintf(
                '  user %d (%s): %s → %s, charitable £%s → £%s',
                $entry['user']->id,
                $entry['user']->name,
                $this->percent($entry['before']['rate']),
                $this->percent($entry['after']['rate']),
                number_format($entry['before']['charitable'], 2),
                number_format($entry['after']['charitable'], 2),
            ));
        }
    }

    private function percent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate * 100, 2), '0'), '.').'%';
    }
}
