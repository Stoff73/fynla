<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Record the `spouse_pension_percent` convention on the column itself, and repair
 * any row written under the opposite one (W-0030).
 *
 * The field had two incompatible conventions. Five sites treated it as PERCENTAGE
 * POINTS — `HouseholdPlanningService` and `PensionDerivedColumnCalculator` both
 * divide by 100, `StoreDBPensionRequest` and `PensionStore` both validate
 * `max:100`, and `DBPensionFactory` seeds 50.0 / 66.67 / 100.0. Two treated it as
 * a decimal fraction: `DBPensionMapper::parseSpousePercent` (now corrected) and
 * the extraction prompt that fed it (also corrected). So a Defined Benefit pension
 * imported from an uploaded document stored 0.50 where a form-entered one stored
 * 50, and every spouse projection for the imported one ran at a hundredth of the
 * real figure.
 *
 * Identifying the affected rows: a value strictly between 0 and 1 is a fraction.
 * No real scheme pays a spouse less than 1% of the member's pension, whereas 0.5
 * meaning "a half" is exactly what the decimal convention produced. The one case
 * this cannot separate is a fraction that truncated into a plausible points value
 * — `decimal(5,2)` stores 0.6667 as 0.67 — but 0.67 as points is not a real spouse
 * pension either, so it is corrected on the same reasoning.
 *
 * `spouse_pension_projected_gbp` is recalculated for every corrected row. Without
 * that the derived cache keeps serving the hundredth-scale figure and the fix
 * looks complete while the wrong number is still what renders.
 *
 * Every correction is logged: on an environment where affected rows exist, the
 * deploy log is the record of exactly which pensions changed and by how much.
 */
return new class extends Migration
{
    private const COLUMN_COMMENT = 'Percentage points, not a fraction: 50 means 50%. Consumers divide by 100.';

    public function up(): void
    {
        // MySQL will not take a bound parameter in DDL, so the comment is
        // inlined. It is a class constant with no quotes in it, never user input.
        $comment = str_replace("'", "''", self::COLUMN_COMMENT);

        DB::statement(
            "ALTER TABLE `db_pensions` MODIFY `spouse_pension_percent` DECIMAL(5,2) NULL COMMENT '{$comment}'"
        );

        $affected = DB::table('db_pensions')
            ->whereNotNull('spouse_pension_percent')
            ->where('spouse_pension_percent', '>', 0)
            ->where('spouse_pension_percent', '<', 1)
            ->get(['id', 'spouse_pension_percent', 'accrued_annual_pension']);

        if ($affected->isEmpty()) {
            Log::info('W-0030: no db_pensions rows used the decimal spouse-pension convention.');

            return;
        }

        foreach ($affected as $pension) {
            $corrected = round((float) $pension->spouse_pension_percent * 100, 2);
            $corrected = min(100.0, $corrected);

            $projected = $pension->accrued_annual_pension !== null
                ? round((float) $pension->accrued_annual_pension * $corrected / 100, 2)
                : null;

            DB::table('db_pensions')->where('id', $pension->id)->update([
                'spouse_pension_percent' => $corrected,
                'spouse_pension_projected_gbp' => $projected,
                'spouse_pension_projected_gbp_calculated_at' => now(),
            ]);

            Log::warning('W-0030: corrected decimal-convention spouse pension percentage.', [
                'db_pension_id' => $pension->id,
                'was' => (float) $pension->spouse_pension_percent,
                'now' => $corrected,
                'spouse_pension_projected_gbp' => $projected,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `db_pensions` MODIFY `spouse_pension_percent` DECIMAL(5,2) NULL');

        // The data correction is deliberately not reversed. A row this migration
        // fixed is indistinguishable from one always stored correctly, so undoing
        // it would re-break pensions that were never broken.
    }
};
