<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Fyn chat consent gate had exactly one grant path, so accounts created any
 * other way were locked out of the product permanently.
 *
 * `AuthController::verifyCode` has recorded `ai_chat` consent at registration
 * since 2026-05-05 (0335ffd31). Every account created before that date, and
 * every account created since by a path other than self-registration — the two
 * spouse-account creators, admin creation — has no `user_consents` row at all.
 * `AiChatController` then answers every Fyn request from that user with 403
 * `consent_required` on web, `/m` and native alike, and no surface offers a way
 * to grant it (there is no consent toggle, by design). A TestFlight tester
 * (csjones user 49, registered 2026-05-01) hit exactly this: "Fyn chat consent
 * is required before you can continue", with nothing to tap.
 *
 * This grants the missing consent on the same basis the registration path
 * states: the whole post-registration journey is chat-driven, so consent is
 * given at account creation, and withdrawal continues to flow through
 * `PUT /api/user/consents`. Accounts that already hold a row are untouched —
 * including a withdrawn one, which must stay withdrawn.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO user_consents
                (user_id, consent_type, version, consented, consented_at, created_at, updated_at)
            SELECT u.id, 'ai_chat', 'v1.0', 1, NOW(), NOW(), NOW()
            FROM users u
            LEFT JOIN user_consents c
                ON c.user_id = u.id AND c.consent_type = 'ai_chat'
            WHERE c.id IS NULL AND u.deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        // Deliberately irreversible. Nothing distinguishes a row written here
        // from one written at registration, and deleting the wrong one locks a
        // real user out of Fyn.
    }
};
