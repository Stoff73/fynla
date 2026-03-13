<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fix column type: joint_owner_id must be unsigned to match users.id
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('joint_owner_id')->nullable()->change();
        });

        // Now add the FK constraint
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->foreign('joint_owner_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropForeign(['joint_owner_id']);
        });

        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->bigInteger('joint_owner_id')->nullable()->change();
        });
    }
};
