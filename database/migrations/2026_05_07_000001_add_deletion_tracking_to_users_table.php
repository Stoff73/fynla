<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'deletion_scheduled_for')) {
                $table->timestamp('deletion_scheduled_for')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('users', 'deletion_reason')) {
                $table->enum('deletion_reason', [
                    'user_requested',
                    'trial_expired',
                    'subscription_cancelled_grace_ended',
                    'admin_initiated',
                    'legacy_purged',
                ])->nullable()->after('deletion_scheduled_for');
            }
            if (! Schema::hasColumn('users', 'deletion_source')) {
                $table->string('deletion_source', 50)->nullable()->after('deletion_reason');
            }
            if (! Schema::hasColumn('users', 'restored_at')) {
                $table->timestamp('restored_at')->nullable()->after('deletion_source');
            }
            if (! Schema::hasColumn('users', 'purge_eligible_at')) {
                $table->timestamp('purge_eligible_at')->nullable()->after('restored_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('deletion_scheduled_for');
            $table->index('purge_eligible_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_scheduled_for']);
            $table->dropIndex(['purge_eligible_at']);
            $table->dropColumn([
                'deletion_scheduled_for',
                'deletion_reason',
                'deletion_source',
                'restored_at',
                'purge_eligible_at',
            ]);
        });
    }
};
