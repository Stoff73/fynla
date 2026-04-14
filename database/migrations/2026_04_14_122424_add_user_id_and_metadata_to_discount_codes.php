<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('discount_codes', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('users')
                    ->cascadeOnDelete();
            }
            if (! Schema::hasColumn('discount_codes', 'metadata')) {
                $table->json('metadata')->nullable()->after('applicable_cycles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            if (Schema::hasColumn('discount_codes', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('discount_codes', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
