<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W-0323 — the one row W-0263's sweep deliberately left alone, now settled.
 *
 * `cash_accounts.interest_rate` is `decimal(5,4)`, which stops at 9.9999. W-0263
 * widened every other narrow rate column because live rows proved they held
 * percentages, and left this one because `cash_accounts` holds zero rows, no form
 * request writes it, and widening on that evidence would have been a guess at the
 * units of a column nobody writes. That caution was right.
 *
 * The units are now determined, without guessing, from the writer the sweep did
 * not look for. It searched form requests; the writer is a console command.
 *
 * `App\Console\Commands\MigrateSavingsToCash:159` — deleted under W-0489, quoted here
 * because it is where the units were established — set
 * `'interest_rate' => $account->interest_rate`, copied verbatim from
 * `savings_accounts`. So whatever that column holds, this one would hold.
 *
 * `savings_accounts.interest_rate` holds **percentages**:
 *
 *   - live values run 0.0000 to 5.1000, mean 1.886 across 25 rows — 5.1 meaning
 *     5.1%, not 510%;
 *   - its validation message says "cannot exceed 20%";
 *   - W-0263 widened it to `decimal(8,4)` for exactly that reason.
 *
 * A rate of 20% cannot be stored in `decimal(5,4)`. So this column is too narrow
 * under the only writer it has, and matching `savings_accounts` at `decimal(8,4)`
 * is the evidenced size rather than a chosen one. Widening is also the safe
 * direction regardless of which reading of the table wins: `decimal(8,4)` stores
 * fractions perfectly well, so if a future writer did store 0.051, nothing breaks.
 *
 * The column comment is corrected in the same breath. It reads "Annual interest
 * rate as decimal" — the identical stale boilerplate that sat on
 * `mortgages.fixed_interest_rate` and `variable_interest_rate` until W-0263 found
 * it was wrong there too. Leaving it would send the next reader down the same
 * blind alley the item warns about.
 *
 * Raw `MODIFY COLUMN` rather than `->change()`, matching W-0263: doctrine/dbal is
 * not installed, and `->change()` silently drops attributes it is not given.
 *
 * Reversibility: `down()` restores the original definition exactly. It is safe
 * here in a way it is not for W-0263's columns, because the table holds no rows —
 * but it would refuse on any database where a rate of 10% or more had since been
 * written, which is the point of the widening.
 *
 * This migration does NOT address what the table is for. `cash_accounts` is read
 * by live code, described by its own model as current accounts rather than
 * savings, and described by that console command as the replacement for savings —
 * three answers at once. See W-0498.
 */
return new class extends Migration
{
    /**
     * [table, column, widened definition, original definition].
     *
     * Definitions spelled out in full so nullability survives the change; read
     * from `information_schema`, not from memory.
     */
    private const COLUMNS = [
        ['cash_accounts', 'interest_rate',
            "decimal(8,4) NULL DEFAULT NULL COMMENT 'Annual interest rate as a percentage (4.5 = 4.5%)'",
            "decimal(5,4) NULL DEFAULT NULL COMMENT 'Annual interest rate as decimal'"],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $widened]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$widened}");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column, , $original]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$original}");
        }
    }
};
