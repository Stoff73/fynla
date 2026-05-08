<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereNull('deletion_reason')
            ->update([
                'deletion_reason' => 'legacy_purged',
                'deletion_source' => 'auto_expiration_grace',
                'purge_eligible_at' => DB::raw('deleted_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('deletion_reason', 'legacy_purged')
            ->update([
                'deletion_reason' => null,
                'deletion_source' => null,
                'purge_eligible_at' => null,
            ]);
    }
};
