<?php

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
        Schema::table('business_interests', function (Blueprint $table) {
            // Fix column type to match users.id (bigint unsigned)
            $table->unsignedBigInteger('joint_owner_id')->nullable()->change();

            // Add FK with SET NULL on delete (joint owner removed, asset stays)
            $table->foreign('joint_owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::table('chattels', function (Blueprint $table) {
            $table->unsignedBigInteger('joint_owner_id')->nullable()->change();

            $table->foreign('joint_owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_interests', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
        });

        Schema::table('chattels', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
        });
    }
};
