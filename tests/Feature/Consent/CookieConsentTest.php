<?php

declare(strict_types=1);

use App\Models\PendingRegistration;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Consent\CookieConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

/**
 * W-0049 acceptance 3: the decision is recorded server-side, versioned, with
 * history and withdrawal — including for a visitor who has no account, which
 * is most of them.
 */
beforeEach(function () {
    config()->set('awin.enabled', true);
    config()->set('awin.cookie_domain', null);
});

function consentCookieOn($response, string $name): ?Cookie
{
    foreach ($response->baseResponse->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $name) {
            return $cookie;
        }
    }

    return null;
}

it('records an anonymous acceptance against a server-issued subject token', function () {
    $response = $this->postJson('/api/cookie-consent', ['status' => 'accepted']);

    $response->assertOk()->assertJsonPath('data.status', 'accepted');

    // One click, two records: the banner covers measurement AND affiliate
    // attribution, and a single row could not say which was agreed to.
    $consents = UserConsent::whereIn('consent_type', UserConsent::COOKIE_BANNER_TYPES)->get();

    expect($consents)->toHaveCount(2);
    expect($consents->pluck('consent_type')->sort()->values()->all())
        ->toBe(['cookies_affiliate', 'cookies_analytics']);

    // Both are one act, so they share a subject and a moment.
    expect($consents->pluck('subject_token')->unique())->toHaveCount(1);

    foreach ($consents as $consent) {
        expect($consent->user_id)->toBeNull();
        expect($consent->subject_token)->toMatch('/^[a-f0-9]{64}$/');
        expect($consent->consented)->toBeTrue();
        expect($consent->consented_at)->not->toBeNull();
        expect($consent->withdrawn_at)->toBeNull();
        expect($consent->version)->toBe(UserConsent::CURRENT_VERSIONS[$consent->consent_type]);
    }
});

it('sets the status cookie the tracking middleware reads, and the subject cookie', function () {
    $response = $this->postJson('/api/cookie-consent', ['status' => 'accepted']);

    $status = consentCookieOn($response, CookieConsentService::STATUS_COOKIE);
    $subject = consentCookieOn($response, CookieConsentService::SUBJECT_COOKIE);

    expect($status?->getValue())->toBe('accepted');
    // Readable by the banner scripts on every surface.
    expect($status->isHttpOnly())->toBeFalse();

    expect($subject?->getValue())->toMatch('/^[a-f0-9]{64}$/');
    expect($subject->isHttpOnly())->toBeTrue();
});

it('records a refusal as a withdrawal against the same subject, not a new one', function () {
    $accept = $this->postJson('/api/cookie-consent', ['status' => 'accepted']);
    $token = consentCookieOn($accept, CookieConsentService::SUBJECT_COOKIE)->getValue();

    $this->withCredentials()
        ->withUnencryptedCookie(CookieConsentService::SUBJECT_COOKIE, $token)
        ->postJson('/api/cookie-consent', ['status' => 'declined'])
        ->assertOk();

    $consents = UserConsent::whereIn('consent_type', UserConsent::COOKIE_BANNER_TYPES)->get();

    // Still two rows, not four — the refusal withdraws the same two.
    expect($consents)->toHaveCount(2);

    foreach ($consents as $consent) {
        expect($consent->subject_token)->toBe($token);
        expect($consent->consented)->toBeFalse();
        expect($consent->withdrawn_at)->not->toBeNull();
    }
});

it('expires the HttpOnly affiliate cookie when consent is withdrawn', function () {
    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie('awc', 'click-ref-from-before')
        ->postJson('/api/cookie-consent', ['status' => 'declined']);

    $awc = consentCookieOn($response, 'awc');

    expect($awc)->not->toBeNull();
    expect($awc->getValue())->toBeNull();
    expect($awc->getExpiresTime())->toBeLessThan(time());
});

it('captures the click reference the visitor is carrying at the moment they accept', function () {
    $response = $this->postJson('/api/cookie-consent', [
        'status' => 'accepted',
        'awc' => 'click-ref-xyz',
    ]);

    $awc = consentCookieOn($response, 'awc');

    expect($awc?->getValue())->toBe('click-ref-xyz');
    expect($awc->isHttpOnly())->toBeTrue();
});

it('ignores the click reference when the visitor declines', function () {
    $response = $this->postJson('/api/cookie-consent', [
        'status' => 'declined',
        'awc' => 'click-ref-xyz',
    ]);

    expect(consentCookieOn($response, 'awc')->getValue())->toBeNull();
});

it('ignores the click reference when Awin is disabled', function () {
    config()->set('awin.enabled', false);

    $response = $this->postJson('/api/cookie-consent', [
        'status' => 'accepted',
        'awc' => 'click-ref-xyz',
    ]);

    expect(consentCookieOn($response, 'awc'))->toBeNull();
});

it('rejects anything that is not an accept or a decline', function () {
    $this->postJson('/api/cookie-consent', ['status' => 'maybe'])
        ->assertStatus(422);

    $this->postJson('/api/cookie-consent', [])
        ->assertStatus(422);

    expect(UserConsent::count())->toBe(0);
});

it('ignores a malformed subject token and issues a fresh one', function () {
    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie(CookieConsentService::SUBJECT_COOKIE, 'not-a-token')
        ->postJson('/api/cookie-consent', ['status' => 'accepted']);

    $issued = consentCookieOn($response, CookieConsentService::SUBJECT_COOKIE)->getValue();

    expect($issued)->toMatch('/^[a-f0-9]{64}$/');

    // Both records are written against the freshly-issued subject, not the
    // junk the caller presented.
    expect(UserConsent::pluck('subject_token')->unique()->all())->toBe([$issued]);
});

it('records against the user when the visitor is signed in', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/cookie-consent', ['status' => 'accepted'])
        ->assertOk();

    $consents = UserConsent::whereIn('consent_type', UserConsent::COOKIE_BANNER_TYPES)->get();

    expect($consents)->toHaveCount(2);

    foreach ($consents as $consent) {
        expect($consent->user_id)->toBe($user->id);
        expect($consent->subject_token)->toBeNull();
    }
});

it('claims a pre-account consent onto the user who later registers', function () {
    $token = str_repeat('a', 64);

    foreach (UserConsent::COOKIE_BANNER_TYPES as $type) {
        UserConsent::recordAnonymousConsent($token, $type, true);
    }

    $user = User::factory()->create();

    $claimed = UserConsent::claimAnonymousConsents($token, $user->id);

    // Both activities are claimed, not just whichever came first.
    expect($claimed)->toBe(2);

    foreach (UserConsent::COOKIE_BANNER_TYPES as $type) {
        $consent = UserConsent::where('consent_type', $type)->sole();
        expect($consent->user_id)->toBe($user->id);
        // The token is cleared: the row is the user's now, not the browser's.
        expect($consent->subject_token)->toBeNull();
        expect(UserConsent::hasConsent($user->id, $type))->toBeTrue();
    }
});

it('leaves an anonymous row unclaimed rather than overwrite a consent the user already holds', function () {
    $user = User::factory()->create();
    UserConsent::recordConsent($user->id, UserConsent::TYPE_COOKIES_ANALYTICS, false);

    $token = str_repeat('b', 64);
    UserConsent::recordAnonymousConsent($token, UserConsent::TYPE_COOKIES_ANALYTICS, true);

    $claimed = UserConsent::claimAnonymousConsents($token, $user->id);

    // Neither record is destroyed — the unique key forbids a duplicate and
    // consent evidence is not something to overwrite.
    expect($claimed)->toBe(0);
    expect(UserConsent::where('consent_type', UserConsent::TYPE_COOKIES_ANALYTICS)->count())->toBe(2);
    expect(UserConsent::hasConsent($user->id, UserConsent::TYPE_COOKIES_ANALYTICS))->toBeFalse();
});

it('claims a pre-account consent through the real registration flow', function () {
    // The visitor answers the banner on the landing page, with no account.
    $accept = $this->postJson('/api/cookie-consent', ['status' => 'accepted']);
    $token = consentCookieOn($accept, CookieConsentService::SUBJECT_COOKIE)->getValue();

    $pending = PendingRegistration::create([
        'first_name' => 'Consenting',
        'surname' => 'Visitor',
        'email' => 'consenting.visitor@example.com',
        'password' => Hash::make('Password1!'),
        'verification_code' => '123456',
        'verification_attempts' => 0,
        'signup_source' => 'web',
    ]);

    $this->withCredentials()
        ->withUnencryptedCookie(CookieConsentService::SUBJECT_COOKIE, $token)
        ->postJson('/api/auth/verify-code', [
            'type' => 'registration',
            'pending_id' => $pending->id,
            'code' => '123456',
        ])
        ->assertOk();

    $user = User::where('email', 'consenting.visitor@example.com')->sole();

    // Demonstrable for this user, per activity, dated when they actually gave it.
    foreach (UserConsent::COOKIE_BANNER_TYPES as $type) {
        expect(UserConsent::hasConsent($user->id, $type))->toBeTrue();
        expect(UserConsent::where('consent_type', $type)->count())->toBe(1);
    }
});

it('surfaces both cookie consents in the authenticated consent snapshot', function () {
    $user = User::factory()->create();

    foreach (UserConsent::COOKIE_BANNER_TYPES as $type) {
        UserConsent::recordConsent($user->id, $type, true);
    }

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/gdpr/consents')
        ->assertOk()
        ->assertJsonPath('data.consents.cookies_analytics.consented', true)
        ->assertJsonPath('data.consents.cookies_affiliate.consented', true);
});

it('refuses to write cookie consent through the general consent endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/auth/gdpr/consents', ['consents' => [
            UserConsent::TYPE_COOKIES_ANALYTICS => true,
            UserConsent::TYPE_COOKIES_AFFILIATE => true,
        ]])
        ->assertOk();

    // Silently ignored, not recorded: a record written here alone would be a
    // preference the tracking middleware never sees (W-0049).
    expect(UserConsent::whereIn('consent_type', UserConsent::COOKIE_BANNER_TYPES)->count())->toBe(0);
});
