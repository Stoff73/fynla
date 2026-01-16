<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\FamilyMember;

/**
 * Observer that triggers risk recalculation when FamilyMember changes.
 *
 * Monitors: dependant count changes
 */
class FamilyMemberRiskObserver extends RiskRecalculationObserver
{
    public function created(FamilyMember $member): void
    {
        if ($member->is_dependent) {
            $this->dispatchRecalculation($member->user_id, 'family_member_created');
        }
    }

    public function updated(FamilyMember $member): void
    {
        $changedFields = array_keys($member->getChanges());

        if (in_array('is_dependent', $changedFields, true)) {
            $this->dispatchRecalculation($member->user_id, 'family_member_updated');
        }
    }

    public function deleted(FamilyMember $member): void
    {
        if ($member->is_dependent) {
            $this->dispatchRecalculation($member->user_id, 'family_member_deleted');
        }
    }
}
