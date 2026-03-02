<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommendation_tracking', function (Blueprint $table) {
            if (! Schema::hasColumn('recommendation_tracking', 'recommended_amount')) {
                $table->decimal('recommended_amount', 15, 2)->default(0)->after('priority_score');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recommendation_tracking', function (Blueprint $table) {
            if (Schema::hasColumn('recommendation_tracking', 'recommended_amount')) {
                $table->dropColumn('recommended_amount');
            }
        });
    }
};
