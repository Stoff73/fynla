<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'university')) {
                $table->string('university', 255)->nullable()->after('education_level');
            }
            if (! Schema::hasColumn('users', 'student_number')) {
                $table->string('student_number', 50)->nullable()->after('university');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['university', 'student_number']);
        });
    }
};
