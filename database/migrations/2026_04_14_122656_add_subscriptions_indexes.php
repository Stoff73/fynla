<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM subscriptions'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('subscriptions', function (Blueprint $table) use ($existing) {
            if (! in_array('idx_subs_status_trial', $existing, true)) {
                $table->index(['status', 'trial_ends_at'], 'idx_subs_status_trial');
            }
            if (! in_array('idx_subs_status_period', $existing, true)) {
                $table->index(['status', 'current_period_end'], 'idx_subs_status_period');
            }
            if (! in_array('idx_subs_status_cancelled', $existing, true)) {
                $table->index(['status', 'cancelled_at'], 'idx_subs_status_cancelled');
            }
        });
    }

    public function down(): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM subscriptions'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('subscriptions', function (Blueprint $table) use ($existing) {
            if (in_array('idx_subs_status_trial', $existing, true)) {
                $table->dropIndex('idx_subs_status_trial');
            }
            if (in_array('idx_subs_status_period', $existing, true)) {
                $table->dropIndex('idx_subs_status_period');
            }
            if (in_array('idx_subs_status_cancelled', $existing, true)) {
                $table->dropIndex('idx_subs_status_cancelled');
            }
        });
    }
};
