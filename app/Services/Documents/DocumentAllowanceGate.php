<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;

/**
 * SP2 PR8 §11 — Document upload allowance and storage quota gate.
 *
 * Rolling COUNT gate: a new upload is blocked when the user's retained
 * document count ≥ document_upload_allowance for their resolved tier.
 * Grandfather rule: never deletes existing documents, only blocks NEW ones.
 *
 * Storage ceiling (tier2/tier3 only): blocks a new upload when total retained
 * storage would exceed document_storage_gb.
 *
 * Preview users and admins are fully exempt (Rule #2, SP1 §14.2).
 */
class DocumentAllowanceGate
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    /**
     * Returns null when the upload is allowed.
     * Returns a structured upgrade-CTA array (mirroring DbTierGate/TeaserGate shape)
     * when the allowance or storage ceiling would be exceeded.
     *
     * @return array{allowed: false, reason: string, entity_key: string, limit: int|null, target_tier: array{tier: string, display_name: string}|null}|null
     */
    public function check(User $user, int $newFileSizeBytes = 0): ?array
    {
        if ($user->is_admin) {
            return null; // SP1 §14.2 allowlist
        }
        if ($user->is_preview_user) {
            return null; // preview personas sit entirely outside the gate (Rule #2)
        }

        $tier = $this->resolver->resolve($user);
        $config = $this->store->forTier($tier);

        // Count gate: SoftDeletes global scope already excludes soft-deleted rows.
        $retainedCount = Document::where('user_id', $user->id)
            ->count();

        $allowance = $config->document_upload_allowance;

        if ($retainedCount >= $allowance) {
            // Find the lowest tier with a strictly higher allowance
            $upgrade = null;
            foreach (TierConfigurationStore::TIERS as $candidate) {
                if ($this->store->forTier($candidate)->document_upload_allowance > $allowance) {
                    $upgrade = ['tier' => $candidate, 'display_name' => $this->store->forTier($candidate)->display_name];
                    break;
                }
            }

            return [
                'allowed' => false,
                'reason' => "Document allowance reached ({$retainedCount}/{$allowance}). Upgrade to store more documents.",
                'entity_key' => 'document_upload',
                'limit' => $allowance,
                'target_tier' => $upgrade,
            ];
        }

        // Storage ceiling gate (only applicable when document_storage_gb is set)
        if ($config->document_storage_gb !== null) {
            $storageCeilingBytes = (float) $config->document_storage_gb * 1024 * 1024 * 1024;

            // SoftDeletes global scope already excludes soft-deleted rows.
            $usedBytes = Document::where('user_id', $user->id)
                ->sum('file_size');

            if (($usedBytes + $newFileSizeBytes) > $storageCeilingBytes) {
                $upgrade = null;
                foreach (TierConfigurationStore::TIERS as $candidate) {
                    $cand = $this->store->forTier($candidate);
                    if ($cand->document_storage_gb !== null
                        && (float) $cand->document_storage_gb > (float) $config->document_storage_gb) {
                        $upgrade = ['tier' => $candidate, 'display_name' => $cand->display_name];
                        break;
                    }
                }

                $gbUsed = number_format($usedBytes / (1024 * 1024 * 1024), 2);
                $gbLimit = number_format((float) $config->document_storage_gb, 2);

                return [
                    'allowed' => false,
                    'reason' => "Document storage limit reached ({$gbUsed} GB / {$gbLimit} GB). Upgrade for more storage.",
                    'entity_key' => 'document_storage',
                    'limit' => null,
                    'target_tier' => $upgrade,
                ];
            }
        }

        return null;
    }
}
