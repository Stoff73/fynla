<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one home for cookie-banner consent — the single accept/decline covering
 * analytics and affiliate tracking (W-0049).
 *
 * One click, one endpoint, one moment — and TWO records, because the click
 * covers two materially different activities. See {@see record()}.
 *
 * Before this class, consent lived only as a `localStorage` string in the
 * visitor's browser: nothing on the server could read it, so the affiliate
 * middleware tracked every visitor regardless, and no consent could be
 * evidenced for any user. Consent now lives in two places with one owner:
 *
 *   - `user_consents` is the RECORD. Versioned, timestamped, with withdrawal,
 *     keyed to a user_id once one exists and to an anonymous subject_token
 *     before that. This is what demonstrates consent.
 *   - The `fyn_cookie_consent` cookie is the TRANSPORT. It is what the global
 *     middleware stack reads on every request, where no user is resolved and a
 *     database round-trip per request would not be acceptable.
 *
 * They cannot drift because this class is the only thing that writes either,
 * and it always writes both. Every surface (web SPA, the server-rendered
 * public pages, /m) reaches it through one endpoint: POST /api/cookie-consent.
 *
 * Deliberately NOT a source of truth for the authenticated privacy screen's
 * other consent types — those keep flowing through ConsentService.
 */
class CookieConsentService
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    /** Readable by page scripts: the banner decides whether to show from it. */
    public const STATUS_COOKIE = 'fyn_cookie_consent';

    /** HttpOnly: identifies the anonymous subject of a consent record. */
    public const SUBJECT_COOKIE = 'fyn_consent_ref';

    private const LIFETIME_DAYS = 365;

    private const SUBJECT_PATTERN = '/^[a-f0-9]{64}$/';

    /**
     * The visitor's recorded decision, or null if they have not been asked.
     */
    public function statusFor(Request $request): ?string
    {
        return $this->normaliseStatus($request->cookie(self::STATUS_COOKIE));
    }

    /**
     * Whether analytics and affiliate tracking are permitted for this request.
     *
     * Absence of a decision is NOT consent: unasked visitors are not tracked.
     */
    public function allowsTracking(Request $request): bool
    {
        return $this->statusFor($request) === self::STATUS_ACCEPTED;
    }

    /**
     * The decision in force by the end of this request.
     *
     * A response that sets the status cookie — the consent endpoint itself —
     * supersedes what the request arrived with, so middleware acting after the
     * controller sees the new decision rather than the one being replaced.
     */
    public function resolvedStatus(Request $request, Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::STATUS_COOKIE) {
                return $this->normaliseStatus($cookie->getValue());
            }
        }

        return $this->statusFor($request);
    }

    /**
     * The subject token for this visitor: theirs if they already carry a
     * well-formed one, otherwise a fresh one.
     *
     * The token is opaque and unguessable. A visitor presenting a token of
     * their own choosing can only write consent for a subject they control,
     * which is what they were doing anyway.
     */
    public function subjectTokenFor(Request $request): string
    {
        $token = $request->cookie(self::SUBJECT_COOKIE);

        if (is_string($token) && preg_match(self::SUBJECT_PATTERN, $token) === 1) {
            return $token;
        }

        return bin2hex(random_bytes(32));
    }

    /**
     * Record the decision. Against the user when there is one, against the
     * anonymous subject otherwise.
     *
     * The banner is one click covering two materially different activities —
     * measurement and affiliate attribution — so it writes both types, here, in
     * one call, at one moment. A single `cookies` row answered a question that
     * does not survive the two being separated: it could not say which of them
     * the visitor had agreed to, and that is not something a later migration
     * can reconstruct without inventing it.
     *
     * This is one write path recording one user action against two subjects of
     * consent. It is not a second mechanism, and it must not become one: the
     * button, the endpoint and the moment stay single.
     *
     * @return array<string, UserConsent> keyed by consent type
     */
    public function record(Request $request, bool $consented, string $subjectToken): array
    {
        $user = $request->user('sanctum') ?? $request->user();
        $recorded = [];

        foreach (UserConsent::COOKIE_BANNER_TYPES as $type) {
            $recorded[$type] = $user instanceof User
                ? UserConsent::recordConsent($user->id, $type, $consented)
                : UserConsent::recordAnonymousConsent($subjectToken, $type, $consented);
        }

        return $recorded;
    }

    /**
     * Put the decision and the subject token on the response.
     *
     * Host-only (no domain) on purpose. The banner scripts write the status
     * cookie themselves the instant the visitor answers, so that a slow or
     * failed request can never leave them stuck behind the banner, and a
     * browser-set cookie is always host-only. A domain-scoped cookie of the
     * same name would sit alongside that one rather than replace it, and which
     * of the two a later request sends is not defined.
     */
    public function applyTo(Request $request, Response $response, bool $consented, string $subjectToken): void
    {
        $expiry = time() + (86400 * self::LIFETIME_DAYS);
        $secure = $request->isSecure();

        $response->headers->setCookie(Cookie::create(
            name: self::STATUS_COOKIE,
            value: $consented ? self::STATUS_ACCEPTED : self::STATUS_DECLINED,
            expire: $expiry,
            path: '/',
            domain: null,
            secure: $secure,
            httpOnly: false,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        $response->headers->setCookie(Cookie::create(
            name: self::SUBJECT_COOKIE,
            value: $subjectToken,
            expire: $expiry,
            path: '/',
            domain: null,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        ));
    }

    /**
     * Claim any consent this visitor gave before they had an account.
     */
    public function claimFor(Request $request, User $user): void
    {
        $token = $request->cookie(self::SUBJECT_COOKIE);

        if (! is_string($token) || preg_match(self::SUBJECT_PATTERN, $token) !== 1) {
            return;
        }

        UserConsent::claimAnonymousConsents($token, $user->id);
    }

    private function normaliseStatus(mixed $status): ?string
    {
        return in_array($status, [self::STATUS_ACCEPTED, self::STATUS_DECLINED], true)
            ? $status
            : null;
    }
}
