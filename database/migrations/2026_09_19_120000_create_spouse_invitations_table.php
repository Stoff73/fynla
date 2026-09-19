<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invitation to a spouse who has no Fynla account yet, remembered.
 *
 * Until now the invitation was sent and forgotten: no row anywhere tied the
 * invited address to the inviter, so the invitee registered as a stranger,
 * met the ground-zero front door, and none of the inviter's household facts
 * reached them (Azlan and Laura, 2026-09-18). The email link now carries a
 * token; registering from it links the two accounts and hands over what the
 * inviter already told us (CSJ 2026-09-19).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spouse_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inviter_id', 'email']);
        });

        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('spouse_invitation_token', 64)->nullable()->after('funnel_answers');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('spouse_invitation_token');
        });
        Schema::dropIfExists('spouse_invitations');
    }
};
