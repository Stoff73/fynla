<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->json('result_json')->nullable()->after('retirement_age');
        });
    }

    public function down(): void
    {
        Schema::table('iht_calculations', function (Blueprint $table) {
            $table->dropColumn('result_json');
        });
    }
};
