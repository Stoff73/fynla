<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How-to steps per action definition, shown on the action's detail card only
 * once CSJ has approved them (ruling 2026-09-25). Additive and nullable: no
 * existing row changes meaning.
 */
return new class extends Migration
{
    private const TABLES = [
        'tax_action_definitions',
        'retirement_action_definitions',
        'investment_action_definitions',
        'protection_action_definitions',
        'savings_action_definitions',
        'estate_action_definitions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table): void {
                if (! Schema::hasColumn($table, 'how_to_steps')) {
                    $t->json('how_to_steps')->nullable();
                }
                if (! Schema::hasColumn($table, 'how_to_status')) {
                    $t->enum('how_to_status', ['draft', 'approved'])->default('draft');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table): void {
                foreach (['how_to_steps', 'how_to_status'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $t->dropColumn($column);
                    }
                }
            });
        }
    }
};
