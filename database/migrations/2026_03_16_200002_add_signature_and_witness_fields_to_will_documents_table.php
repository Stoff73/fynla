<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('will_documents', 'signed_date')) {
            return;
        }

        Schema::table('will_documents', function (Blueprint $table) {
            $table->date('signed_date')->nullable()->after('domicile_confirmed');
            $table->json('witnesses')->nullable()->after('signed_date');
        });
    }

    public function down(): void
    {
        Schema::table('will_documents', function (Blueprint $table) {
            $table->dropColumn(['signed_date', 'witnesses']);
        });
    }
};
