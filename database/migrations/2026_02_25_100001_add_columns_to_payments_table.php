<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'description')) {
                $table->string('description')->nullable()->after('status');
            }
            if (! Schema::hasColumn('payments', 'plan_slug')) {
                $table->string('plan_slug')->nullable()->after('description');
            }
            if (! Schema::hasColumn('payments', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable()->after('plan_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['description', 'plan_slug', 'billing_cycle']);
        });
    }
};
