<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0105 — the bankruptcy question a property and financial affairs LPA needs.
 *
 * Mental Capacity Act 2005 s13(8)-(9): a bankrupt person cannot act as attorney
 * for PROPERTY AND FINANCIAL AFFAIRS. The disqualification does not extend to
 * health and welfare, which is why this is a per-attorney fact judged against
 * the instrument's type rather than a blanket bar.
 *
 * Nullable, and it must stay nullable. "The donor has not been asked" is a
 * different fact from "the attorney is not bankrupt" — a NOT NULL DEFAULT false
 * would turn an unanswered question into a declaration nobody made, on an
 * instrument the Office of the Public Guardian can refuse to register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lpa_attorneys', function (Blueprint $table): void {
            $table->boolean('is_bankrupt')
                ->nullable()
                ->after('date_of_birth')
                ->comment('MCA 2005 s13(8) — bankrupt attorneys cannot act on a property and financial affairs LPA. NULL means not asked, which is not the same as false.');
        });
    }

    public function down(): void
    {
        Schema::table('lpa_attorneys', function (Blueprint $table): void {
            $table->dropColumn('is_bankrupt');
        });
    }
};
