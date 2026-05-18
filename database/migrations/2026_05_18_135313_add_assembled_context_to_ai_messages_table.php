<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->longText('assembled_context')->nullable()->after('system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropColumn('assembled_context');
        });
    }
};
