<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wills', function (Blueprint $table) {
            if (! Schema::hasColumn('wills', 'last_reviewed_date')) {
                $table->date('last_reviewed_date')->nullable()->after('will_last_updated');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wills', function (Blueprint $table) {
            if (Schema::hasColumn('wills', 'last_reviewed_date')) {
                $table->dropColumn('last_reviewed_date');
            }
        });
    }
};
