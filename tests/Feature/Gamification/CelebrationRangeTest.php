<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use Laravel\Sanctum\Sanctum;

/**
 * The celebration is a RANGE, not a single pending level — the level number
 * climbs one at a time on the dashboard, so the server must be able to say
 * "you are owed 4, 5 and 6" (CSJ 2026-09-17).
 */
it('owes nothing when the celebrated level has caught up with the real level', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 290, 'level' => 4, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 4)
        ->assertJsonPath('celebrate_to', 4);
});

it('owes every level above the one last celebrated', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 4)
        ->assertJsonPath('celebrate_to', 7);
});

/**
 * The null case MUST coalesce to 1, never to the current level. Coalescing to
 * the level would mean a brand-new user who reaches level 2 before ever
 * acking has from == to, and is never celebrated at all.
 */
it('owes a brand new user the level they just reached', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 60, 'level' => 2, 'celebrated_level' => null,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 1)
        ->assertJsonPath('celebrate_to', 2);
});

it('banks the acknowledged level so the climb never replays', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 7])
        ->assertOk()
        ->assertJsonPath('acknowledged', true)
        ->assertJsonPath('celebrated_level', 7);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 7)
        ->assertJsonPath('celebrate_to', 7);
});

it('is idempotent — a second ack cannot push the celebrated level past the real one', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 7])->assertOk();
    $this->postJson('/api/gamification/celebration/ack', ['level' => 99])
        ->assertOk()
        ->assertJsonPath('celebrated_level', 7);

    expect(UserGamification::where('user_id', $user->id)->value('celebrated_level'))->toBe(7);
});

it('never lowers the celebrated level on a late or out of order ack', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 6,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 5])
        ->assertOk()
        ->assertJsonPath('celebrated_level', 6);
});

it('acks everything owed when no level is given', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 4,
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/gamification/celebration/ack')
        ->assertOk()
        ->assertJsonPath('celebrated_level', 7);
});

/**
 * THE PRODUCTION SAFETY TEST. Every existing row is migrated as
 * already-celebrated. Without it, a live user sitting at level 6 gets a
 * five-step climb the next time they open the dashboard.
 */
it('owes nothing to a user who existed before this feature', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id, 'total_points' => 900, 'level' => 7, 'celebrated_level' => 7,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 7)
        ->assertJsonPath('celebrate_to', 7);
});

/**
 * The stored `level` column and the level derived from points can drift
 * (csjones user 399 had 825 points — level 7 — while the column still said 6).
 * status() offers the range from the DERIVED level, so the ack must clamp
 * against the same thing. Clamped against the stale column, the ack could
 * never catch up and the climb replayed on every dashboard view. Found in the
 * browser, 2026-09-17.
 */
it('acknowledges up to the level the points earn, even when the stored column has drifted', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create([
        'user_id' => $user->id,
        'total_points' => 825,   // level 7 on the ladder
        'level' => 6,            // stale column
        'celebrated_level' => 3,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 3)
        ->assertJsonPath('celebrate_to', 7);

    $this->postJson('/api/gamification/celebration/ack', ['level' => 7])
        ->assertOk()
        ->assertJsonPath('celebrated_level', 7);

    // The climb must now be settled — not offered again on the next view.
    $this->getJson('/api/gamification/status')
        ->assertOk()
        ->assertJsonPath('celebrate_from', 7)
        ->assertJsonPath('celebrate_to', 7);
});
