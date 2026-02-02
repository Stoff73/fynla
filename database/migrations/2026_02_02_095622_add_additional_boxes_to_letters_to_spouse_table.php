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
        Schema::table('letters_to_spouse', function (Blueprint $table) {
            $table->json('additional_boxes')->nullable()->after('additional_wishes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('letters_to_spouse', function (Blueprint $table) {
            $table->dropColumn('additional_boxes');
        });
    }
};
