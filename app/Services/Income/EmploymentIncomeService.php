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
     * The most one income figure may be: the range capture_work_details
     * accepts, and within employments.annual_income decimal(12,2).
     */
    public const MAX_ANNUAL_INCOME = 99_999_999;

    /**
     * Record what the user just told us about their work.
     *
     * A payload with no income refines the job in hand (multi-turn extraction
     * filling in an employer after the salary). A payload with an income is a
     * job: the same employer and role at a new figure is a correction, anything
     * else is another job. Re-sending an identical payload changes nothing —
     * the LLM emitting the same tool call twice must never double a salary.
     *
     * An estimate someone else gave (recordEstimate) is replaced by the
     * person's own first job, whatever they call it — it is a guess at that
     * same income, never a second one.
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
            $job = $user->employments()->where('is_estimate', true)->latest('id')->first()
                ?: new Employment(['user_id' => $user->id, 'income_type' => $type]);
        }

        $job->user_id = $user->id;
        $job->income_type = $type;
        // The flag is about the FIGURE. Naming the job without one leaves the
        // inviter's guess in place, so it stays an estimate until an income
        // arrives — otherwise the next full payload lands as a second row.
        if ($income !== null) {
            $job->is_estimate = false;
        }
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
     * Hold a figure someone else gave for this person's income — the income a
     * user entered for their spouse, copied across when the spouse's account
     * links. It counts towards the totals until the person gives their own,
     * which replaces it (recordJob) instead of being added to it.
     */
    public function recordEstimate(User $user, float $income): Employment
    {
        if ($income <= 0 || $income > self::MAX_ANNUAL_INCOME) {
            throw new \InvalidArgumentException('Estimated income out of range');
        }
        $type = $this->incomeTypeFor($user);
        $job = Employment::create([
            'user_id' => $user->id,
            'income_type' => $type,
            'annual_income' => $income,
            'is_estimate' => true,
        ]);

        $this->syncTotals($user);

        return $job;
    }

    /**
     * The user stated their own total for one income type on the income page.
     * Someone else's estimate (recordEstimate) no longer stands: it goes, and
     * when it was the only row of that type the stated figure takes its place,
     * so syncTotals can never bring the inviter's figure back (C1, 2026-09-29).
     * Without an estimate on file nothing changes here.
     */
    public function replaceEstimateWithStated(User $user, string $type, float $stated): void
    {
        $estimates = $user->employments()->where('income_type', $type)->where('is_estimate', true)->get();
        if ($estimates->isEmpty()) {
            return;
        }
        $estimates->each->delete();
        $hasOwn = $user->employments()->where('income_type', $type)->exists();
        if (! $hasOwn && $stated > 0 && $stated <= self::MAX_ANNUAL_INCOME) {
            Employment::create(['user_id' => $user->id, 'income_type' => $type, 'annual_income' => $stated, 'is_estimate' => false]);
        }
        $this->syncTotals($user);
    }

    /**
     * The user says they do not work, so someone else's estimate of their pay
     * (recordEstimate) no longer stands: it goes, and its amount is returned
     * for the caller to store as what it really is. 0 when there was none.
     */
    public function dropEstimates(User $user): float
    {
        $estimates = $user->employments()->where('is_estimate', true)->get();
        if ($estimates->isEmpty()) {
            return 0.0;
        }
        $amount = (float) $estimates->sum('annual_income');
        $estimates->each->delete();
        $this->syncTotals($user);

        return $amount;
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
        $job->is_estimate = false;
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
