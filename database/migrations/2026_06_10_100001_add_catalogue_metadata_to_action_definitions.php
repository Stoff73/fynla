<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'tax_action_definitions',
        'retirement_action_definitions',
        'protection_action_definitions',
        'investment_action_definitions',
        'savings_action_definitions',
        'estate_action_definitions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'claim_tier')) {
                    // mechanical = stated directly with figures; judgement = hedged + signposted.
                    $t->string('claim_tier', 20)->default('judgement');
                }
                if (! Schema::hasColumn($table, 'required_data')) {
                    $t->json('required_data')->nullable();
                }
                if (! Schema::hasColumn($table, 'sequencing')) {
                    $t->json('sequencing')->nullable();
                }
            });
        }

        Schema::table('tax_action_definitions', function (Blueprint $t) {
            if (! Schema::hasColumn('tax_action_definitions', 'strategy_type')) {
                // Links a definition row to a Tax/Strategies class output type
                // (StrategyRecommendation::type). Null for legacy 'agent' rows.
                $t->string('strategy_type', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $cols = array_values(array_filter(
                    ['claim_tier', 'required_data', 'sequencing'],
                    fn (string $c): bool => Schema::hasColumn($table, $c)
                ));
                if ($cols !== []) {
                    $t->dropColumn($cols);
                }
            });
        }

        Schema::table('tax_action_definitions', function (Blueprint $t) {
            if (Schema::hasColumn('tax_action_definitions', 'strategy_type')) {
                $t->dropColumn('strategy_type');
            }
        });
    }
};
