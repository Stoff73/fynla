<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->unsignedTinyInteger('hash_scheme')->default(1)->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->dropColumn('hash_scheme');
        });
    }
};
