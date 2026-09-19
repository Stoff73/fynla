<?php

declare(strict_types=1);

namespace App\Services\Income;

use App\Models\Employment;
use App\Models\User;

/**
 * The one home for writing a job and keeping the user's income totals in step.
 *
 * Onboarding has always invited a second job (OnboardingStateMachine's
 * "Phase 10 — multi-job loop"), but every write landed on the single
 * users.annual_employment_income column, so job two silently replaced job one
 * and the first salary was gone. Jobs now live in `employments`; the two user
 * columns are the maintained totals, because 204 call sites read them and all
 * of them want the total rather than a per-job figure.
 *
 * Nothing else may write those two columns for employment income — a second
 * writer is how they drift out of step with the rows they are meant to total.
 */
class EmploymentIncomeService
{
    /**
     * Record what the user just told us about their work.
     *
     * A payload with no income refines the job in hand (multi-turn extraction
     * filling in an employer after the salary). A payload with an income is a
     * job: the same employer and role at a new figure is a correction, anything
     * else is another job. Re-sending an identical payload changes nothing —
     * the LLM emitting the same tool call twice must never double a salary.
     */
    public function recordJob(User $user, ?string $employer, ?string $occupation, ?float $income): Employment
    {
        $type = $this->incomeTypeFor($user);
        $latest = $user->employments()->where('income_type', $type)->latest('id')->first();

        if ($income === null) {
            $job = $latest ?: new Employment(['user_id' => $user->id, 'income_type' => $type, 'annual_income' => 0]);
        } elseif ($latest && $this->sameRole($latest, $employer, $occupation)) {
            $job = $latest;
        } else {
            $job = new Employment(['user_id' => $user->id, 'income_type' => $type]);
        }

        $job->user_id = $user->id;
        $job->income_type = $type;
        if ($employer !== null && $employer !== '') {
            $job->employer = $employer;
        }
        if ($occupation !== null && $occupation !== '') {
            $job->occupation = $occupation;
        }
        if ($income !== null) {
            $job->annual_income = $income;
        }
        $job->save();

        $this->syncTotals($user);

        return $job;
    }

    /**
     * Change one job the user already told us about (the Fyn edit form,
     * CSJ 2026-09-19). Only the fields given change; the totals follow.
     */
    public function updateJob(User $user, Employment $job, ?string $employer, ?string $occupation, ?float $income): Employment
    {
        if ($employer !== null && $employer !== '') {
            $job->employer = $employer;
        }
        if ($occupation !== null && $occupation !== '') {
            $job->occupation = $occupation;
        }
        if ($income !== null) {
            $job->annual_income = $income;
        }
        $job->save();

        $this->syncTotals($user);

        return $job;
    }

    /**
     * Rewrite the user's two income totals from the jobs on file. Call after any
     * change to `employments` — this is what keeps the columns every other
     * service reads equal to the rows the income page shows.
     */
    public function syncTotals(User $user): void
    {
        $totals = $user->employments()
            ->selectRaw('income_type, SUM(annual_income) as total')
            ->groupBy('income_type')
            ->pluck('total', 'income_type');

        $user->annual_employment_income = (float) ($totals['employment'] ?? 0);
        $user->annual_self_employment_income = (float) ($totals['self_employment'] ?? 0);

        // The user row keeps the most recent job's label: the income pages read
        // it for the "employer · role" line beside the employment total.
        $latest = $user->employments()->latest('id')->first();
        if ($latest) {
            $user->employer = $latest->employer ?: $user->employer;
            $user->occupation = $latest->occupation ?: $user->occupation;
        }

        $user->save();
    }

    /** Self-employment is taxed differently, so a job must say which it is. */
    private function incomeTypeFor(User $user): string
    {
        return $user->employment_status === 'self_employed' ? 'self_employment' : 'employment';
    }

    /**
     * Same job being corrected, rather than a new one. A blank employer or role
     * in the payload cannot distinguish two jobs, so it counts as a match and
     * the figure in hand is corrected instead of a second row appearing.
     */
    private function sameRole(Employment $job, ?string $employer, ?string $occupation): bool
    {
        $matches = fn (?string $incoming, ?string $stored): bool => $incoming === null
            || $incoming === ''
            || mb_strtolower(trim($incoming)) === mb_strtolower(trim((string) $stored));

        return $matches($employer, $job->employer) && $matches($occupation, $job->occupation);
    }
}
