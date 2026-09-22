<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax-Free Childcare pays a higher top-up for a disabled child (the seeded
 * `max_disabled_contribution`, to `disabled_child_age_limit`), but nothing on
 * the family member could say a child is disabled, so the childcare line could
 * never apply either figure (CSJ, 2026-09-22). Nullable: NULL means not asked,
 * never "no".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->boolean('is_disabled')->nullable()->after('receives_child_benefit');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn('is_disabled');
        });
    }
};
