<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class SavingsAccountNormaliser
{
    /**
     * Map HTTP form-validated input to the canonical-array shape consumed
     * by SavingsStore::create(). Replicates the ownership / country logic
     * that previously lived in SavingsController::storeAccount (lines 266-285).
     */
    public function fromForm(array $request): array
    {
        $data = $request;

        $data['ownership_type'] = $data['ownership_type'] ?? 'individual';

        if (! isset($data['ownership_percentage'])) {
            $data['ownership_percentage'] = 100.00;
        }

        if ($data['ownership_type'] === 'joint' && (float) $data['ownership_percentage'] === 100.00) {
            $data['ownership_percentage'] = 50.00;
        }

        // Clear any stale joint_owner_id when switching back to individual ownership.
        // Matches SavingsController::updateAccount (line 390).
        if ($data['ownership_type'] === 'individual') {
            $data['joint_owner_id'] = null;
        }

        // ISA accounts must always be United Kingdom.
        // Non-ISA accounts default to United Kingdom if not provided.
        if (! empty($data['is_isa'])) {
            $data['country'] = 'United Kingdom';
        } elseif (! isset($data['country']) || $data['country'] === null) {
            $data['country'] = 'United Kingdom';
        }

        return $data;
    }
}
