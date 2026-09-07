<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Estate\Gift;
use App\Models\Estate\Trust;
use Illuminate\Support\Facades\Log;

/**
 * FR-M15 — Trust CLT orphan prevention.
 *
 * A trust settlement is a Chargeable Lifetime Transfer for IHT purposes,
 * which must be tracked under the 7-year rule with taper relief. The CLT
 * used to be written directly from CoordinatingAgent::handleCreateTrust
 * before the user had confirmed the trust form, which left orphan Gift
 * rows whenever the user cancelled the form — and those orphans caused
 * IHT calculations to double-count unsaved settlements.
 *
 * This observer moves the CLT write to the `created` event on the Trust
 * model, so the Gift is written if and only if the Trust is actually
 * saved. Errors are logged and swallowed — the CLT being missing is
 * recoverable (the user or a reconcile job can add it later) whereas
 * throwing from the observer would prevent the Trust from being saved
 * at all.
 *
 * **W-0528 — the settlement gift now tracks the trust for its whole life.**
 * `created` was the only event handled, and the gift is what withholds the
 * settlor's nil rate band for seven years, so the estate went wrong in both
 * directions the moment a trust changed: raising the settled amount left the
 * old, smaller figure withholding the band, and deleting the trust left the
 * gift behind withholding a band for a settlement that no longer existed.
 *
 * The trust is the source of truth for its own settlement. A gift with no
 * `trust_id` was entered by the user by hand and is never touched here.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M15
 */
class TrustObserver
{
    public function created(Trust $trust): void
    {
        $this->syncSettlementGift($trust);
    }

    public function updated(Trust $trust): void
    {
        $this->syncSettlementGift($trust);
    }

    /**
     * Soft delete. The settlement no longer stands, so it stops withholding band.
     */
    public function deleted(Trust $trust): void
    {
        $this->guard($trust, function () use ($trust): void {
            Gift::where('trust_id', $trust->id)->delete();
        });
    }

    public function restored(Trust $trust): void
    {
        $this->syncSettlementGift($trust);
    }

    /**
     * The `trust_id` foreign key cascades, so the row goes either way. Doing it
     * here as well keeps the model events firing, which is what invalidates the
     * cached IHT calculation via `UserDataCacheObserver`.
     */
    public function forceDeleted(Trust $trust): void
    {
        $this->guard($trust, function () use ($trust): void {
            Gift::withTrashed()->where('trust_id', $trust->id)->forceDelete();
        });
    }

    /**
     * Bring the settlement gift into line with the trust as it now stands.
     */
    private function syncSettlementGift(Trust $trust): void
    {
        $this->guard($trust, function () use ($trust): void {
            $initialValue = (float) ($trust->initial_value ?? 0);
            $gift = Gift::withTrashed()->where('trust_id', $trust->id)->first();

            // Nothing was settled, so nothing is chargeable. A trust edited DOWN to
            // zero has to release the band it was holding, which is why this deletes
            // rather than returning early as the original did.
            if ($initialValue <= 0) {
                $gift?->delete();

                return;
            }

            $mirrored = [
                'gift_date' => $trust->trust_creation_date,
                'recipient' => $trust->trust_name,
                'gift_value' => $initialValue,
            ];

            if ($gift === null) {
                Gift::create($mirrored + [
                    'user_id' => $trust->user_id,
                    'trust_id' => $trust->id,
                    'gift_type' => 'clt',
                    'notes' => 'Chargeable Lifetime Transfer — settlement into trust. Auto-recorded.',
                ]);

                return;
            }

            // Restored, or brought back by an edit from zero.
            if ($gift->trashed()) {
                $gift->restore();
            }

            // `notes` is deliberately not mirrored — the user may have annotated the
            // gift, and nothing about the trust contradicts what they wrote.
            $gift->update($mirrored);
        });
    }

    /**
     * Errors are logged and swallowed, as they were for the original write: a
     * missing or stale CLT is recoverable, whereas throwing from an observer would
     * stop the user saving the trust at all.
     */
    private function guard(Trust $trust, callable $work): void
    {
        try {
            $work();
        } catch (\Throwable $e) {
            Log::warning('[TrustObserver] Failed to sync the settlement CLT gift for trust', [
                'trust_id' => $trust->id,
                'trust_name' => $trust->trust_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
