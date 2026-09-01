<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0152 — the section 13(11) election Fynla never offered.
 *
 * Mental Capacity Act 2005 s13(6)(c): the dissolution or annulment of a marriage
 * or civil partnership between the donor and an attorney TERMINATES that
 * attorney's appointment. s13(11) lets the instrument provide otherwise.
 *
 * So a donor who appoints their spouse and wants that appointment to survive a
 * divorce has to say so expressly, and Fynla gave them nowhere to say it.
 *
 * Nullable, and it must stay nullable — the same reasoning as W-0105's
 * `is_bankrupt` and the same reasoning W-0100 established for
 * `when_attorneys_can_act`: an unanswered election must not become an answer.
 * A NOT NULL DEFAULT here would write a legally operative provision into the
 * instrument on the donor's behalf, which is exactly the defect W-0100 fixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lasting_powers_of_attorney', function (Blueprint $table): void {
            $table->boolean('appointment_survives_dissolution')
                ->nullable()
                ->after('when_attorneys_can_act')
                ->comment('MCA 2005 s13(11) election against the s13(6)(c) default. NULL means the donor has not been asked, which is not the same as declining.');
        });
    }

    public function down(): void
    {
        Schema::table('lasting_powers_of_attorney', function (Blueprint $table): void {
            $table->dropColumn('appointment_survives_dissolution');
        });
    }
};
