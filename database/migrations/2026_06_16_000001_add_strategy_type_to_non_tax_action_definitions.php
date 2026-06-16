<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'retirement_action_definitions',
        'savings_action_definitions',
        'investment_action_definitions',
        'protection_action_definitions',
        'estate_action_definitions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('strategy_type', 64)->nullable()->unique()->after('source');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->dropUnique($table.'_strategy_type_unique');
                $t->dropColumn('strategy_type');
            });
        }
    }
};
