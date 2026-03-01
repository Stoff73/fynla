<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add expires_at column to pending_registrations table.
     *
     * Pending registrations now expire after 24 hours to prevent
     * database bloat and limit exposure of abandoned registration codes.
     */
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('billing_cycle');
            $table->index('expires_at');
        });

        // Set expiry for existing records (24 hours from their creation)
        DB::table('pending_registrations')
            ->whereNull('expires_at')
            ->update(['expires_at' => DB::raw('DATE_ADD(created_at, INTERVAL 24 HOUR)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
