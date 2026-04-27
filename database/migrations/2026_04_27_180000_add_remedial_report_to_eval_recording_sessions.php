<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('eval_recording_sessions', 'remedial_report')) {
            return;
        }

        Schema::table('eval_recording_sessions', function (Blueprint $t): void {
            $t->longText('remedial_report')->nullable()->after('completed_at');
            $t->timestamp('remedial_report_updated_at')->nullable()->after('remedial_report');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('eval_recording_sessions', 'remedial_report')) {
            return;
        }

        Schema::table('eval_recording_sessions', function (Blueprint $t): void {
            $t->dropColumn(['remedial_report', 'remedial_report_updated_at']);
        });
    }
};
