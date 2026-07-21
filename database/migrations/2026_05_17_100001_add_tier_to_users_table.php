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
            // Nullable: null means "resolve via TierResolver". No mechanical
            // plan->tier backfill (spec §5.2) — paid legacy subscribers stay
            // null and are grandfathered by the resolver until renewal.
            $table->enum('tier', ['free', 'tier1', 'tier2', 'tier3'])
                ->nullable()->after('plan')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
