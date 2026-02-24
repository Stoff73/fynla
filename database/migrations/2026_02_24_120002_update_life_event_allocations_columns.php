<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_event_allocations', function (Blueprint $table) {
            $table->string('account_label', 255)->nullable()->change();
            $table->unsignedSmallInteger('display_order')->default(0)->change();
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('life_event_allocations', function (Blueprint $table) {
            $table->string('account_label', 100)->nullable()->change();
            $table->tinyInteger('display_order')->default(0)->change();
            $table->softDeletes();
        });
    }
};
