<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eval_recording_sessions', function (Blueprint $table): void {
            $table->string('persona', 64)->nullable()->after('eval_user_id')->index();
            $table->json('http_log')->nullable()->after('start_state_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('eval_recording_sessions', function (Blueprint $table): void {
            $table->dropIndex(['persona']);
            $table->dropColumn(['persona', 'http_log']);
        });
    }
};
