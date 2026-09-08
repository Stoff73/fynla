<?php

declare(strict_types=1);

use App\Services\Savings\MarketRates\MarketRateRefreshService;
use App\Services\Savings\MarketRates\MoneySavingExpertRatesParser;

/**
 * F20. Fixtures are the article bodies of the two MoneySavingExpert guides as
 * rendered on 8 September 2026 (saved from a browser; the site challenges
 * non-browser clients). The expected figures are the ones the pages showed.
 */
function mseFixture(string $name): string
{
    return file_get_contents(base_path("tests/fixtures/Savings/{$name}"));
}

beforeEach(function () {
    $this->parser = new MoneySavingExpertRatesParser;
});

it('reads the best-savings guide: easy access, easy-access ISA, notice and the one/two/three-year fixes', function () {
    $rates = $this->parser->parse(mseFixture('mse-savings-best-interest.html'));

    expect($rates)->toHaveKeys(['easy_access', 'easy_access_isa', 'notice', 'fixed_1_year', 'fixed_2_year', 'fixed_3_year'])
        ->and($rates['easy_access'])->toBe(['rate' => 0.05, 'provider' => 'Spring (part of Paragon Bank)'])
        ->and($rates['easy_access_isa'])->toBe(['rate' => 0.0461, 'provider' => 'Trading 212'])
        ->and($rates['fixed_1_year'])->toBe(['rate' => 0.0488, 'provider' => 'Family Building Society'])
        ->and($rates['fixed_2_year'])->toBe(['rate' => 0.0496, 'provider' => 'Shawbrook Bank'])
        ->and($rates['fixed_3_year'])->toBe(['rate' => 0.05, 'provider' => 'Investec'])
        ->and($rates['notice']['rate'])->toBe(0.044)
        ->and($rates['notice']['provider'])->toContain('Birmingham Bank');
});

it('does not invent keys for terms the benchmarks do not track', function () {
    $rates = $this->parser->parse(mseFixture('mse-savings-best-interest.html'));

    expect(array_keys($rates))->each->toBeIn(array_keys(MarketRateRefreshService::LABELS));
});

it('reads the cash-ISA guide: the fixed ISA terms and the easy-access ISA', function () {
    $rates = $this->parser->parse(mseFixture('mse-best-cash-isa.html'));

    expect($rates['fixed_1_year_isa'])->toBe(['rate' => 0.0474, 'provider' => 'Charter Savings Bank'])
        ->and($rates['fixed_2_year_isa'])->toBe(['rate' => 0.0481, 'provider' => 'Hodge Bank'])
        ->and($rates['fixed_3_year_isa'])->toBe(['rate' => 0.0483, 'provider' => 'Charter Savings Bank'])
        ->and($rates['easy_access_isa']['rate'])->toBe(0.0461)
        ->and($rates)->not->toHaveKey('fixed_1_year');
});

it('returns nothing for a page without best-buy tables rather than guessing', function () {
    expect($this->parser->parse('<html><body><h2>Just a moment...</h2><p>Enable JavaScript and cookies to continue</p></body></html>'))->toBe([])
        ->and($this->parser->parse(''))->toBe([]);
});
