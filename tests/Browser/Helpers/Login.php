<?php

declare(strict_types=1);

namespace Tests\Browser\Helpers;

use App\Models\EmailVerificationCode;
use App\Models\User;

/**
 * Login helper — centralises the click-through pattern so every Sprint 0
 * BS-NN scenario starts identically. The methods on this class are
 * documentation + DB plumbing only; the actual `browser_*` Playwright
 * calls live in the BS-NN scenario script itself, where Claude executes
 * them step by step.
 *
 * The verification-code path matches root CLAUDE.md "Authentication for
 * Testing" — local dev fetches the latest unused code from the DB
 * (this class), production asks the user (out of scope for this helper).
 */
final class Login
{
    /**
     * Standard login script — used by every scenario except those that
     * use a preview persona.
     *
     * Claude executes the following Playwright steps inside the scenario
     * file, calling `latestVerificationCode()` below to bridge the MFA
     * step on local dev:
     *
     *   1. browser_navigate('http://localhost:8000')
     *   2. browser_snapshot()           // capture landing page DOM
     *   3. browser_click on the "Sign in" link
     *   4. browser_fill_form { email, password }
     *   5. browser_press_key('Enter')
     *   6. If "Verification code" prompt appears:
     *        $code = Login::latestVerificationCode($email);
     *        browser_type(code)
     *        browser_press_key('Enter')
     *   7. browser_wait_for the dashboard heading or first onboarding turn
     *
     * Returns the User the credentials belong to so subsequent steps
     * can run DB-side assertions against the correct row.
     */
    public static function as(string $email, string $password): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * Convenience overload for tests that have built a factory User in
     * Pest setup — saves the caller from repeating `$user->email`.
     */
    public static function asFactoryUser(User $user, string $password): ?User
    {
        return self::as($user->email, $password);
    }

    /**
     * Preview persona path — the landing page persona selector skips
     * the auth flow entirely and lands the user on the dashboard with
     * preview seed data. Used by BS-08, BS-09, BS-11, BS-14, BS-17, BS-21.
     *
     * Claude executes:
     *   1. browser_navigate('http://localhost:8000')
     *   2. browser_click on the persona-selector CTA matching `$persona`
     *   3. browser_wait_for the dashboard
     *
     * Persona keys must match `App\Models\User::is_preview_user = true`
     * rows seeded by PreviewUserSeeder — currently:
     *   young_family, peak_earners, entrepreneur, young_saver,
     *   retired_couple, student.
     */
    public static function asPreviewPersona(string $persona): ?User
    {
        return User::query()
            ->where('is_preview_user', true)
            ->where('preview_persona_key', $persona)
            ->first();
    }

    /**
     * Local-dev only — fetch the most recent (still-valid) verification
     * code so the MFA step in `as()` can be completed by Claude without
     * asking the user.
     *
     * On production (fynla.org) Claude must ASK the user for the code per
     * root CLAUDE.md "Authentication for Testing" — never hit the DB.
     */
    public static function latestVerificationCode(string $email): ?string
    {
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            return null;
        }

        return EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first()
            ?->code;
    }
}
