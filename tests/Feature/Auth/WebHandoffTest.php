<?php

declare(strict_types=1);

use App\Enums\WebHandoffDestination;
use App\Models\User;
use App\Models\WebHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Cookie;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-09 15:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('requires authentication to issue a web handoff', function (): void {
    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'subscription',
    ])->assertUnauthorized();
});

it('issues a two minute handoff while storing only a hash of the token', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'subscription',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.expires_at', now()->addMinutes(2)->toISOString())
        ->assertJsonMissingPath('data.token');

    $url = $response->json('data.url');
    $token = basename((string) parse_url($url, PHP_URL_PATH));
    $handoff = WebHandoff::query()->sole();

    expect($token)->toHaveLength(64)
        ->and($handoff->user_id)->toBe($user->id)
        ->and($handoff->destination)->toBe(WebHandoffDestination::SUBSCRIPTION)
        ->and($handoff->token_hash)->toBe(hash('sha256', $token))
        ->and($handoff->token_hash)->not->toBe($token)
        ->and($handoff->expires_at->equalTo(now()->addMinutes(2)))->toBeTrue();
});

it('allows only admins to issue the admin destination', function (): void {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'admin',
    ])->assertForbidden();

    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'admin',
    ])->assertCreated();
});

it('rejects destinations outside the server allowlist', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'https://attacker.example/steal',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('destination');
});

it('consumes a valid handoff once into a normal web session', function (): void {
    config()->set('session.domain', '.example.test');

    $user = User::factory()->create();
    $plainToken = Str::random(64);
    WebHandoff::query()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $plainToken),
        'destination' => WebHandoffDestination::PRIVACY,
        'expires_at' => now()->addMinutes(2),
    ]);

    $response = $this->get('/web-handoff/'.$plainToken)
        ->assertRedirect('/settings/privacy')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertPlainCookie('fynla_web_session', '1');

    $marker = collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === 'fynla_web_session');

    expect($marker)->not->toBeNull()
        ->and($marker->isHttpOnly())->toBeFalse()
        ->and($marker->getSameSite())->toBe(Cookie::SAMESITE_LAX)
        ->and($marker->getDomain())->toBeNull()
        ->and(WebHandoff::query()->sole()->consumed_at)->not->toBeNull();

    $this->withHeader('Origin', 'http://localhost')
        ->getJson('/api/auth/user')
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id);

    $this->get('/web-handoff/'.$plainToken)->assertForbidden();
});

it('rejects missing and expired handoffs without creating a session', function (): void {
    $user = User::factory()->create();
    $expiredToken = Str::random(64);
    WebHandoff::query()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $expiredToken),
        'destination' => WebHandoffDestination::SETTINGS,
        'expires_at' => now()->subSecond(),
    ]);

    $this->get('/web-handoff/'.$expiredToken)
        ->assertForbidden()
        ->assertCookieMissing('fynla_web_session');
    $this->get('/web-handoff/'.Str::random(64))
        ->assertForbidden()
        ->assertCookieMissing('fynla_web_session');

    expect(WebHandoff::query()->sole()->consumed_at)->toBeNull();
});

it('prunes handoffs one day after consumption or expiry and keeps recent records', function (): void {
    $user = User::factory()->create();
    $create = static function (string $token, Carbon $expiresAt, ?Carbon $consumedAt = null) use ($user): WebHandoff {
        return WebHandoff::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'destination' => WebHandoffDestination::SETTINGS,
            'expires_at' => $expiresAt,
            'consumed_at' => $consumedAt,
        ]);
    };

    $expired = $create('expired-old', now()->subDay()->subSecond());
    $consumed = $create('consumed-old', now()->addDay(), now()->subDay()->subSecond());
    $recentlyExpired = $create('expired-recent', now()->subHours(23));
    $active = $create('active', now()->addMinute());

    $this->artisan('model:prune', ['--model' => [WebHandoff::class]])
        ->assertSuccessful();

    expect(WebHandoff::query()->find($expired->id))->toBeNull()
        ->and(WebHandoff::query()->find($consumed->id))->toBeNull()
        ->and(WebHandoff::query()->find($recentlyExpired->id))->not->toBeNull()
        ->and(WebHandoff::query()->find($active->id))->not->toBeNull();
});

it('schedules web handoff pruning every day', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('model:prune')
        ->assertSuccessful();
});

// W-0044. This enum is mirrored by hand in the native app
// (`ios-native/Fynla/Core/Navigation/WebHandoffClient.swift`) and consumed by name
// on /m (`resources/mobile/views/modules/EstateBequests.vue` sends 'estate_will').
// `estate_will` was added here and never added to the native mirror, so the native
// app had no route to the Will Builder at all — and the Swift test that claimed to
// assert "the server allowlist" was itself a frozen copy, so nothing caught it.
//
// If this test fails because you added a destination, that is the point: add it to
// the native enum and to `WebHandoffClientTests.exposesOnlyTheServerAllowlistedSemanticDestinations`
// in the same change.
it('keeps the destination allowlist in step with the native mirror', function (): void {
    expect(array_map(
        fn (WebHandoffDestination $case): string => $case->value,
        WebHandoffDestination::cases()
    ))->toBe([
        'admin', 'subscription', 'settings', 'privacy', 'notifications', 'estate_will',
        'estate_iht', 'risk_profile', 'estate_lpa',
    ]);
    // W-0110. `estate_lpa` is deliberately NOT in the native mirror yet: CSJ ruled
    // on 2026-08-31 that iOS is out of scope for the board loop, so the Swift case
    // is deferred to the iOS backlog beside W-0044. Native cannot send a case it
    // does not have, so the gap is a missing route on native — not a 422 — and it
    // is recorded on W-0110 rather than hidden here.
});

// Swift derives an enum's raw value from the case name unless told otherwise, so a
// multi-word destination silently ships as camelCase and is rejected here as a 422.
// Every value must be snake_case for the mirror to be writable safely.
it('uses only snake_case destination values the native mirror can reproduce', function (): void {
    foreach (WebHandoffDestination::cases() as $case) {
        expect($case->value)->toMatch('/^[a-z]+(_[a-z]+)*$/');
    }
});

it('issues the will builder handoff the native app has no screen for', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'estate_will',
    ])->assertCreated()->assertJsonPath('success', true);

    expect(WebHandoff::query()->sole()->destination)
        ->toBe(WebHandoffDestination::ESTATE_WILL);
});

it('rejects a camelCase destination, which is what an unmirrored Swift enum sends', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'estateWill',
    ])->assertStatus(422);
});

// W-0279. `/m` prints the risk engine's conclusion — "Attitude to risk: Balanced" —
// and has no route to the nine factors behind it, because `resources/mobile/router.js`
// has no risk route at all. Neither does native. So both hand off to the web screen
// that holds the breakdown, rather than either surface rebuilding a subset of it.
it('issues the risk profile handoff neither mobile surface has a screen for', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'risk_profile',
    ])->assertCreated()->assertJsonPath('success', true);

    expect(WebHandoff::query()->sole()->destination)
        ->toBe(WebHandoffDestination::RISK_PROFILE);
});

// The path is the whole point of the destination: an allowlisted name that lands
// somewhere else is a handoff to the wrong screen, and nothing above would catch it.
it('lands the risk profile handoff on the web screen that holds the factor breakdown', function (): void {
    expect(WebHandoffDestination::RISK_PROFILE->path())->toBe('/risk-profile');
});

/**
 * W-0110. `/m` had no Lasting Power of Attorney screen and no destination to send
 * anyone to, while Fyn wrote the record from that same device. The estate card now
 * hands off here, so the destination has to be on the server's allowlist and has to
 * land on the screen that actually holds the instrument.
 */
it('issues a handoff to the Lasting Power of Attorney screen', function (): void {
    expect(WebHandoffDestination::ESTATE_LPA->path())->toBe('/estate/power-of-attorney');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/mobile/web-handoffs', [
        'destination' => 'estate_lpa',
    ])->assertCreated()->assertJsonPath('success', true);

    expect(WebHandoff::query()->sole()->destination)
        ->toBe(WebHandoffDestination::ESTATE_LPA);
});
