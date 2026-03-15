<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('life_insurance_policies', 'joint_life')) {
            return;
        }

        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->boolean('joint_life')->default(false)->after('in_trust');
        });
    }

    public function down(): void
    {
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->dropColumn('joint_life');
        });
    }
};
