<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiAdviceLog;
use App\Models\User;

/**
 * Detects when user data has changed since the last advice was given,
 * and identifies modules due for annual review.
 *
 * Used by SystemPromptBuilder to inject <review_due> prompts.
 */
class AdviceReviewService
{
    /**
     * Check for data changes since last advice and modules due for review.
     *
     * @return array{changes: array, reviews_due: array}
     */
    public function checkForChanges(User $user): array
    {
        $lastAdvice = AiAdviceLog::forUser($user->id)
            ->latest()
            ->first();

        $changes = [];

        if ($lastAdvice && $lastAdvice->user_data_snapshot) {
            $snapshot = $lastAdvice->user_data_snapshot;

            $currentIncome = (float) $user->annual_employment_income + (float) $user->annual_self_employment_income;
            if (isset($snapshot['income']) && abs($currentIncome - (float) $snapshot['income']) > 1000) {
                $changes[] = [
                    'field' => 'income',
                    'previous' => (float) $snapshot['income'],
                    'current' => $currentIncome,
                    'advice_date' => $lastAdvice->created_at->toDateString(),
                ];
            }

            $currentExpenditure = (float) ($user->monthly_expenditure ?? 0);
            if (isset($snapshot['expenditure']) && abs($currentExpenditure - (float) $snapshot['expenditure']) > 100) {
                $changes[] = [
                    'field' => 'expenditure',
                    'previous' => (float) $snapshot['expenditure'],
                    'current' => $currentExpenditure,
                    'advice_date' => $lastAdvice->created_at->toDateString(),
                ];
            }

            if (isset($snapshot['employment_status']) && $user->employment_status !== $snapshot['employment_status']) {
                $changes[] = [
                    'field' => 'employment_status',
                    'previous' => $snapshot['employment_status'],
                    'current' => $user->employment_status,
                    'advice_date' => $lastAdvice->created_at->toDateString(),
                ];
            }

            if (isset($snapshot['marital_status']) && $user->marital_status !== $snapshot['marital_status']) {
                $changes[] = [
                    'field' => 'marital_status',
                    'previous' => $snapshot['marital_status'],
                    'current' => $user->marital_status,
                    'advice_date' => $lastAdvice->created_at->toDateString(),
                ];
            }
        }

        $reviewsDue = $this->getModulesOverdueForReview($user);

        return [
            'changes' => $changes,
            'reviews_due' => $reviewsDue,
        ];
    }

    /**
     * Find modules where advice is older than 12 months.
     */
    public function getModulesOverdueForReview(User $user): array
    {
        $modules = ['protection', 'savings', 'retirement', 'investment', 'estate'];
        $overdue = [];

        foreach ($modules as $module) {
            $lastModuleAdvice = AiAdviceLog::forUser($user->id)
                ->forModule($module)
                ->latest()
                ->first();

            if ($lastModuleAdvice && $lastModuleAdvice->created_at->lt(now()->subMonths(12))) {
                $monthsAgo = (int) $lastModuleAdvice->created_at->diffInMonths(now());
                $overdue[] = [
                    'module' => $module,
                    'last_reviewed' => $lastModuleAdvice->created_at->toDateString(),
                    'months_ago' => $monthsAgo,
                ];
            }
        }

        return $overdue;
    }
}
