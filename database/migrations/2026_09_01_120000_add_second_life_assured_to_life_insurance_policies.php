<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0200 — name the second life assured instead of inferring it.
 *
 * `life_insurance_policies` carried `joint_life` and nothing else: "this covers
 * two lives", with no field for whose. The only available answer was
 * `users.spouse_id`, so a key-person policy over a business partner, an unmarried
 * couple's policy, or a parent-and-adult-child policy was silently attributed to
 * the spouse — or to nobody.
 *
 * The pair mirrors the counterparty rule every other shared record already uses
 * (`joint_owner_id` / `joint_owner_name` on business interests and investment
 * accounts): the id when the other life holds an account, the free-text name when
 * they do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->foreignId('joint_life_with_user_id')
                ->nullable()
                ->after('joint_life')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('joint_life_with_name')->nullable()->after('joint_life_with_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('life_insurance_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('joint_life_with_user_id');
            $table->dropColumn('joint_life_with_name');
        });
    }
};
