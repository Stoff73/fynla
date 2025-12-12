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
        Schema::table('mortgages', function (Blueprint $table) {
            if (! Schema::hasColumn('mortgages', 'ownership_type')) {
                $table->enum('ownership_type', ['individual', 'joint', 'trust'])
                    ->default('individual')
                    ->after('remaining_term_months');
            }

            if (! Schema::hasColumn('mortgages', 'joint_owner_id')) {
                $table->unsignedBigInteger('joint_owner_id')
                    ->nullable()
                    ->after('ownership_type');

                // Add foreign key constraint
                $table->foreign('joint_owner_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }

            if (! Schema::hasColumn('mortgages', 'joint_owner_name')) {
                $table->string('joint_owner_name', 255)
                    ->nullable()
                    ->after('joint_owner_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
            $table->dropColumn(['ownership_type', 'joint_owner_id', 'joint_owner_name']);
        });
    }
};
