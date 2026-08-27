<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0042 — a shared asset must be able to NAME its counterparty on every table.
 *
 * W-0025's house rule is that a shared asset names either a linked
 * `joint_owner_id` or an off-platform `joint_owner_name`. Three tables could
 * express the second half; four could not, so a joint savings account co-owned
 * with someone who is not on the platform was anonymous — the user could say the
 * account was shared and never say with whom.
 *
 * CSJ direction 2026-08-26: all four, not just the two W-0042 names.
 * `business_interests` and `liabilities` carry no shared rows today, so this is
 * cheap now and closes the schema inconsistency rather than leaving two thirds of
 * it for someone to rediscover.
 *
 * Nullable, and deliberately so: this migration adds a CAPABILITY. Requiring the
 * name is a separate decision, because `SavingsStore:357-361` documents joint with
 * no linked owner as first-class and `CoordinatingAgentJointOwnerTest` exercises
 * that state through Fyn. Enforcing here would delete a working capability under
 * cover of adding one.
 */
return new class extends Migration
{
    private const TABLES = [
        'savings_accounts',
        'investment_accounts',
        'business_interests',
        'liabilities',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'joint_owner_name')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->string('joint_owner_name')->nullable()->after('joint_owner_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'joint_owner_name')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('joint_owner_name');
            });
        }
    }
};
