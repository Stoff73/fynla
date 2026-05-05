<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wills', function (Blueprint $table) {
            $table->string('residuary_beneficiary')
                ->nullable()
                ->after('spouse_bequest_percentage')
                ->comment('Free-text residuary beneficiary name for AI-captured wills.');

            $table->string('guardian_for_minors')
                ->nullable()
                ->after('residuary_beneficiary')
                ->comment('Named guardian for any minor dependants.');

            $table->text('specific_gifts')
                ->nullable()
                ->after('guardian_for_minors')
                ->comment('Free-text list of specific gifts (item, recipient). Distinct from will_documents.specific_gifts which is a separate model.');
        });
    }

    public function down(): void
    {
        Schema::table('wills', function (Blueprint $table) {
            $table->dropColumn(['residuary_beneficiary', 'guardian_for_minors', 'specific_gifts']);
        });
    }
};
