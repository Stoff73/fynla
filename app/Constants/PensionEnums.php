<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * The canonical values for the pension enum columns.
 *
 * This lives in `App\Constants` rather than on `App\Models\DBPension` for a
 * structural reason, not a stylistic one. `tests/Architecture/StoreBoundary/
 * PensionStoreBoundaryTest.php` is a LOCKED allowlist: `DBPension` may only be
 * referenced from the canonical pension write/read set. A form request is not on
 * that list and should not be — it has no business touching a pension model — so
 * declaring the vocabulary on the model would force every validator to either
 * breach the boundary or retype the list and let it drift. Same reasoning, and the
 * same shape, as `ProfileEnums`.
 *
 * The column this mirrors:
 *   db_pensions.scheme_status  varchar(20) NULL
 *
 * Deliberately a plain nullable string rather than a MySQL enum, and deliberately
 * not backfilled (migration 2026_08_21_180000, W-0032). NULL is meaningful: it
 * means the user has never stated a status, and `DBPension::isInPayment()` reads
 * it as "fall back to age against the Normal Retirement Age". Guessing a value for
 * the rows that predate the column would invent the very fact the column exists to
 * record.
 *
 * Values are stored lower snake_case, matching every other enum in the app —
 * `db_pensions.scheme_type` is `final_salary`, `inflation_protection` is `cpi`.
 * The title-case forms the two Defined Benefit forms display, and that Fyn's
 * `create_pension` tool schema declares, are display labels only;
 * `PensionNormaliser::normaliseSchemeStatus()` maps them on the way in.
 *
 * Not to be confused with `investment_accounts.scheme_status`, a different column
 * on a different table for employee share schemes, whose values are
 * active / vesting / exercisable / exercised / expired / forfeited / cancelled.
 */
final class PensionEnums
{
    /** Still building up benefits — not being paid, whatever the member's age. */
    public const SCHEME_STATUS_ACTIVE = 'active';

    /** Left the scheme, not yet drawing — not being paid, whatever the member's age. */
    public const SCHEME_STATUS_DEFERRED = 'deferred';

    /** Being paid now — income today, whatever the member's age. */
    public const SCHEME_STATUS_IN_PAYMENT = 'in_payment';

    /** @var list<string> */
    public const SCHEME_STATUSES = [
        self::SCHEME_STATUS_ACTIVE,
        self::SCHEME_STATUS_DEFERRED,
        self::SCHEME_STATUS_IN_PAYMENT,
    ];
}
