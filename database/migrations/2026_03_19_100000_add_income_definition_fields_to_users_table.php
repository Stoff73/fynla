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
            if (! Schema::hasColumn('users', 'is_registered_blind')) {
                $table->boolean('is_registered_blind')->default(false)->after('annual_trust_income');
            }
            if (! Schema::hasColumn('users', 'annual_charitable_donations')) {
                $table->decimal('annual_charitable_donations', 15, 2)->nullable()->after('is_registered_blind');
            }
            if (! Schema::hasColumn('users', 'is_gift_aid')) {
                $table->boolean('is_gift_aid')->default(false)->after('annual_charitable_donations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_registered_blind', 'annual_charitable_donations', 'is_gift_aid']);
        });
    }
};
