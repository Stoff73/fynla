<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_entitlements', function (Blueprint $table): void {
            $table->dateTime('last_verified_at', 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('premium_entitlements', function (Blueprint $table): void {
            $table->dateTime('last_verified_at')->change();
        });
    }
};
