<?php

declare(strict_types=1);

/**
 * W-0516. Two answers to one question, a year apart:
 * `RetirementProjectionService` carried `$user->statePension?->state_pension_age ?? 67`
 * at two sites while `HouseholdCashFlowProjector` read the statutory cohort schedule
 * through `StatePensionAgeResolver`. A user with no State Pension record had their
 * retirement income projected from 67 and their household cash flow from 66 — and
 * W-0482 had wired the literal one into the projected estate, so a hardcoded statutory
 * age was reaching an Inheritance Tax figure.
 *
 * A single constant is the wrong shape anyway: State Pension age rises to 67 between
 * 2026 and 2028 under the Pensions Act 2014 and to 68 thereafter, so the answer depends
 * on the birth cohort. W-0197 built the resolver that reads that schedule; this asserts
 * nobody goes round it.
 */
it('resolves State Pension age from the schedule and never from a literal', function () {
    // Every age the timetable can currently yield, so a new literal cannot hide by
    // matching one band. Written as a pattern rather than the digits themselves, or
    // this test would be the thing it forbids.
    $ages = ['66', '67', '68'];

    $offenders = [];

    foreach (['app/Services', 'app/Agents', 'app/Http'] as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__.'/../../'.$directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($ages as $age) {
                // The exact shape the defect took: a State Pension age column read with
                // a numeric fallback. It is the fallback that is the literal, and the
                // column name beside it is what makes it a State Pension age rather
                // than any other number.
                if (preg_match('/state_pension_age\s*\?\?\s*'.$age.'\b/', $contents)) {
                    $offenders[] = str_replace(realpath(__DIR__.'/../../').'/', '', $file->getPathname());
                }
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

it('keeps the resolver as the only reader of the cohort schedule', function () {
    // The schedule's configuration keys, read anywhere but the resolver, would be a
    // second implementation of the same lookup — which is how the two answers arose.
    $offenders = shell_exec(sprintf(
        'grep -rl %s %s 2>/dev/null | grep -v StatePensionAgeResolver',
        escapeshellarg('state_pension_age_schedule'),
        escapeshellarg(__DIR__.'/../../app')
    ));

    expect(trim((string) $offenders))->toBe('');
});
