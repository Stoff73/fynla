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
     * The State Pension age that applies to a user, in whole years reached (the
     * age on the day it is reached, so 66 for "66 years and 5 months"). For a
     * comparison or an amount, use dateForUser / fractionPaidAtAge, which hold
     * the months; for words, labelForUser.
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

        return $this->forDateOfBirth($user->date_of_birth, $user->gender);
    }

    /**
     * The State Pension age for a birth cohort, in whole years reached.
     *
     * A null date of birth resolves to the first whole-year age that applies to
     * both genders (66, para 1(6)): the age already in force, erring early
     * rather than assuming a person is young enough to be caught by a rise.
     */
    public function forDateOfBirth(mixed $dateOfBirth, ?string $gender = null): int
    {
        if ($dateOfBirth === null) {
            return $this->unknownBirthAge();
        }

        $born = CarbonImmutable::parse($dateOfBirth)->startOfDay();

        return (int) $born->diffInYears($this->dateForDateOfBirth($born, $gender));
    }

    /**
     * The day a user reaches State Pension age: their recorded age from their
     * date of birth, else the statutory schedule. Null without a date of birth.
     */
    public function dateForUser(User $user): ?CarbonImmutable
    {
        if ($user->date_of_birth === null) {
            return null;
        }

        $recorded = $user->statePension?->state_pension_age;
        if ($recorded) {
            return CarbonImmutable::parse($user->date_of_birth)->startOfDay()->addYears((int) $recorded);
        }

        return $this->dateForDateOfBirth($user->date_of_birth, $user->gender);
    }

    /**
     * The day someone born on $dateOfBirth reaches State Pension age (Pensions
     * Act 1995 Sch 4 para 1). Before 6 December 1953 the age differs by gender;
     * with none recorded, the later (men's 65) is taken.
     */
    public function dateForDateOfBirth(mixed $dateOfBirth, ?string $gender = null): CarbonImmutable
    {
        $born = CarbonImmutable::parse($dateOfBirth)->startOfDay();
        $band = $this->bandFor($born, $gender);

        if (isset($band['date'])) {
            return CarbonImmutable::parse($band['date'])->startOfDay();
        }

        return $born->addYears((int) $band['age'])->addMonthsNoOverflow((int) ($band['months'] ?? 0));
    }

    /** "66 years and 5 months", or "67" for a whole number of years. */
    public function labelForUser(User $user): string
    {
        $date = $this->dateForUser($user);

        return $date === null
            ? (string) $this->unknownBirthAge()
            : self::label(CarbonImmutable::parse($user->date_of_birth)->startOfDay(), $date);
    }

    public function labelForDateOfBirth(mixed $dateOfBirth, ?string $gender = null): string
    {
        if ($dateOfBirth === null) {
            return (string) $this->unknownBirthAge();
        }
        $born = CarbonImmutable::parse($dateOfBirth)->startOfDay();

        return self::label($born, $this->dateForDateOfBirth($born, $gender));
    }

    /**
     * The share of the year from the user's birthday at $age to the next that
     * falls on or after the day they reach State Pension age: 1 once reached,
     * 0 before, and the part-year in the year it is reached (66 and 5 months:
     * 7/12 of the year at 66). For yearly projections, so State Pension starts
     * in the month it is paid from rather than at a rounded age.
     */
    public function fractionPaidAtAge(User $user, int $age): float
    {
        $date = $this->dateForUser($user);
        if ($date === null) {
            return $age >= $this->unknownBirthAge() ? 1.0 : 0.0;
        }

        $born = CarbonImmutable::parse($user->date_of_birth)->startOfDay();
        $start = $born->addYears($age);
        $end = $born->addYears($age + 1);

        if ($date->lte($start)) {
            return 1.0;
        }
        if ($date->gte($end)) {
            return 0.0;
        }

        return round($date->diffInDays($end) / $start->diffInDays($end), 4);
    }

    /** Whether someone stopping at $age (a birthday) does so before State Pension age. */
    public function isBeforeStatePensionAge(User $user, int $age): bool
    {
        $date = $this->dateForUser($user);
        if ($date === null) {
            return $age < $this->unknownBirthAge();
        }

        return CarbonImmutable::parse($user->date_of_birth)->startOfDay()->addYears($age)->lt($date);
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

    private static function label(CarbonImmutable $born, CarbonImmutable $date): string
    {
        $months = (int) $born->diffInMonths($date);
        $years = intdiv($months, 12);
        $rest = $months % 12;

        return $rest === 0
            ? (string) $years
            : $years.' years and '.$rest.' '.($rest === 1 ? 'month' : 'months');
    }

    private function unknownBirthAge(): int
    {
        foreach ($this->schedule() as $band) {
            if (isset($band['age']) && ! isset($band['gender']) && ! isset($band['months'])) {
                return (int) $band['age'];
            }
        }

        $schedule = $this->schedule();

        return (int) (end($schedule)['age'] ?? 0);
    }

    /** @return array<string, mixed> */
    private function bandFor(CarbonImmutable $born, ?string $gender): array
    {
        $gender = in_array($gender, ['male', 'female'], true) ? $gender : 'male';

        foreach ($this->schedule() as $band) {
            if (isset($band['gender']) && $band['gender'] !== $gender) {
                continue;
            }
            $from = $band['from'] ?? null;
            $to = $band['to'] ?? null;

            $afterStart = $from === null || $born->gte(CarbonImmutable::parse($from)->startOfDay());
            $beforeEnd = $to === null || $born->lte(CarbonImmutable::parse($to)->startOfDay());

            if ($afterStart && $beforeEnd) {
                return $band;
            }
        }

        // Unreachable while the schedule's last band is open-ended, which it must be.
        $schedule = $this->schedule();

        return end($schedule);
    }

    /**
     * @return list<array{from: ?string, to: ?string, age?: int, months?: int, date?: string, gender?: string}>
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
