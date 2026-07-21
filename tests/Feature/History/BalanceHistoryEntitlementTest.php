<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

it('clamps Free history to the latest 90 days', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'account_name' => 'Long-term portfolio',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 10000,
        'currency' => 'GBP',
        'value_gbp' => 10000,
        'taken_at' => now()->subDays(120),
        'trigger_reason' => 'update',
        'ingest_source' => 'form',
    ]);
    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 11000,
        'currency' => 'GBP',
        'value_gbp' => 11000,
        'taken_at' => now()->subDays(30),
        'trigger_reason' => 'update',
        'ingest_source' => 'form',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/balance-history?from='.now()->subYear()->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.visibility.window_days', 90)
        ->assertJsonPath('data.visibility.label', '90 days of history')
        ->assertJsonPath('data.effective_range.from', now()->subDays(90)->toDateString())
        ->assertJsonCount(1, 'data.series.0.points')
        ->assertJsonPath('data.series.0.points.0.value', 11000);
});

it('returns all requested retained history and exact year-on-year movement for Premium', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'account_name' => 'Long-term portfolio',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $older = now()->subMonthsNoOverflow(13)->startOfDay();
    $latest = now()->startOfDay();

    foreach ([[$older, 10000], [$latest, 12500]] as [$takenAt, $value]) {
        InvestmentAccountValueSnapshot::query()->create([
            'investment_account_id' => $account->id,
            'column_name' => 'current_value_gbp',
            'value' => $value,
            'currency' => 'GBP',
            'value_gbp' => $value,
            'taken_at' => $takenAt,
            'trigger_reason' => 'update',
            'ingest_source' => 'form',
        ]);
    }

    $from = now()->subYears(2)->toDateString();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/balance-history?from='.$from.'&to='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.visibility.window_days', null)
        ->assertJsonPath('data.visibility.label', 'Full available history')
        ->assertJsonPath('data.effective_range.from', $from)
        ->assertJsonCount(2, 'data.series.0.points')
        ->assertJsonPath('data.year_on_year.currency', 'GBP')
        ->assertJsonPath('data.year_on_year.net_worth_change', 2500)
        ->assertJsonPath('data.year_on_year.percentage_change', 25);
});

it('returns a null year-on-year value when less than 12 months are available', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 12500,
        'currency' => 'GBP',
        'value_gbp' => 12500,
        'taken_at' => now()->subMonthsNoOverflow(6),
        'trigger_reason' => 'update',
        'ingest_source' => 'form',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/balance-history?from='.now()->subYear()->toDateString().'&to='.now()->toDateString())
        ->assertOk()
        ->assertJsonPath('data.year_on_year', null);
});

it('does not expose another users balance history', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $other = User::factory()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $other->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 999999,
        'currency' => 'GBP',
        'value_gbp' => 999999,
        'taken_at' => now(),
        'trigger_reason' => 'create',
        'ingest_source' => 'form',
    ]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/balance-history')
        ->assertOk()
        ->assertJsonPath('data.series', [])
        ->assertJsonPath('data.year_on_year', null);
});

it('returns each joint owner only their share of a recorded balance', function () {
    $primary = User::factory()->create(['tier' => 'premium']);
    $jointOwner = User::factory()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $primary->id,
        'joint_owner_id' => $jointOwner->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 70,
    ]);

    InvestmentAccountValueSnapshot::query()->create([
        'investment_account_id' => $account->id,
        'column_name' => 'current_value_gbp',
        'value' => 10000,
        'currency' => 'GBP',
        'value_gbp' => 10000,
        'taken_at' => now(),
        'trigger_reason' => 'update',
        'ingest_source' => 'form',
    ]);

    $this->actingAs($primary, 'sanctum')
        ->getJson('/api/balance-history')
        ->assertOk()
        ->assertJsonPath('data.series.0.points.0.value', 7000)
        ->assertJsonPath('data.totals.0.net_worth', 7000);

    $this->actingAs($jointOwner, 'sanctum')
        ->getJson('/api/balance-history')
        ->assertOk()
        ->assertJsonPath('data.series.0.points.0.value', 3000)
        ->assertJsonPath('data.totals.0.net_worth', 3000);
});
