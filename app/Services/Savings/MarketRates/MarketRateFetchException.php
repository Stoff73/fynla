<?php

declare(strict_types=1);

namespace App\Services\Savings\MarketRates;

use RuntimeException;

final class MarketRateFetchException extends RuntimeException
{
    public static function challenged(string $url): self
    {
        return new self("MoneySavingExpert refused the request with a browser challenge (HTTP 403) for {$url}. Rates are unchanged. Save the page from a browser and run `php artisan savings:refresh-market-rates --html=<file>`.");
    }

    public static function httpStatus(string $url, int $status): self
    {
        return new self("MoneySavingExpert returned HTTP {$status} for {$url}. Rates are unchanged.");
    }

    public static function nothingParsed(): self
    {
        return new self('No best-buy rates could be read from the MoneySavingExpert pages; their layout may have changed. Rates are unchanged.');
    }
}
