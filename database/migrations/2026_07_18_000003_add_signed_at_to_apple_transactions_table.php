<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apple_transactions', function (Blueprint $table): void {
            $table->dateTime('signed_at', 3)
                ->nullable()
                ->after('signed_payload_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('apple_transactions', function (Blueprint $table): void {
            $table->dropColumn('signed_at');
        });
    }
};
