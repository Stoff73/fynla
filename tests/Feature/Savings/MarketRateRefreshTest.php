<?php

declare(strict_types=1);

use App\Models\SavingsMarketRate;
use App\Models\User;
use App\Services\Savings\MarketRates\MarketRateFetchException;
use App\Services\Savings\MarketRates\MarketRateRefreshService;
use App\Services\Savings\RateComparator;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->taxYear = app(TaxConfigService::class)->getTaxYear();
});

function fakeMsePages(): void
{
    Http::fake([
        MarketRateRefreshService::SOURCES[0] => Http::response(file_get_contents(base_path('tests/fixtures/Savings/mse-savings-best-interest.html'))),
        MarketRateRefreshService::SOURCES[1] => Http::response(file_get_contents(base_path('tests/fixtures/Savings/mse-best-cash-isa.html'))),
    ]);
}

it('writes one row per benchmark into the active tax year, tagged with provider and source', function () {
    fakeMsePages();

    $summary = app(MarketRateRefreshService::class)->refresh();

    expect($summary['tax_year'])->toBe($this->taxYear)
        ->and($summary['created'])->toHaveCount(9)
        ->and($summary['updated'])->toBeEmpty();

    $rows = SavingsMarketRate::where('tax_year', $this->taxYear)->get()->keyBy('rate_key');
    expect($rows)->toHaveCount(9)
        ->and((float) $rows['fixed_1_year']->rate)->toBe(0.0488)
        ->and($rows['fixed_1_year']->provider)->toBe('Family Building Society')
        ->and($rows['fixed_1_year']->source)->toBe('moneysavingexpert')
        ->and($rows['fixed_1_year']->effective_from->toDateString())->toBe(now()->toDateString())
        ->and($rows['fixed_2_year_isa']->provider)->toBe('Hodge Bank')
        ->and($rows)->not->toHaveKey('notice_isa');
});

it('updates the same rows on the next run instead of duplicating them', function () {
    fakeMsePages();
    $service = app(MarketRateRefreshService::class);
    $service->refresh();

    $second = $service->refresh();

    expect($second['unchanged'])->toHaveCount(9)
        ->and($second['created'])->toBeEmpty()
        ->and(SavingsMarketRate::where('tax_year', $this->taxYear)->count())->toBe(9);
});

it('leaves a hand-entered row alone until the scraped figure differs, then updates it in place', function () {
    fakeMsePages();
    $id = app(SavingsMarketRateStore::class)->create([
        'rate_key' => 'fixed_1_year', 'label' => '1 Year Fixed', 'rate' => 0.0300,
        'tax_year' => $this->taxYear, 'effective_from' => '2026-04-06',
    ], IngestSource::ADMIN);

    $summary = app(MarketRateRefreshService::class)->refresh();

    expect($summary['updated'])->toHaveCount(1)
        ->and((float) SavingsMarketRate::find($id)->rate)->toBe(0.0488)
        ->and(SavingsMarketRate::where('rate_key', 'fixed_1_year')->count())->toBe(1);
});

it('writes nothing when the site answers with the Cloudflare challenge', function () {
    Http::fake(fn () => Http::response('<html>Just a moment... challenge-platform</html>', 403, ['cf-mitigated' => 'challenge']));

    expect(fn () => app(MarketRateRefreshService::class)->refresh())
        ->toThrow(MarketRateFetchException::class, 'browser challenge');
    expect(SavingsMarketRate::count())->toBe(0);
});

it('writes nothing when the page layout yields no rates', function () {
    Http::fake(fn () => Http::response('<html><body><p>Redesigned page</p></body></html>', 200));

    expect(fn () => app(MarketRateRefreshService::class)->refresh())
        ->toThrow(MarketRateFetchException::class, 'layout may have changed');
    expect(SavingsMarketRate::count())->toBe(0);
});

it('dry-runs from saved pages through the artisan command without writing', function () {
    $this->artisan('savings:refresh-market-rates', [
        '--html' => [base_path('tests/fixtures/Savings/mse-savings-best-interest.html'), base_path('tests/fixtures/Savings/mse-best-cash-isa.html')],
        '--dry-run' => true,
    ])->expectsOutputToContain('Dry run for '.$this->taxYear)
        ->expectsOutputToContain('fixed_1_year 4.88% (Family Building Society)')
        ->assertSuccessful();

    expect(SavingsMarketRate::count())->toBe(0);
});

it('fails the command cleanly when the fetch is challenged', function () {
    Http::fake(fn () => Http::response('challenge-platform', 403, ['cf-mitigated' => 'challenge']));

    $this->artisan('savings:refresh-market-rates')->assertFailed();
});

it('falls back to the newest year with rows when the active year has none (F20)', function () {
    $store = app(SavingsMarketRateStore::class);
    $store->create(['rate_key' => 'easy_access', 'label' => 'Easy Access', 'rate' => 0.0450, 'tax_year' => '2025/26', 'effective_from' => '2025-04-06'], IngestSource::SEEDER);
    $store->create(['rate_key' => 'notice', 'label' => 'Notice Account', 'rate' => 0.0500, 'tax_year' => '2025/26', 'effective_from' => '2025-04-06'], IngestSource::SEEDER);

    $benchmarks = app(RateComparator::class)->getMarketBenchmarks($this->taxYear);

    expect($benchmarks['easy_access'])->toBe(0.045)
        ->and($benchmarks['notice'])->toBe(0.05)
        ->and($benchmarks)->not->toHaveKey('fixed_1_year');
});

describe('admin refresh endpoint', function () {
    it('refreshes and returns the list with a summary for an admin', function () {
        fakeMsePages();
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));

        $this->postJson('/api/admin/savings-market-rates/refresh')
            ->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('summary.tax_year', $this->taxYear)
            ->assertJsonPath('data.0.source', 'moneysavingexpert');
    });

    it('reports a challenged fetch as 502 with the rates unchanged', function () {
        Http::fake(fn () => Http::response('challenge-platform', 403, ['cf-mitigated' => 'challenge']));
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));

        $this->postJson('/api/admin/savings-market-rates/refresh')
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'browser challenge'));
        expect(SavingsMarketRate::count())->toBe(0);
    });

    it('is not available to a non-admin', function () {
        Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]));

        $this->postJson('/api/admin/savings-market-rates/refresh')->assertForbidden();
    });
});
