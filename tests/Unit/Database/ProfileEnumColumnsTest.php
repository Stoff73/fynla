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
 * smoking_status being NOT NULL is load-bearing: it is why an unanswered select
 * drops its key rather than sending null, and why that rule alone is not
 * `nullable`. Making the column nullable means revisiting both.
 */
it('records that smoking_status is NOT NULL, which the request rules depend on', function (): void {
    $column = DB::selectOne(
        "SELECT IS_NULLABLE AS is_nullable
           FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'smoking_status'"
    );

    expect($column->is_nullable)->toBe('NO');
});
