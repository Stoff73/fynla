<?php

declare(strict_types=1);

use App\Constants\ProfileEnums;
use Illuminate\Support\Facades\DB;

/**
 * The enforcement behind `App\Constants\ProfileEnums`.
 *
 * The request rules and the `users` columns had drifted twice — W-0006 (the rules
 * named `good_health` / `smoker`, two columns that do not exist, so every health
 * and smoking value was stripped in silence) and W-0031 (the rules then allowed
 * `doctorate`, `foundation` and `hnd` for `education_level`, which the column enum
 * cannot hold, so validation passed and the write died as a 500 — reachable from a
 * live select on the Personal Information page).
 *
 * A hand-written copy of a column's enum is a copy that will drift. This reads the
 * column and goes red the moment either side changes without the other.
 */
function usersColumnEnumValues(string $column): array
{
    $definition = DB::selectOne(
        'SELECT COLUMN_TYPE AS column_type
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?',
        ['users', $column]
    );

    expect($definition)->not->toBeNull("users.{$column} does not exist");

    preg_match_all("/'((?:[^']|'')*)'/", $definition->column_type, $matches);

    return array_map(static fn (string $value): string => str_replace("''", "'", $value), $matches[1]);
}

it('pins HEALTH_STATUSES to the users.health_status column', function (): void {
    expect(ProfileEnums::HEALTH_STATUSES)->toBe(usersColumnEnumValues('health_status'));
});

it('pins SMOKING_STATUSES to the users.smoking_status column', function (): void {
    expect(ProfileEnums::SMOKING_STATUSES)->toBe(usersColumnEnumValues('smoking_status'));
});

it('pins EDUCATION_LEVELS to the users.education_level column', function (): void {
    expect(ProfileEnums::EDUCATION_LEVELS)->toBe(usersColumnEnumValues('education_level'));
});

/**
 * Item 8a (CSJ 2026-10-06): null is "not answered". The old defaults ('never',
 * 'yes') read every unasked user as a non-smoker in good health.
 */
it('stores smoking and health as nullable with no default, so not answered stays not answered', function (string $column): void {
    $definition = DB::selectOne(
        "SELECT IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ?",
        [$column]
    );

    expect($definition->is_nullable)->toBe('YES')
        ->and($definition->column_default)->toBeNull();
})->with(['smoking_status', 'health_status']);

it('counts a smoker as anyone who smoked in the last 12 months, and keeps not answered as null', function (): void {
    expect(ProfileEnums::isSmoker('yes'))->toBeTrue()
        ->and(ProfileEnums::isSmoker('quit_recent'))->toBeTrue()
        ->and(ProfileEnums::isSmoker('quit_long_ago'))->toBeFalse()
        ->and(ProfileEnums::isSmoker('never'))->toBeFalse()
        ->and(ProfileEnums::isSmoker(null))->toBeNull()
        ->and(ProfileEnums::hasHealthHistory('yes'))->toBeFalse()
        ->and(ProfileEnums::hasHealthHistory('yes_previous'))->toBeTrue()
        ->and(ProfileEnums::hasHealthHistory('no_both'))->toBeTrue()
        ->and(ProfileEnums::hasHealthHistory(null))->toBeNull()
        ->and(array_keys(ProfileEnums::SMOKING_STATUS_LABELS))->toBe(ProfileEnums::SMOKING_STATUSES)
        ->and(array_keys(ProfileEnums::HEALTH_STATUS_LABELS))->toBe(ProfileEnums::HEALTH_STATUSES);
});
