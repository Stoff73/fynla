<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\MortgageMapper;
use App\Services\Documents\FieldMappers\SavingsAccountMapper;

/**
 * The statement reader returns rates as decimals (AIExtractionService: "3.5% =
 * 0.035"); the savings and mortgage columns hold percentages. An uploaded
 * 3.5% used to be stored as 0.035 and read everywhere else as 0.035%.
 */
it('maps an extracted rate to the percentage the column stores', function (string $mapper, mixed $extracted, float $stored): void {
    expect((new $mapper)->map(['interest_rate' => $extracted])['interest_rate'])->toBe($stored);
})->with([
    'savings decimal' => [SavingsAccountMapper::class, 0.035, 3.5],
    'savings percent string' => [SavingsAccountMapper::class, '4.2%', 4.2],
    'savings below 1%' => [SavingsAccountMapper::class, 0.005, 0.5],
    'mortgage decimal' => [MortgageMapper::class, 0.0475, 4.75],
    'mortgage whole number' => [MortgageMapper::class, 5, 5.0],
]);
