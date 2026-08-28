<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W-0162 — the column offered four ownership types and the application could
 * only ever write two.
 *
 * `tenants_in_common` was added to this enum on 2026-01-17 by a migration named
 * for the purpose, so the schema reads as though mortgages support it. They do
 * not, and `trust` is in the same position: both are coerced away before any row
 * is written, at three independent layers.
 *
 * That contradiction is a reader trap rather than a defect — no row has ever held
 * either value — and it has already misled once. It nearly caused the coercion to
 * be removed as stale decoration while W-0172 was being fixed, on the strength of
 * the migration name alone. The comment beside the code was wrong about the column
 * and right about the application; the schema was right about the column and silent
 * about the application.
 *
 * So the constraint is recorded where a reader checking the column actually looks:
 * on the column. The enum itself is deliberately NOT narrowed — the January
 * migration's own `down()` keeps `trust`, so reverting it would not resolve the
 * mismatch it exists to resolve, and an ALTER on a live table buys nothing when
 * the value has never been stored.
 */
return new class extends Migration
{
    private const COMMENT = 'W-0162: only individual|joint are writable. tenants_in_common and trust are '
        .'coerced away before any write, at Store/UpdateMortgageRequest, MortgageNormaliser and '
        .'MortgageStore::validateCanonical. A mortgage share follows the property securing it '
        .'(W-0228), so ownership is expressed on the property, never here.';

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE mortgages MODIFY COLUMN ownership_type '
            ."ENUM('individual', 'joint', 'tenants_in_common', 'trust') NOT NULL DEFAULT 'individual' "
            .'COMMENT '.DB::getPdo()->quote(self::COMMENT)
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE mortgages MODIFY COLUMN ownership_type '
            ."ENUM('individual', 'joint', 'tenants_in_common', 'trust') NOT NULL DEFAULT 'individual' "
            ."COMMENT ''"
        );
    }
};
