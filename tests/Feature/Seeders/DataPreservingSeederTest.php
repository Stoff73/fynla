<?php

declare(strict_types=1);

use App\Models\CurrencyRate;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\PlanConfiguration;
use App\Models\SavingsMarketRate;
use App\Models\TierConfiguration;
use Database\Seeders\ChrisUserSeeder;
use Database\Seeders\CurrencyRatesSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DiscountCodeSeeder;
use Database\Seeders\InvoiceSequenceSeeder;
use Database\Seeders\PlanConfigurationSeeder;
use Database\Seeders\SavingsMarketRatesSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\DB;

it('preserves an existing discount code state when reseeded', function () {
    $expiresAt = now()->subMonth()->startOfSecond();
    $existing = DiscountCode::factory()->create([
        'code' => 'LAUNCH20',
        'value' => 35,
        'max_uses' => 100,
        'times_used' => 73,
        'expires_at' => $expiresAt,
        'is_active' => false,
    ]);

    $this->seed(DiscountCodeSeeder::class);

    $existing->refresh();

    expect($existing->value)->toBe(35)
        ->and($existing->max_uses)->toBe(100)
        ->and($existing->times_used)->toBe(73)
        ->and($existing->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and($existing->is_active)->toBeFalse();
});

it('does not recreate a soft-deleted seeded discount code', function () {
    $existing = DiscountCode::factory()->create(['code' => 'TRYME']);
    $existing->delete();

    $this->seed(DiscountCodeSeeder::class);

    expect(DiscountCode::withTrashed()->where('code', 'TRYME')->count())->toBe(1)
        ->and(DiscountCode::withTrashed()->where('code', 'TRYME')->first()->trashed())->toBeTrue();
});

it('never rewinds the invoice sequence during a reseed', function () {
    Invoice::factory()->create(['invoice_number' => 'FYN-INV-000042']);

    $this->seed(InvoiceSequenceSeeder::class);

    expect((int) DB::table('invoice_sequences')->where('id', 1)->value('next_value'))->toBe(43)
        ->and(Invoice::generateNumber())->toBe('FYN-INV-000043');
});

it('preserves admin-edited tier configuration during a routine reseed', function () {
    $this->seed(TierConfigurationSeeder::class);
    TierConfiguration::where('tier', 'premium')->update(['price_monthly_pence' => 777]);

    $this->seed(TierConfigurationSeeder::class);

    expect(TierConfiguration::where('tier', 'premium')->value('price_monthly_pence'))->toBe(777);
});

it('preserves the active admin-edited plan configuration during a routine reseed', function () {
    $this->seed(PlanConfigurationSeeder::class);
    $configuration = PlanConfiguration::where('version', '1.0')->firstOrFail();
    $configuration->update([
        'config_data' => ['rates' => ['default_growth_rate' => 0.077]],
        'notes' => 'Admin-managed production settings',
    ]);

    $this->seed(PlanConfigurationSeeder::class);

    expect($configuration->fresh()->is_active)->toBeTrue()
        ->and($configuration->fresh()->config_data)->toBe([
            'rates' => ['default_growth_rate' => 0.077],
        ])
        ->and($configuration->fresh()->notes)->toBe('Admin-managed production settings');
});

it('preserves admin-edited currency rates during a routine reseed', function () {
    $this->seed(CurrencyRatesSeeder::class);
    $rate = CurrencyRate::where('from_ccy', 'GBP')->where('to_ccy', 'EUR')->firstOrFail();
    $rate->update(['rate' => 1.23456789, 'source' => 'admin']);

    $this->seed(CurrencyRatesSeeder::class);

    expect($rate->fresh()->rate)->toBe('1.23456789')
        ->and($rate->fresh()->source)->toBe('admin');
});

it('preserves admin-edited savings market rates during a routine reseed', function () {
    $this->seed(SavingsMarketRatesSeeder::class);
    $rate = SavingsMarketRate::where('rate_key', 'easy_access')->where('tax_year', '2025/26')->firstOrFail();
    $rate->update(['rate' => 0.0612, 'label' => 'Admin benchmark']);

    $this->seed(SavingsMarketRatesSeeder::class);

    expect($rate->fresh()->rate)->toBe('0.0612')
        ->and($rate->fresh()->label)->toBe('Admin benchmark');
});

it('does not run local account fixtures in staging', function () {
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'staging');

    $seeder = new class extends DatabaseSeeder
    {
        /** @var list<class-string> */
        public array $seeders = [];

        public function call($class, $silent = false, array $parameters = [])
        {
            $this->seeders = array_merge($this->seeders, (array) $class);

            return $this;
        }
    };

    try {
        $seeder->run();
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
    }

    expect($seeder->seeders)->not->toContain(ChrisUserSeeder::class);
});
