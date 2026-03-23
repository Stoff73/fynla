<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\NetWorth\NetWorthService;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates the net worth cache whenever any asset or liability model changes.
 *
 * Registered on: Property, Mortgage, SavingsAccount, InvestmentAccount,
 * DCPension, BusinessInterest, Chattel, Estate\Asset, Estate\Liability.
 */
class NetWorthCacheObserver
{
    public function __construct(
        private readonly NetWorthService $netWorthService
    ) {}

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $userId = $model->user_id ?? null;

        if ($userId) {
            $this->netWorthService->invalidateCache($userId);
        }

        // Also invalidate for joint owner if applicable
        $jointOwnerId = $model->joint_owner_id ?? null;
        if ($jointOwnerId) {
            $this->netWorthService->invalidateCache($jointOwnerId);
        }
    }
}
