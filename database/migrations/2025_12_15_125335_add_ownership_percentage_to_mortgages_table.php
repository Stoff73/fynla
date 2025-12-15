<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            if (! Schema::hasColumn('mortgages', 'ownership_percentage')) {
                $table->decimal('ownership_percentage', 5, 2)
                    ->default(100)
                    ->after('ownership_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropColumn('ownership_percentage');
        });
    }
};
