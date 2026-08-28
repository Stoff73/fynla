<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * W-0329, the SIXTH axis — a Store's numeric ceiling versus the matching request's.
 *
 * The fifth axis, guarded by `StoreEnumRulesMatchColumnsTest`, is about which
 * *values* a Store accepts. This is about how *large* they may be, and it hides
 * better: an enum divergence refuses a legitimate value and someone notices, while
 * a missing ceiling accepts an illegitimate one and nobody does.
 *
 * The reason it matters is the same one: **`resources/mobile/api.js` has no post,
 * put or patch helper anywhere in the bundle, so Fyn is not one of `/m`'s write
 * paths — it is the only one**, and Fyn's capture handlers write through the Stores,
 * not through `app/Http/Requests/`. A bound the request enforces and the Store does
 * not means the web form and the Fyn capture path disagree about what a user may
 * say, and `/m` follows the Store.
 *
 * **An unruled key is not a dropped key.** Every Store validates with
 * `Validator::make($canonical, $rules)` and throws on failure — none calls
 * `validated()`, and the write persists `$canonical`. Laravel ignores keys with no
 * rule, so a field absent from a Store's ruleset is written exactly as supplied. It
 * is not filtered out; it is unchecked. That is what makes an absent bound a defect
 * rather than a no-op, and it is the single fact this test exists to defend.
 *
 * Measured on 2026-08-26 before the guard was written: **15 fields** across
 * `MortgageStore` and `InvestmentAccountStore` were bounded by their request and
 * unbounded by their Store — including `platform_fee_percent`, where a 12% fee was
 * a 422 on the web form and a successful save through Fyn.
 *
 * **A field the request bounds but the Store's table has no column for is NOT a
 * finding.** `StorePropertyRequest` bounds nine `mortgage_*` fields that are not
 * columns on `properties`; they are request-only, handed to the mortgage path.
 * Reporting those would have tripled the count with noise, so the column check is
 * part of the test rather than a filter applied to its output once.
 */

/** Store class => the table it writes => the requests that write the same table. */
const BOUNDED_PAIRS = [
    'MortgageStore' => ['mortgages', ['StoreMortgageRequest', 'UpdateMortgageRequest']],
    'PropertyStore' => ['properties', ['StorePropertyRequest', 'UpdatePropertyRequest']],
    'SavingsStore' => ['savings_accounts', ['StoreSavingsAccountRequest', 'UpdateSavingsAccountRequest']],
    'InvestmentAccountStore' => ['investment_accounts', ['StoreInvestmentAccountRequest', 'UpdateInvestmentAccountRequest']],
    'GoalStore' => ['goals', ['StoreGoalRequest', 'UpdateGoalRequest']],
    'LiabilityStore' => ['liabilities', ['StoreLiabilityRequest', 'UpdateLiabilityRequest']],
    'PensionStore' => ['dc_pensions', ['StoreDCPensionRequest', 'UpdateDCPensionRequest']],
];

/**
 * `ValidationLimits` helpers, resolved to the ceiling they produce.
 *
 * A rule built from one of these carries a bound even though no `max:` appears in
 * the source. Missing this reported `ownership_percentage` as unbounded on the
 * first pass, which it is not.
 */
const HELPER_CEILINGS = [
    'percentageRules' => '100',
    'currencyRules' => '999999999.99',
];

/**
 * Every numeric field in a file, mapped to its `max:` ceiling or null.
 *
 * Parsed from source for the same reason the enum guard parses: Store rule sets are
 * built inside protected methods taking a partial-update flag and a user, and a
 * request's `rules()` needs a bound request instance. Both string syntax
 * (`'f' => 'nullable|numeric|max:10'`) and array syntax
 * (`'f' => ['nullable', 'numeric', 'max:10']`) are in use, so both are read.
 *
 * @return array<string, string|null>
 */
function numericCeilings(string $file): array
{
    if (! is_file($file)) {
        return [];
    }

    $source = file_get_contents($file);
    $found = [];

    preg_match_all("/'([a-z0-9_]+)'\s*=>\s*([^\n]*)/i", $source, $matches, PREG_SET_ORDER);

    foreach ($matches as [, $field, $expression]) {
        foreach (HELPER_CEILINGS as $helper => $ceiling) {
            if (str_contains($expression, $helper.'(')) {
                $found[$field] = $ceiling;

                continue 2;
            }
        }

        if (preg_match('/\b(numeric|integer|decimal)\b/', $expression) !== 1) {
            continue;
        }

        $found[$field] = preg_match('/\bmax:([0-9.]+)/', $expression, $m) === 1 ? $m[1] : null;
    }

    return $found;
}

/** Does this table have this column? A request-only field is not a Store's problem. */
function tableHasColumn(string $table, string $column): bool
{
    return DB::selectOne(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    ) !== null;
}

describe('a Store enforces the ceilings its request enforces', function () {
    it('has no column the request bounds and the Store leaves open', function () {
        $offenders = [];

        foreach (BOUNDED_PAIRS as $store => [$table, $requests]) {
            $storeCeilings = numericCeilings(base_path("app/Services/Stores/{$store}.php"));

            foreach ($requests as $request) {
                foreach (numericCeilings(base_path("app/Http/Requests/{$request}.php")) as $field => $ceiling) {
                    if ($ceiling === null) {
                        continue;
                    }

                    if (! tableHasColumn($table, $field)) {
                        continue;
                    }

                    $storeCeiling = $storeCeilings[$field] ?? null;

                    if ($storeCeiling !== null) {
                        continue;
                    }

                    $offenders[sprintf('%s::%s', $store, $field)] = sprintf(
                        '%s::%s has no ceiling; %s bounds %s.%s at max:%s',
                        $store, $field, $request, $table, $field, $ceiling
                    );
                }
            }
        }

        expect(array_values($offenders))->toBe([], implode("\n", array_merge(
            ['A Store leaves open a bound its request enforces, on a column it writes.'],
            ['Fyn and /m write through the Store and through nothing else, so this is'],
            ['a value the web form refuses and the Fyn capture path accepts.'],
            [''],
            ['An unruled key is NOT dropped: every Store validates with'],
            ['Validator::make() and none calls validated(), so the write persists the'],
            ['canonical payload as supplied. Mirror the request rule into the Store:'],
            array_values($offenders)
        )));
    });

    it('agrees with the request on the ceiling wherever both set one', function () {
        $offenders = [];

        foreach (BOUNDED_PAIRS as $store => [$table, $requests]) {
            $storeCeilings = numericCeilings(base_path("app/Services/Stores/{$store}.php"));

            foreach ($requests as $request) {
                foreach (numericCeilings(base_path("app/Http/Requests/{$request}.php")) as $field => $ceiling) {
                    $storeCeiling = $storeCeilings[$field] ?? null;

                    if ($ceiling === null || $storeCeiling === null) {
                        continue;
                    }

                    if (! tableHasColumn($table, $field)) {
                        continue;
                    }

                    if ((float) $storeCeiling === (float) $ceiling) {
                        continue;
                    }

                    $offenders[sprintf('%s::%s', $store, $field)] = sprintf(
                        '%s::%s allows max:%s; %s allows max:%s',
                        $store, $field, $storeCeiling, $request, $ceiling
                    );
                }
            }
        }

        expect(array_values($offenders))->toBe([], implode("\n", array_merge(
            ['A Store and its request set DIFFERENT ceilings for the same column, so'],
            ['the same figure is accepted on one surface and refused on the other.'],
            ['Whichever is right, both layers must say it:'],
            array_values($offenders)
        )));
    });
});
