<?php

declare(strict_types=1);

use App\Models\Estate\WillDocument;
use App\Models\User;
use App\Services\Estate\WillDocumentService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0350 — a spouse link claimed from one side only must not authorise a WRITE into
 * the named account.
 *
 * `users.spouse_id` is a column written ABOUT the account holder. Every reader that
 * trusted it read "I say N is my spouse" as "N's records are mine". The census ranks
 * writes above reads, because a write into someone else's account is worse than a read
 * of it — and these three wrote twenty-one expenditure columns, and a whole will
 * document, on the strength of the caller having named somebody.
 *
 * The precondition used to be trivial: `SpouseLinkingService::linkExistingSpouse()`
 * wrote BOTH rows from one party's request, so the server established reciprocity for
 * the attacker. W-0347 fixed that write path, which is what makes these gates
 * load-bearing rather than decorative.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // Without this every /api/* call answers 404 "Endpoint not found" rather than a
    // 401 or 403 — the route matches and tier resolution fails behind it, which reads
    // exactly like a missing route and is not one.
    $this->seed(TierConfigurationSeeder::class);

    // BOTH premium: detailed expenditure sits behind its own tier guard, which also
    // answers 403. Without this the one-sided test would pass on somebody else's
    // refusal and prove nothing about the link.
    $this->attacker = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->target = User::factory()->withActivePremiumSubscription()->create([
        'tier' => 'premium',
        'food_groceries' => 400,
        'monthly_expenditure' => 1_200,
    ]);

    // One-sided: the attacker names the target. The target names nobody.
    $this->attacker->update(['spouse_id' => $this->target->id]);
});

it('resolves no spouse from a one-sided link, and the real one when it is returned', function () {
    expect($this->attacker->fresh()->reciprocalLiveSpouse())->toBeNull();

    $this->target->update(['spouse_id' => $this->attacker->id]);

    expect($this->attacker->fresh()->reciprocalLiveSpouse()?->id)->toBe($this->target->id);
});

it('refuses to overwrite the named account\'s expenditure', function () {
    $this->actingAs($this->attacker)
        ->putJson("/api/users/{$this->target->id}/expenditure", [
            'food_groceries' => 9_999,
            'monthly_expenditure' => 9_999,
        ])
        ->assertStatus(403);

    $this->target->refresh();

    expect((float) $this->target->food_groceries)->toBe(400.0)
        ->and((float) $this->target->monthly_expenditure)->toBe(1_200.0);
});

it('allows it once the link is returned, so a real couple is unaffected', function () {
    $this->target->update(['spouse_id' => $this->attacker->id]);

    $this->actingAs($this->attacker)
        ->putJson("/api/users/{$this->target->id}/expenditure", [
            'food_groceries' => 500,
            'monthly_expenditure' => 1_500,
        ])
        ->assertOk();

    // 250, not 500: the form sends what the HOUSEHOLD spends and each account stores
    // its share. What matters here is that the write reached the account at all.
    expect((float) $this->target->fresh()->food_groceries)->toBe(250.0)
        ->not->toBe(400.0);
});

it('refuses to create a mirror will inside the named account', function () {
    // A mirror will carries the caller's executors and guardians into a document
    // written in the other person's account.
    $primary = WillDocument::factory()->create(['user_id' => $this->attacker->id]);

    expect(fn () => app(WillDocumentService::class)->generateMirrorWill($primary))
        ->toThrow(RuntimeException::class, 'no reciprocally linked spouse');

    expect(WillDocument::where('user_id', $this->target->id)->count())->toBe(0);
});

it('does not push the household\'s other half into an account that never linked back', function () {
    // `HouseholdExpenditureWriter` splits the household figure across both rows. On a
    // one-sided link it wrote half of the caller's figure into the named account.
    $this->actingAs($this->attacker)
        ->putJson('/api/user/profile/expenditure', [
            'monthly_expenditure' => 2_000,
            'expenditure_sharing_mode' => 'shared',
        ])
        ->assertOk();

    expect((float) $this->target->fresh()->monthly_expenditure)->toBe(1_200.0);
});
