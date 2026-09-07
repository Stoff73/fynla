<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\CarbonImmutable;

/**
 * W-0197 — the one answer to "at what age does THIS person reach State Pension age?".
 *
 * Before this class the application held two static keys, `current_spa` (66) and
 * `future_spa` (67), and both were correct facts about different cohorts. Four
 * services read the first and one read the second, so a household could be told one
 * State Pension age by the retirement module and a different one by a marketing
 * estimate of the same thing.
 *
 * **Choosing between the two keys could never have been right.** State Pension age is
 * legislated by birth cohort and rises over time — a 46-year-old and a 26-year-old do
 * not share one, and a single scalar gave them one. On a projection running to a second
 * death decades away, a scalar is only ever less wrong. So both keys are retired and
 * the schedule replaces them, the same effective-from shape used for other legislated
 * changes.
 *
 * **This is NOT the retirement-age default.** When someone chooses to stop working is a
 * different question with a different answer — see {@see RetirementAgeResolver}
 * (W-0196). The two were already tangled once: `AssumptionsService` used its
 * retirement-age default as the fallback for this.
 */
final class StatePensionAgeResolver
{
    private const SCHEDULE_KEY = 'pension.state_pension.age_schedule';

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * The State Pension age that applies to a user.
     *
     * A person's own recorded `state_pensions.state_pension_age` wins over anything
     * derived — they may hold a forecast we cannot reproduce, and overriding it with
     * our own arithmetic would be telling them their own statement is wrong.
     */
    public function forUser(User $user): int
    {
        $recorded = $user->statePension?->state_pension_age;

        if ($recorded) {
            return (int) $recorded;
        }

        return $this->forDateOfBirth($user->date_of_birth);
    }

    /**
     * The State Pension age for a birth cohort.
     *
     * A null date of birth resolves to the age of the OLDEST band — the one already in
     * force. It is the only band that is certain for someone whose age we do not know,
     * and it errs towards the earlier, lower figure rather than assuming a person is
     * young enough to be caught by a rise that may never reach them.
     */
    public function forDateOfBirth(mixed $dateOfBirth): int
    {
        $schedule = $this->schedule();

        if ($dateOfBirth === null) {
            return (int) $schedule[0]['age'];
        }

        $born = CarbonImmutable::parse($dateOfBirth)->startOfDay();

        foreach ($schedule as $band) {
            $from = $band['from'] ?? null;
            $to = $band['to'] ?? null;

            $afterStart = $from === null || $born->gte(CarbonImmutable::parse($from)->startOfDay());
            $beforeEnd = $to === null || $born->lte(CarbonImmutable::parse($to)->startOfDay());

            if ($afterStart && $beforeEnd) {
                return (int) $band['age'];
            }
        }

        // Unreachable while the schedule's last band is open-ended, which it must be.
        return (int) end($schedule)['age'];
    }

    /**
     * The State Pension age for someone of a given age today.
     *
     * For callers that hold an age rather than a date of birth — the marketing funnel
     * works in age bands and never asks for one. The derived birth date is
     * approximate by construction, which is honest for a banded estimate and puts it
     * on the same schedule as everything else rather than on a second answer.
     */
    public function forCurrentAge(int $age): int
    {
        return $this->forDateOfBirth(CarbonImmutable::now()->subYears($age));
    }

    /**
     * @return list<array{from: ?string, to: ?string, age: int}>
     */
    private function schedule(): array
    {
        $schedule = $this->taxConfig->get(self::SCHEDULE_KEY);

        if (! is_array($schedule) || $schedule === []) {
            throw new \RuntimeException(
                'pension.state_pension.age_schedule is missing from tax configuration. '
                .'Reseed with TaxConfigurationSeeder — W-0197 retired current_spa and future_spa, '
                .'and there is deliberately no scalar fallback to silently stand in for the schedule.'
            );
        }

        return array_values($schedule);
    }
}
