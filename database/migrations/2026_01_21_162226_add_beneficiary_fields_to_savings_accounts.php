<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add beneficiary fields for Junior ISA accounts.
     * beneficiary_id references a family_member (child/dependent)
     * beneficiary_name is used when "Other" is selected or for display
     */
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('beneficiary_id')->nullable()->after('joint_owner_id');
            $table->string('beneficiary_name')->nullable()->after('beneficiary_id');

            $table->foreign('beneficiary_id')
                ->references('id')
                ->on('family_members')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_id']);
            $table->dropColumn(['beneficiary_id', 'beneficiary_name']);
        });
    }
};
