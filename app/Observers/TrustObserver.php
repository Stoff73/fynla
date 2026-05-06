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
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M15
 */
class TrustObserver
{
    public function created(Trust $trust): void
    {
        $initialValue = (float) ($trust->initial_value ?? 0);
        if ($initialValue <= 0) {
            return;
        }

        try {
            Gift::create([
                'user_id' => $trust->user_id,
                'gift_date' => $trust->trust_creation_date,
                'recipient' => $trust->trust_name,
                'gift_type' => 'clt',
                'gift_value' => $initialValue,
                'notes' => 'Chargeable Lifetime Transfer — settlement into trust. Auto-recorded.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TrustObserver] Failed to auto-create CLT gift for trust', [
                'trust_id' => $trust->id,
                'trust_name' => $trust->trust_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
