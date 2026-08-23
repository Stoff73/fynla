<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * W-0263 drift guard — a `max:` rule must never permit a value its column
 * cannot physically store.
 *
 * The third way the validation layer and the schema can disagree, after
 * `nullable`-on-NOT-NULL (W-0052, W-0261) and fillable-but-unvalidated (W-0262).
 * It is invisible to both of those sweeps: nullability is right, the field is
 * validated, the field is fillable, and every layer is correct in isolation. It
 * only appears when you compare the rule's RANGE to the column's PRECISION.
 *
 * `decimal(5,4)` is five significant digits with four after the point, so it
 * stops at 9.9999. A rule of `max:100` in front of it does not reject 12 — it
 * passes 12 to MySQL, which raises `SQLSTATE[22003] Out of range` and the user
 * gets a 500 rather than a validation message.
 *
 * Two tests, guarding in both directions:
 *
 *   1. Every mapping below still fits — catches a column being re-narrowed, or a
 *      `max:` being raised past what the column can hold.
 *   2. Nothing new is unclassified — catches a rule added tomorrow that
 *      over-promises against a column nobody thought to check.
 *
 * Deliberately driven from the live schema and the live rule arrays rather than
 * from expected values written down here: a test that hardcodes both sides
 * cannot fail when they drift together.
 */

/**
 * Verified request-field-to-column mappings.
 *
 * `null` means the field name collides with a column in some other table but
 * this request does not write that table — a classified false positive, not an
 * unchecked one. Resolved by reading the controller or service that consumes the
 * request, not by matching names.
 */
const RULE_COLUMN_MAP = [
    // --- The rows W-0263 widened. Percentages, every one verified against live
    // --- rows before the migration: mortgage rates stored as 4.5000 meaning
    // --- 4.5%, platform fees as 0.4500 meaning 0.45%.
    'App\Http\Requests\StoreMortgageRequest::fixed_interest_rate' => ['mortgages', 'fixed_interest_rate'],
    'App\Http\Requests\StoreMortgageRequest::variable_interest_rate' => ['mortgages', 'variable_interest_rate'],
    'App\Http\Requests\UpdateMortgageRequest::fixed_interest_rate' => ['mortgages', 'fixed_interest_rate'],
    'App\Http\Requests\UpdateMortgageRequest::variable_interest_rate' => ['mortgages', 'variable_interest_rate'],

    // The property wizard writes the same two mortgage columns under prefixed
    // names, via App\Services\Property\MortgageService. Same crash, second door.
    'App\Http\Requests\StorePropertyRequest::mortgage_fixed_interest_rate' => ['mortgages', 'fixed_interest_rate'],
    'App\Http\Requests\StorePropertyRequest::mortgage_variable_interest_rate' => ['mortgages', 'variable_interest_rate'],
    'App\Http\Requests\StorePropertyRequest::mortgage_interest_rate' => ['mortgages', 'interest_rate'],

    'App\Http\Requests\Savings\StoreSavingsAccountRequest::interest_rate' => ['savings_accounts', 'interest_rate'],
    'App\Http\Requests\Savings\UpdateSavingsAccountRequest::interest_rate' => ['savings_accounts', 'interest_rate'],

    'App\Http\Requests\StoreInvestmentAccountRequest::current_ownership_percent' => ['investment_accounts', 'current_ownership_percent'],
    'App\Http\Requests\StoreInvestmentAccountRequest::platform_fee_percent' => ['investment_accounts', 'platform_fee_percent'],
    'App\Http\Requests\StoreInvestmentAccountRequest::advisor_fee_percent' => ['investment_accounts', 'advisor_fee_percent'],
    'App\Http\Requests\StoreInvestmentAccountRequest::interest_rate' => ['investment_accounts', 'interest_rate'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::current_ownership_percent' => ['investment_accounts', 'current_ownership_percent'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::platform_fee_percent' => ['investment_accounts', 'platform_fee_percent'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::advisor_fee_percent' => ['investment_accounts', 'advisor_fee_percent'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::interest_rate' => ['investment_accounts', 'interest_rate'],

    'App\Http\Requests\Retirement\StoreDCPensionRequest::platform_fee_percent' => ['dc_pensions', 'platform_fee_percent'],
    'App\Http\Requests\Retirement\StoreDCPensionRequest::advisor_fee_percent' => ['dc_pensions', 'advisor_fee_percent'],
    'App\Http\Requests\Retirement\StoreDCPensionRequest::expected_return_percent' => ['dc_pensions', 'expected_return_percent'],
    'App\Http\Requests\Retirement\StoreDCPensionRequest::employer_ni_rebate_pct' => ['dc_pensions', 'employer_ni_rebate_pct'],

    'App\Http\Requests\Admin\StoreActuarialLifeTableRequest::life_expectancy_years' => ['actuarial_life_tables', 'life_expectancy_years'],
    'App\Http\Requests\Admin\UpdateActuarialLifeTableRequest::life_expectancy_years' => ['actuarial_life_tables', 'life_expectancy_years'],

    // Four paths write `holdings.ocf_percent` and two write
    // `holdings.dividend_yield`. All of them are pinned, so a bound raised in one
    // place and forgotten in the others fails here (Rule 20).
    'App\Http\Requests\Investment\StoreHoldingRequest::dividend_yield' => ['holdings', 'dividend_yield'],
    'App\Http\Requests\Investment\StoreHoldingRequest::ocf_percent' => ['holdings', 'ocf_percent'],
    'App\Http\Requests\Investment\UpdateHoldingRequest::dividend_yield' => ['holdings', 'dividend_yield'],
    'App\Http\Requests\Investment\UpdateHoldingRequest::ocf_percent' => ['holdings', 'ocf_percent'],
    'App\Http\Requests\StoreInvestmentAccountRequest::holdings.*.ocf_percent' => ['holdings', 'ocf_percent'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::holdings.*.ocf_percent' => ['holdings', 'ocf_percent'],
    'App\Http\Requests\Retirement\StoreDCPensionRequest::holdings.*.ocf_percent' => ['holdings', 'ocf_percent'],
    'App\Http\Requests\StoreInvestmentAccountRequest::holdings.*.allocation_percent' => ['holdings', 'allocation_percent'],
    'App\Http\Requests\UpdateInvestmentAccountRequest::holdings.*.allocation_percent' => ['holdings', 'allocation_percent'],
    'App\Http\Requests\Retirement\StoreDCPensionRequest::holdings.*.allocation_percent' => ['holdings', 'allocation_percent'],

    // --- Classified false positives: the field name matches a narrow column in
    // --- another table, but this request does not write that table.
    // `liabilities.interest_rate` is decimal(8,4); the match against
    // `cash_accounts.interest_rate` is a name collision.
    'App\Http\Requests\Estate\StoreLiabilityRequest::interest_rate' => ['liabilities', 'interest_rate'],
    'App\Http\Requests\Estate\UpdateLiabilityRequest::interest_rate' => ['liabilities', 'interest_rate'],
    // `currency_rates.rate` is decimal(18,8), not `savings_market_rates.rate`.
    'App\Http\Requests\Admin\StoreCurrencyRateRequest::rate' => ['currency_rates', 'rate'],
    'App\Http\Requests\Admin\UpdateCurrencyRateRequest::rate' => ['currency_rates', 'rate'],
    // `life_events.amount` is decimal(15,2); `payments`/`subscriptions` are other tables.
    'App\Http\Requests\StoreLifeEventRequest::amount' => ['life_events', 'amount'],
    'App\Http\Requests\UpdateLifeEventRequest::amount' => ['life_events', 'amount'],
    // Writes `personal_accounts`, decimal(15,2) — see PersonalAccountsController.
    'App\Http\Requests\StorePersonalAccountLineItemRequest::amount' => ['personal_accounts', 'amount'],
    'App\Http\Requests\UpdatePersonalAccountLineItemRequest::amount' => ['personal_accounts', 'amount'],
    // Mortgage rate columns other than the two portion rates.
    'App\Http\Requests\StoreMortgageRequest::interest_rate' => ['mortgages', 'interest_rate'],
    'App\Http\Requests\UpdateMortgageRequest::interest_rate' => ['mortgages', 'interest_rate'],
];

/**
 * The largest value a column can physically hold, or null for a type where the
 * question does not arise.
 */
function columnCapacity(string $table, string $column): ?float
{
    $rows = DB::select(
        'SELECT DATA_TYPE dt, COLUMN_TYPE ct, NUMERIC_PRECISION p, NUMERIC_SCALE s
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    if ($rows === []) {
        return null;
    }

    $c = $rows[0];

    if ($c->dt === 'decimal') {
        return 10 ** ($c->p - $c->s) - 10 ** (-$c->s);
    }

    $signed = match ($c->dt) {
        'tinyint' => 127.0,
        'smallint' => 32767.0,
        'mediumint' => 8388607.0,
        'int' => 2147483647.0,
        'bigint' => 9223372036854775807.0,
        default => null,
    };

    if ($signed === null) {
        return null; // float/double and everything non-numeric: not a precision question.
    }

    return str_contains($c->ct, 'unsigned') ? $signed * 2 + 1 : $signed;
}

/**
 * Every form request that declares rules, as [class => rules array].
 *
 * Instantiated and called rather than parsed, so inherited and conditional rules
 * are included exactly as a real request would see them.
 */
function formRequestRules(): array
{
    $out = [];
    $dir = base_path('app/Http/Requests');

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (! preg_match('/namespace\s+([^;]+);/', $source, $ns)) {
            continue;
        }

        if (! preg_match('/class\s+(\w+)/', $source, $cls)) {
            continue;
        }

        $class = trim($ns[1]).'\\'.$cls[1];

        if (! class_exists($class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->hasMethod('rules')) {
            continue;
        }

        try {
            $out[$class] = (new $class)->rules();
        } catch (Throwable) {
            // A request whose rules() needs a bound route or an authenticated
            // user cannot be swept this way. Skipping is honest; pretending it
            // passed would not be.
            continue;
        }
    }

    return $out;
}

/**
 * Numeric `max:` rules across every form request, as
 * [class, field, max, ruleString].
 */
function numericMaxRules(): array
{
    $found = [];

    foreach (formRequestRules() as $class => $rules) {
        foreach ($rules as $field => $rule) {
            $rule = is_array($rule)
                ? implode('|', array_map(
                    fn ($r) => is_object($r) ? $r::class : (string) $r,
                    $rule
                ))
                : (string) $rule;

            if (! preg_match('/\b(numeric|integer|decimal)\b/', $rule)) {
                continue;
            }

            if (! preg_match('/\bmax:([0-9.]+)/', $rule, $m)) {
                continue;
            }

            $found[] = [$class, (string) $field, (float) $m[1], $rule];
        }
    }

    return $found;
}

describe('a validation max never exceeds what its column can store', function () {
    it('holds for every verified request-to-column mapping', function () {
        $offenders = [];

        foreach (numericMaxRules() as [$class, $field, $max, $rule]) {
            $key = $class.'::'.$field;

            if (! array_key_exists($key, RULE_COLUMN_MAP)) {
                continue; // The second test owns unclassified rules.
            }

            [$table, $column] = RULE_COLUMN_MAP[$key];
            $capacity = columnCapacity($table, $column);

            if ($capacity === null || $max <= $capacity) {
                continue;
            }

            $offenders[] = sprintf(
                '%s::%s permits %s but %s.%s stops at %s',
                class_basename($class), $field, $max, $table, $column, $capacity
            );
        }

        expect($offenders)->toBe([], implode("\n", array_merge(
            ['A rule promises a range its column cannot store. The user gets'],
            ['`SQLSTATE[22003] Out of range` — a 500, not a validation message.'],
            ['Widen the column (W-0263 migration 2026_08_22_010000) unless the'],
            ['narrower range is genuinely what the product means:'],
            $offenders
        )));
    });

    it('leaves no new rule unclassified', function () {
        // Every narrow decimal column in the schema, by name, so a new rule on a
        // field sharing that name is caught wherever it is added.
        $narrow = [];
        $rows = DB::select(
            "SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ct, NUMERIC_PRECISION p, NUMERIC_SCALE s
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'decimal'"
        );

        foreach ($rows as $r) {
            $narrow[$r->c][$r->t] = 10 ** ($r->p - $r->s) - 10 ** (-$r->s);
        }

        $unclassified = [];

        foreach (numericMaxRules() as [$class, $field, $max]) {
            $key = $class.'::'.$field;

            if (array_key_exists($key, RULE_COLUMN_MAP)) {
                continue;
            }

            $bare = preg_replace('/^.*\./', '', $field);

            if (! isset($narrow[$bare])) {
                continue;
            }

            foreach ($narrow[$bare] as $table => $capacity) {
                if ($max > $capacity) {
                    $unclassified[] = sprintf(
                        '%s::%s permits %s; a column named `%s` in `%s` stops at %s',
                        class_basename($class), $field, $max, $bare, $table, $capacity
                    );
                    break;
                }
            }
        }

        expect($unclassified)->toBe([], implode("\n", array_merge(
            ['A numeric rule over-promises against a same-named column somewhere in'],
            ['the schema. Resolve which table this request actually writes — read the'],
            ['controller or service, do not match on the name — then add it to'],
            ['RULE_COLUMN_MAP. Mapping it to the real column is the answer whether it'],
            ['turns out to be a defect or a name collision:'],
            $unclassified
        )));
    });
});
