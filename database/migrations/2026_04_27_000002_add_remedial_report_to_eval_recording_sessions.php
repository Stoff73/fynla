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

        Schema::table('eval_recording_sessions', function (Blueprint $table) {
            // Free-text remedial report authored against the rubric in
            // April/April27Updates/eval-remediation-process.md. One report
            // per session; both providers' findings live inside it.
            $table->text('remedial_report')->nullable()->after('start_state_snapshot');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('eval_recording_sessions', 'remedial_report')) {
            return;
        }

        Schema::table('eval_recording_sessions', function (Blueprint $table) {
            $table->dropColumn('remedial_report');
        });
    }
};
