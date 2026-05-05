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
            if (! Schema::hasColumn('users', 'is_lifecycle_test_user')) {
                $table->boolean('is_lifecycle_test_user')->default(false);
                $table->index('is_lifecycle_test_user');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_lifecycle_test_user')) {
                $table->dropIndex(['is_lifecycle_test_user']);
                $table->dropColumn('is_lifecycle_test_user');
            }
        });
    }
};
