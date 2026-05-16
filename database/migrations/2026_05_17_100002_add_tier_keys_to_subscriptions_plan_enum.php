<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SP2 PR5 — Add tier keys (free, tier1, tier2, tier3) to subscriptions.plan enum.
 *
 * PaymentController now accepts tier keys from PricingPage (PR4) and stores
 * them on the subscription row. Without this migration the column rejects
 * INSERT/UPDATE with tier key values (MySQL enum strict).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE subscriptions MODIFY COLUMN plan ENUM(
                'student','standard','family','pro',
                'free','tier1','tier2','tier3'
            ) NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE subscriptions MODIFY COLUMN plan ENUM(
                'student','standard','family','pro'
            ) NOT NULL"
        );
    }
};
