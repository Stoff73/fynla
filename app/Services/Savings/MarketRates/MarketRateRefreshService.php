<?php

declare(strict_types=1);

namespace App\Services\Savings\MarketRates;

use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Http;

/**
 * F20 (CSJ 2026-09-08): the savings benchmarks come from MoneySavingExpert's
 * best-buy tables and are refreshed quarterly. One place fetches, parses and
 * writes; the artisan command, the scheduler and the admin button all call it.
 *
 * Writes go through SavingsMarketRateStore under the SCRAPER source into the
 * ACTIVE tax year, one row per rate_key (updated in place when it already
 * exists). A fetch that fails, or a page that yields nothing, throws before any
 * write, so the previous rates stand.
 */
final class MarketRateRefreshService
{
    public const SOURCES = [
        'https://www.moneysavingexpert.com/savings/savings-accounts-best-interest/',
        'https://www.moneysavingexpert.com/savings/best-cash-isa/',
    ];

    public const SOURCE_TAG = 'moneysavingexpert';

    /** Same labels as SavingsMarketRatesSeeder; notice_isa has no MSE best-buy table and stays admin-managed. */
    public const LABELS = [
        'easy_access' => 'Easy Access',
        'easy_access_isa' => 'Easy Access ISA',
        'notice' => 'Notice Account',
        'notice_isa' => 'Notice ISA',
        'fixed_1_year' => '1 Year Fixed',
        'fixed_1_year_isa' => '1 Year Fixed ISA',
        'fixed_2_year' => '2 Year Fixed',
        'fixed_2_year_isa' => '2 Year Fixed ISA',
        'fixed_3_year' => '3 Year Fixed',
        'fixed_3_year_isa' => '3 Year Fixed ISA',
    ];

    public function __construct(
        private readonly MoneySavingExpertRatesParser $parser,
        private readonly SavingsMarketRateStore $store,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * @param  list<string>|null  $htmlDocuments  Pre-fetched pages (the manual fallback); null fetches SOURCES.
     * @return array{tax_year: string, created: list<string>, updated: list<string>, unchanged: list<string>, skipped: list<string>}
     *
     * @throws MarketRateFetchException
     */
    public function refresh(?array $htmlDocuments = null, bool $dryRun = false, ?int $actorUserId = null): array
    {
        $documents = $htmlDocuments ?? array_map(fn (string $url) => $this->fetch($url), self::SOURCES);

        $rates = [];
        foreach ($documents as $html) {
            // Later pages win: the cash-ISA guide is the home of the ISA benchmarks.
            $rates = array_merge($rates, $this->parser->parse($html));
        }
        if ($rates === []) {
            throw MarketRateFetchException::nothingParsed();
        }

        $taxYear = $this->taxConfig->getTaxYear();
        $today = now()->toDateString();
        $summary = ['tax_year' => $taxYear, 'created' => [], 'updated' => [], 'unchanged' => [], 'skipped' => []];

        foreach ($rates as $key => $found) {
            if (! isset(self::LABELS[$key])) {
                $summary['skipped'][] = $key;

                continue;
            }
            $line = sprintf('%s %.2f%% (%s)', $key, $found['rate'] * 100, $found['provider']);
            $existing = $this->store->findByKeyAndTaxYear($key, $taxYear);
            if ($existing !== null
                && abs((float) $existing->rate - $found['rate']) < 0.00005
                && $existing->provider === $found['provider']) {
                $summary['unchanged'][] = $line;

                continue;
            }

            $payload = [
                'rate_key' => $key,
                'label' => self::LABELS[$key],
                'rate' => $found['rate'],
                'tax_year' => $taxYear,
                'effective_from' => $today,
                'provider' => $found['provider'],
                'source' => self::SOURCE_TAG,
            ];
            if (! $dryRun) {
                $existing === null
                    ? $this->store->create($payload, IngestSource::SCRAPER, $actorUserId)
                    : $this->store->update($existing->id, $payload, IngestSource::SCRAPER, $actorUserId);
            }
            $summary[$existing === null ? 'created' : 'updated'][] = $line;
        }

        return $summary;
    }

    /**
     * @throws MarketRateFetchException
     */
    private function fetch(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-GB,en;q=0.9',
        ])->timeout(20)->get($url);

        // Cloudflare answers a non-browser client with a JavaScript challenge.
        if ($response->status() === 403
            && ($response->header('cf-mitigated') === 'challenge' || str_contains($response->body(), 'challenge-platform'))) {
            throw MarketRateFetchException::challenged($url);
        }
        if (! $response->successful()) {
            throw MarketRateFetchException::httpStatus($url, $response->status());
        }

        return $response->body();
    }
}
