<?php

declare(strict_types=1);

use App\Models\SpousePermission;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0350 Tier 1 — a spouse link claimed from one side only must not disclose the named
 * account's financial data.
 *
 * The flagship is `NetWorthController::getOverview`, where a comment asserting
 * *"if spouse exists and data sharing is enabled"* sat directly above a complete
 * disclosure that checked neither: total assets, total liabilities, net worth, and the
 * full breakdown across pensions, property, investments, cash, business and chattels,
 * plus mortgages, loans and credit cards.
 *
 * **These require RECIPROCITY, not consent.** Measured on the development database, 8
 * of the 12 reciprocally linked accounts have no accepted permission row, so gating
 * these reads on `hasAcceptedSpousePermission()` would take the spouse panel away from
 * two-thirds of real couples. That inconsistency with the Inheritance Tax path, which
 * DOES require consent for the same class of data, is recorded on the board as a
 * decision rather than made silently here.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);

    $this->viewer = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->target = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    // One-sided: the viewer names the target. The target names nobody.
    $this->viewer->update(['spouse_id' => $this->target->id]);

    $this->returnTheLink = fn () => $this->target->update(['spouse_id' => $this->viewer->id]);
});

it('does not put the named account\'s net worth in the overview', function () {
    $body = $this->actingAs($this->viewer)->getJson('/api/net-worth/overview')
        ->assertOk()
        ->json();

    expect($body)->not->toHaveKey('spouse_data');
});

it('puts it there once the link is returned, so a real couple still sees it', function () {
    ($this->returnTheLink)();

    $body = $this->actingAs($this->viewer)->getJson('/api/net-worth/overview')
        ->assertOk()
        ->json();

    expect($body)->toHaveKey('spouse_data')
        ->and($body['spouse_data'])->toHaveKeys(['totalAssets', 'totalLiabilities', 'netWorth']);
});

it('does not disclose the named account\'s financial commitments', function () {
    $this->actingAs($this->viewer)
        ->getJson('/api/user/spouse/financial-commitments')
        ->assertStatus(404);

    ($this->returnTheLink)();

    $this->actingAs($this->viewer->fresh())
        ->getJson('/api/user/spouse/financial-commitments')
        ->assertOk();
});

it('does not disclose the named account through the letter to spouse', function () {
    $this->actingAs($this->viewer)
        ->getJson('/api/user/letter-to-spouse/spouse')
        ->assertStatus(404);
});

describe('Tier 3 — reads that were gated on the link being LIVE but not RETURNED', function () {
    it('does not hand over the named account\'s whole profile', function () {
        // `User` soft-deletes, so `liveSpouseId()` was already most of what the live
        // test bought. What it never answered is whether they named this account back.
        $this->actingAs($this->viewer)
            ->getJson("/api/users/{$this->target->id}")
            ->assertStatus(403);

        ($this->returnTheLink)();

        $this->actingAs($this->viewer->fresh())
            ->getJson("/api/users/{$this->target->id}")
            ->assertOk();
    });
});

describe('W-0530 — consent, not only a returned link', function () {
    /**
     * CSJ, 2026-08-29. Reciprocity says the couple exist; consent says they agreed to
     * share money. A financial read wants both.
     *
     * This is the live population: on the development database 8 of the 12 reciprocal
     * couples sit at `pending` — asked and unanswered — and every one of them was having
     * the other's figures disclosed.
     */
    beforeEach(function () {
        ($this->returnTheLink)();

        SpousePermission::create([
            'user_id' => $this->viewer->id,
            'spouse_id' => $this->target->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    });

    it('withholds the net worth while the invitation is unanswered', function () {
        $body = $this->actingAs($this->viewer->fresh())->getJson('/api/net-worth/overview')
            ->assertOk()
            ->json();

        expect($body)->not->toHaveKey('spouse_data');
    });

    it('withholds the whole profile while the invitation is unanswered', function () {
        // `UserResource` carries income and expenditure, so this is a financial
        // disclosure whatever the endpoint is called.
        $this->actingAs($this->viewer->fresh())
            ->getJson("/api/users/{$this->target->id}")
            ->assertStatus(403);
    });

    it('discloses once the invitation is accepted', function () {
        SpousePermission::where('user_id', $this->viewer->id)->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);

        $body = $this->actingAs($this->viewer->fresh())->getJson('/api/net-worth/overview')
            ->assertOk()
            ->json();

        expect($body)->toHaveKey('spouse_data');
    });
});
