<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Services\Estate\WillDocumentService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W-0395 — repair mirror wills generated before W-0024.
 *
 * `generateMirrorWill()` swapped only the residuary estate until W-0024, so
 * every mirror produced before it carries the primary's executors and guardians
 * verbatim. The partner's own will therefore appoints HER as her own executor
 * and describes her as her own spouse — and because `markComplete()` derives
 * `wills.executor_name` from that list, the wrong names were persisted into
 * Fynla's record of the household's intentions, where the estate model reads
 * them.
 *
 * The generator fix cannot reach documents already generated. This is the other
 * half: it repairs what exists. Verified against the peak_earners household,
 * where `will_documents.id = 6` (Sarah Jones) was generated 2026-08-21 08:59:21
 * and the generator fix was claimed at 09:40 — pre-fix residue, not an unlanded
 * fix. A fresh mirror generated through the live service swaps every party
 * correctly.
 *
 * Dry-run by default, matching `estate:backfill-bequests`, `fyn:episodic:purge`
 * and `fyn:user:erase`.
 *
 * Idempotent by construction rather than by a flag: the repair replaces the
 * testator's own name where it appears as a party, so a repaired document no
 * longer matches and a second run finds nothing to do.
 */
class BackfillMirrorWillParties extends Command
{
    protected $signature = 'estate:backfill-mirror-parties
        {--force : Write the changes (default is a dry-run that rolls back)}
        {--user= : Restrict to one user id}';

    protected $description = 'Repair wills that name their own testator as executor, guardian or residuary beneficiary. Dry-run unless --force.';

    public function handle(WillDocumentService $wills): int
    {
        $force = (bool) $this->option('force');

        // AppServiceProvider:208 enables preventLazyLoading outside production.
        // The repair walks user → spouse per document, which a sweep over
        // arbitrary users cannot pre-declare. Relaxed for the duration and
        // restored in the finally block, exactly as estate:backfill-bequests
        // does and for the same reason.
        $lazyLoadingWasPrevented = Model::preventsLazyLoading();
        Model::preventLazyLoading(false);

        try {
            $documents = $this->documentsNamingTheirOwnTestator($wills);

            if ($documents->isEmpty()) {
                $this->info('Nothing to repair: no will names its own testator as a party.');

                return self::SUCCESS;
            }

            $this->line($force ? 'Repairing.' : 'DRY RUN — nothing will be written. Re-run with --force to apply.');
            $this->newLine();

            $rows = [];

            // One transaction so a dry-run reports the REAL outcome — the names
            // the repair actually produced, read back off the row — and then
            // rolls it back. Anything less would be a second implementation
            // guessing at the result.
            DB::beginTransaction();

            try {
                foreach ($documents as $doc) {
                    $before = self::partyNames($doc);
                    $beforeExecutorName = $this->storedExecutorName($doc);

                    $repaired = $wills->repairSelfNamedParties($doc);

                    $fresh = $doc->fresh();

                    $rows[] = [
                        $doc->user_id,
                        $doc->testator_full_name,
                        $doc->id,
                        $repaired ? 'repaired' : 'no change',
                        $before,
                        $fresh === null ? '(gone)' : self::partyNames($fresh),
                        ($beforeExecutorName ?? '(none)').' → '.($this->storedExecutorName($fresh ?? $doc) ?? '(none)'),
                    ];
                }

                if ($force) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Repair aborted, nothing written: '.$e->getMessage());

                return self::FAILURE;
            }
        } finally {
            Model::preventLazyLoading($lazyLoadingWasPrevented);
        }

        $this->info(($force ? 'Repaired ' : 'Would repair ').count($rows).' will(s).');
        $this->newLine();

        $this->table(
            ['User', 'Testator', 'Doc', 'Outcome', 'Parties before', 'Parties after', 'Stored executor name'],
            $rows,
        );

        $this->newLine();
        $this->warn('A will is a legal instrument. Every repaired document should be reviewed by its owner — this restores the names the mirror was meant to carry, it does not confirm they are the names the testator wants.');

        return self::SUCCESS;
    }

    /**
     * The documents that name their own testator as a party.
     *
     * The selector lives on the service, not here, so the command and the repair
     * cannot disagree about what "broken" means (Rule 20).
     *
     * @return Collection<int, WillDocument>
     */
    private function documentsNamingTheirOwnTestator(WillDocumentService $wills): Collection
    {
        $query = WillDocument::with('user')->whereNull('deleted_at');

        if ($this->option('user') !== null) {
            $query->where('user_id', (int) $this->option('user'));
        }

        return $query->get()->filter($wills->namesItsOwnTestator(...))->values();
    }

    private static function partyNames(WillDocument $doc): string
    {
        $names = collect($doc->executors ?? [])
            ->concat($doc->guardians ?? [])
            ->pluck('name')
            ->concat(collect($doc->residuary_estate ?? [])->pluck('beneficiary_name'))
            ->filter()
            ->values();

        return $names->implode(', ') ?: '(none)';
    }

    private function storedExecutorName(WillDocument $doc): ?string
    {
        $will = $doc->will_id !== null
            ? Will::find($doc->will_id)
            : Will::where('user_id', $doc->user_id)->first();

        return $will?->executor_name;
    }
}
