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
        Schema::table('family_members', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_user_id')->nullable()->after('household_id');
            $table->foreign('linked_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('linked_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropForeign(['linked_user_id']);
            $table->dropIndex(['linked_user_id']);
            $table->dropColumn('linked_user_id');
        });
    }
};
