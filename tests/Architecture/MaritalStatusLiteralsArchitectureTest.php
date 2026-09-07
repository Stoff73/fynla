<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * W-0480 — one household rule, not a literal list per consumer.
 *
 * `App\Support\HouseholdPooling` holds the marital statuses that pool two people's
 * records into one estate, and the predicate for asking. A civil partnership is a
 * marriage throughout Inheritance Tax, so a consumer that writes `['married']` by hand
 * gives a civil partnership the answer it gives a single person.
 *
 * **Why a sweep and not a review.** The defect propagates by copy: W-0474 fixed one
 * service and the reviewer, checking its siblings, found four more (W-0480). Only a
 * sweep sees the next copy, so this fails on any NEW site and on any baselined site
 * whose line has changed — including one that has been fixed, so the entry gets pruned
 * rather than rotting.
 *
 * **What it cannot see.** It reads one line at a time, so an `in_array` split across
 * lines slips past. That is the price of a grep guard, and it still catches the copy —
 * a copy is copied whole.
 */
it('has no new consumer branching on marital_status with its own literal list', function () {
    /**
     * Sites that still read `['married']` alone. **Every one of these is the W-0480
     * defect, unfixed** — they are recorded, not blessed. W-0480 was filed against the
     * four services its reviewer had checked; this sweep found the rest, and they are
     * W-0508's to close. Fix one and this test tells you to delete its line here.
     */
    $known = [
        'app/Agents/EstateAgent.php: if ($user->marital_status === \'married\' && $user->spouse === null) {',
        'app/Agents/ProtectionAgent.php: if ($user->marital_status === \'married\' && $user->spouse === null) {',
        'app/Agents/RetirementAgent.php: if ($user?->marital_status === \'married\' && $profile?->spouse_life_expectancy === null) {',
        'app/Agents/SavingsAgent.php: if ($user && $user->marital_status === \'married\' && $user->spouse === null) {',
        'app/Http/Controllers/Api/EstateController.php: $spouse = $user->marital_status === \'married\' ? $user->liveSpouse() : null;',
        'app/Services/LifeStage/LifeStageService.php: return $user->marital_status === \'married\';',
        'app/Services/Protection/CoverageGapAnalyzer.php: if ($user->liveSpouseId() && $user->marital_status === \'married\') {',
    ];

    $found = [];
    $base = base_path();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base.'/app', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = 'app/'.ltrim(str_replace($base.'/app', '', $file->getPathname()), '/');

        foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) as $line) {
            $trimmed = trim($line);

            if (! str_contains($trimmed, 'marital_status')) {
                continue;
            }

            // Prose about the rule is not an application of it.
            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            // Reading the shared list is the point; a line that names both statuses is
            // making the same distinction this guard is asking for.
            if (str_contains($trimmed, 'civil_partnership') || ! str_contains($trimmed, "'married'")) {
                continue;
            }

            // A comparison against the literal, or an in_array over marital_status —
            // not `'marital_status' => 'married'`, which sets a value rather than
            // branching on one.
            $isComparison = preg_match('/(===|!==|==)\s*\'married\'|\'married\'\s*(===|!==|==)/', $trimmed) === 1;
            $isInArray = str_contains($trimmed, 'in_array');

            if (! $isComparison && ! $isInArray) {
                continue;
            }

            $found[] = $relative.': '.$trimmed;
        }
    }

    sort($found);
    sort($known);

    $new = array_diff($found, $known);
    $stale = array_diff($known, $found);

    expect($new)->toBe(
        [],
        "New consumer branching on marital_status with its own literal list. Read \App\Support\HouseholdPooling::hasSpousalStatus() instead — a civil partnership is a marriage for this purpose (W-0480):\n  ".implode("\n  ", $new)
    );

    expect($stale)->toBe(
        [],
        "Baselined site no longer present — delete its line from this test's \$known list:\n  ".implode("\n  ", $stale)
    );
});
