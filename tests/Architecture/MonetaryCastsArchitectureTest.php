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
 */
const ALLOWED_FLOAT_COLUMNS = [
    // example: 'some_ratio_column' => 'dimensionless ratio, not a currency',
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
