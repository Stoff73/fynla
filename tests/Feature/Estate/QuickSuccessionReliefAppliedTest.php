<?php

declare(strict_types=1);

use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\PremiumTestPersonaSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0527 — quick succession relief reaches a real household's bill.
 *
 * The unit tests pin the s141 arithmetic against the configured taper. These pin
 * the two things the unit tests cannot: that a qualifying household's LIABILITY
 * actually falls, in BOTH columns, and that a non-qualifying one does not move at
 * all.
 *
 * The non-movement half is not a formality. `iht_paid_on_prior_death` is NULL for
 * almost every inheritance — most estates bear no tax — and NULL is not zero. A
 * household that has not answered the question must be exactly where it was.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(PremiumTestPersonaSeeder::class);

    $this->david = User::where('email', 'david.jones@example.com')->firstOrFail();

    $this->figures = fn (User $u): array => app(IHTCalculationService::class)
        ->calculate($u, $u->liveSpouse(), $u->hasAcceptedSpousePermission());
});

it('leaves a household that has stated no tax on a prior death exactly where it was', function () {
    $before = ($this->figures)($this->david);

    // An inheritance with no `iht_paid_on_prior_death`: the commonest case by far.
    LifeEvent::create([
        'user_id' => $this->david->id,
        'event_name' => 'Inheritance from aunt',
        'event_type' => 'inheritance',
        'amount' => 200_000,
        'impact_type' => 'income',
        'expected_date' => now()->subMonths(6),
        'occurred_at' => now()->subMonths(6),
        'status' => 'completed',
    ]);

    $after = ($this->figures)($this->david->fresh());

    expect((float) $after['iht_liability'])->toBe((float) $before['iht_liability'])
        ->and((float) $after['quick_succession_relief'] ?? 0.0)->toBe(0.0);
});

it('reduces the bill where the tax on the earlier death is stated', function () {
    $before = ($this->figures)($this->david);

    LifeEvent::create([
        'user_id' => $this->david->id,
        'event_name' => 'Inheritance from aunt',
        'event_type' => 'inheritance',
        'amount' => 160_000,
        'iht_paid_on_prior_death' => 40_000,
        'impact_type' => 'income',
        'expected_date' => now()->subMonths(6),
        'occurred_at' => now()->subMonths(6),
        'status' => 'completed',
    ]);

    $after = ($this->figures)($this->david->fresh());

    // 40,000 tax on a 200,000 gross transfer, 160,000 net received, inside the
    // first band: 40,000 × 0.8 × 1.0 = 32,000.
    expect((float) $after['quick_succession_relief'])->toBe(32_000.0)
        ->and((float) $after['iht_liability'])
        ->toBe((float) $before['iht_liability'] - 32_000.0);
});

it('tapers the relief by the years since the earlier death', function () {
    LifeEvent::create([
        'user_id' => $this->david->id,
        'event_name' => 'Inheritance from aunt',
        'event_type' => 'inheritance',
        'amount' => 160_000,
        'iht_paid_on_prior_death' => 40_000,
        'impact_type' => 'income',
        'expected_date' => now()->subYears(3)->subMonths(3),
        'occurred_at' => now()->subYears(3)->subMonths(3),
        'status' => 'completed',
    ]);

    // Between three and four years: the 40% band. 40,000 × 0.8 × 0.4 = 12,800.
    expect((float) ($this->figures)($this->david->fresh())['quick_succession_relief'])
        ->toBe(12_800.0);
});

it('gives nothing once the configured window has passed', function () {
    LifeEvent::create([
        'user_id' => $this->david->id,
        'event_name' => 'Inheritance from aunt',
        'event_type' => 'inheritance',
        'amount' => 160_000,
        'iht_paid_on_prior_death' => 40_000,
        'impact_type' => 'income',
        'expected_date' => now()->subYears(6),
        'occurred_at' => now()->subYears(6),
        'status' => 'completed',
    ]);

    expect((float) ($this->figures)($this->david->fresh())['quick_succession_relief'])
        ->toBe(0.0);
});
