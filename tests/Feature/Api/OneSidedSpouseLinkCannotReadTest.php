<?php

declare(strict_types=1);

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
