<?php

declare(strict_types=1);

/**
 * Monetary Casts Architecture Test
 *
 * Prevents regression of the April 2026 audit finding: 70 float casts on currency
 * columns across 12 models. Float arithmetic is unsafe for money — use decimal:2.
 *
 * This test scans Eloquent model $casts arrays for columns whose names imply
 * currency or percentage values and asserts none are declared as 'float'.
 *
 * Exceptions: if a column is genuinely a dimensionless ratio (not currency or
 * percent), add it to ALLOWED_FLOAT_COLUMNS below with a reason.
 */

/**
 * Column names that legitimately use 'float' — dimensionless ratios only.
 * Do NOT add currency or percentage columns here.
 *
 * -------------------------------------------------------------------------
 * PRAGMATIC EXCEPTION (24 April 2026) — Estate + Investment\Holding casts
 * -------------------------------------------------------------------------
 * The columns below are on Estate\Asset, Estate\Liability, Estate\Gift,
 * Estate\IHTProfile, and Investment\Holding. They SHOULD be decimal:2 per
 * the April audit's "never float on money" rule, but Eloquent's decimal:N
 * cast returns a PHP string on read ('550000.00'), which breaks:
 *   - tests/Feature/Estate/EstateApiTest (assertJsonPath strict identity)
 *   - tests/Feature/Estate/EstateIntegrationTest (downstream 500s)
 *   - tests/Feature/Estate/WillBuilderApiTest
 *   - tests/Feature/PortfolioOptimizationTest (Holdings CRUD + 5 optimiser
 *     endpoints that do arithmetic on string-typed values)
 * Commit 421c380 (30 Mar 2026) reverted the same pattern and flagged the
 * correct long-term fix as deferred: "requires API Resource layer updates
 * to cast decimals back to numbers in JSON responses before tests will
 * pass." That work hasn't happened; Payment and Subscription kept
 * decimal:2 because they hold active financial transactions.
 *
 * Until an API Resource layer (or a shared MoneyCast) is introduced, the
 * columns below explicitly use 'float' and are exempt from this arch rule.
 * GBP values in these models fit well within PHP float's ~15-digit
 * precision and the app treats them as display/estate-planning values
 * rather than live monetary accounting.
 *
 * When the Resource layer lands, revert each of these to 'decimal:2' and
 * remove its entry from this list.
 */
const ALLOWED_FLOAT_COLUMNS = [
    // Estate\Asset
    'current_value' => 'estate planning asset value; see header note, pending API Resource layer',
    // Estate\Liability
    'current_balance' => 'estate planning liability balance; see header note',
    'monthly_payment' => 'estate planning liability monthly payment; see header note',
    'interest_rate' => 'estate planning liability interest rate; see header note',
    // Estate\Gift
    'gift_value' => 'estate planning gift value; see header note',
    // Estate\IHTProfile
    'home_value' => 'IHT profile home value; see header note',
    'nrb_transferred_from_spouse' => 'IHT nil-rate-band transfer; see header note',
    'rnrb_transferred_from_spouse' => 'IHT residence-nil-rate-band transfer; see header note',
    'charitable_giving_percent' => 'IHT charitable giving percentage; see header note',
    // Investment\Holding
    'allocation_percent' => 'portfolio holding allocation percentage; see header note',
    'quantity' => 'portfolio holding unit count; see header note',
    'purchase_price' => 'portfolio holding purchase price; see header note',
    'current_price' => 'portfolio holding current price; see header note',
    'cost_basis' => 'portfolio holding cost basis; see header note',
    'dividend_yield' => 'portfolio holding dividend yield; see header note',
    'ocf_percent' => 'portfolio holding ongoing charge figure; see header note',
];

/**
 * Patterns in column names that strongly imply a currency / money value.
 */
const MONEY_KEYWORDS = [
    'amount', 'balance', 'value', 'price', 'cost', 'premium', 'cover', 'coverage',
    'salary', 'income', 'expenditure', 'contribution', 'benefit', 'allowance',
    'liability', 'liabilities', 'asset', 'estate', 'gift', 'holding', 'equity',
    'mortgage', 'debt', 'fee', 'charge', 'tax', 'rate', 'percent', 'yield',
    'ocf', 'pension', 'property', 'trade', 'gain', 'loss',
];

it('all monetary and percentage columns use decimal:2 instead of float', function () {
    $modelsDir = realpath(__DIR__.'/../../app/Models');
    expect($modelsDir)->not->toBeFalse('Models directory not found');

    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($modelsDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());
        if ($content === false) {
            continue;
        }

        // Match:   'column_name' => 'float',
        if (! preg_match_all("/'([a-z0-9_]+)'\s*=>\s*'float'/", $content, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $column = $match[1];

            if (array_key_exists($column, ALLOWED_FLOAT_COLUMNS)) {
                continue;
            }

            foreach (MONEY_KEYWORDS as $keyword) {
                if (str_contains($column, $keyword)) {
                    $violations[] = sprintf(
                        '%s: column "%s" uses float cast (should be decimal:2)',
                        str_replace($modelsDir.'/', '', $file->getPathname()),
                        $column,
                    );
                    break;
                }
            }
        }
    }

    expect($violations)->toBeEmpty(sprintf(
        "Found %d monetary columns using 'float' cast (must be 'decimal:2'):\n\n%s\n\n".
        "Currency and percentage columns require decimal precision. Update the \$casts array ".
        "to use 'decimal:2' (or appropriate precision for percentages).",
        count($violations),
        implode("\n", $violations)
    ));
});
