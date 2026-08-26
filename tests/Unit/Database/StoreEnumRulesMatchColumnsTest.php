<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * W-0326 drift guard — a Store's `in:` list versus its column's enum.
 *
 * **The fifth axis, and the one Fyn writes through.** The four axes measured in
 * F-0025 all live in `app/Http/Requests/`. This one does not, and that is exactly
 * why it matters: `resources/mobile/api.js` has no post, put or patch helper
 * anywhere, so **Fyn is not one of `/m`'s write paths — it is the only one**, and
 * Fyn's capture handlers write through the Stores rather than the form requests.
 *
 * A value the request layer accepts and the Store rejects means the web form and
 * the Fyn capture path disagree about what a user may say. W-0326 was that, live:
 * `MortgageStore` rejected `mixed`, which the column stores, all three form
 * requests permit, and the property form offers in its rate-type select — so a
 * part-fixed part-variable mortgage could not be recorded at all.
 *
 * **Both directions are checked, because they fail differently:**
 *
 *   - **Rule allows what the column cannot store** → the value passes validation
 *     and dies at the write. `capped` and `offset` were this.
 *   - **Column stores what the rule rejects** → a legitimate value is refused.
 *     `mixed` was this.
 *
 * **But the second direction is not automatically a defect**, which is the whole
 * reason this test carries an exception list rather than asserting equality. A
 * rule deliberately narrower than its column is a decision, not drift — see
 * DELIBERATELY_NARROWER below. Asserting the two must match exactly would have
 * "fixed" a documented design decision and disturbed a CSJ ruling.
 */

/**
 * Store class => the table it writes, or, for a Store that writes several, the
 * validation method for each table => that table.
 *
 * Resolved by reading each Store, not by guessing from the class name.
 *
 * **PensionStore writes three**, which is why it sat unmapped while this map was
 * one-to-one (W-0329). `DCPension`, `DBPension` and `StatePension` are all created
 * from the one class, each behind its own ruleset.
 *
 * **Resolving a field by "whichever of the Store's tables has that column" is WRONG,
 * and this Store is the proof.** `scheme_type` exists on both `dc_pensions`
 * (`workplace, sipp, personal`) and `db_pensions`
 * (`final_salary, career_average, public_sector`) — same column name, disjoint
 * enums. A first-match-wins lookup checks the DB ruleset against the DC column and
 * reports a defect in BOTH directions at once, on a rule that is entirely correct.
 * Only the enclosing method says which table a rule is about.
 */
const STORE_TABLE = [
    'MortgageStore' => 'mortgages',
    'PropertyStore' => 'properties',
    'SavingsStore' => 'savings_accounts',
    'InvestmentAccountStore' => 'investment_accounts',
    'GoalStore' => 'goals',
    'LiabilityStore' => 'liabilities',
    'LifeEventStore' => 'life_events',
    'CurrencyRateStore' => 'currency_rates',
    'ActuarialLifeTableStore' => 'actuarial_life_tables',
    'SavingsMarketRateStore' => 'savings_market_rates',
    'PensionStore' => [
        'validateDcCanonical' => 'dc_pensions',
        'validateDbCanonical' => 'db_pensions',
        'validateStateCanonical' => 'state_pensions',
    ],
];

/**
 * Rules that are deliberately narrower than their column, with the reason.
 *
 * Each entry is `Store::field => [values the column has that the rule refuses]`.
 * A narrower rule is only acceptable when something upstream guarantees the
 * excluded values never arrive — otherwise it is W-0326 again.
 */
const DELIBERATELY_NARROWER = [
    // `mortgages.ownership_type` gained `tenants_in_common` in the 2026-01-17
    // migration `add_tenants_in_common_to_mortgages_ownership_type`, but the
    // application deliberately does not use it: MortgageNormaliser:79-81 coerces
    // tenants_in_common to joint before the Store ever sees it, and says so in a
    // docblock. Narrowing here is the decision, not drift.
    //
    // `trust` is excluded on the same basis — no path offers a trust-owned
    // mortgage.
    'MortgageStore::ownership_type' => ['tenants_in_common', 'trust'],

];

/**
 * Every `'field' => '...in:a,b,c...'` rule declared in the Stores.
 *
 * Parsed from source rather than by calling the method: Store rule sets are built
 * inside protected methods that take a partial-update flag and a user, so
 * instantiating them here would test the harness rather than the rules.
 *
 * The enclosing method is captured alongside each rule, because in a Store that
 * writes more than one table it is the only thing identifying which table the rule
 * governs.
 *
 * @return array<int, array{0: string, 1: string, 2: array<int, string>, 3: string}>
 */
function storeEnumRules(): array
{
    $found = [];

    foreach (glob(base_path('app/Services/Stores/*.php')) as $file) {
        $store = basename($file, '.php');
        $source = file_get_contents($file);

        preg_match_all(
            '/function\s+([a-zA-Z0-9_]+)\s*\(/',
            $source,
            $methods,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        preg_match_all(
            "/'([a-z0-9_]+)'\s*=>\s*[^\n]*?\bin:([a-z0-9_,]+)/i",
            $source,
            $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $offset = $match[0][1];
            $method = '';

            foreach ($methods as $declaration) {
                if ($declaration[0][1] > $offset) {
                    break;
                }

                $method = $declaration[1][0];
            }

            $found[] = [$store, $match[1][0], explode(',', $match[2][0]), $method];
        }
    }

    return $found;
}

/**
 * The table a rule governs, or null when the Store is unmapped.
 *
 * A single-table Store ignores the method. A multi-table Store resolves through it,
 * and returns null for a rule outside the mapped rulesets rather than guessing — an
 * unattributable rule is skipped, not checked against an arbitrary one of its
 * tables, which is the mistake described on STORE_TABLE.
 */
function tableFor(string $store, string $method): ?string
{
    $mapped = STORE_TABLE[$store] ?? null;

    if ($mapped === null || is_string($mapped)) {
        return $mapped;
    }

    return $mapped[$method] ?? null;
}

/** The enum values a column accepts, or null when it is not an enum. */
function columnEnumValues(string $table, string $column): ?array
{
    $row = DB::selectOne(
        'SELECT COLUMN_TYPE ct, DATA_TYPE dt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    if ($row === null || $row->dt !== 'enum') {
        return null;
    }

    preg_match_all("/'([^']*)'/", $row->ct, $m);

    return $m[1];
}

describe('a Store never permits a value its column cannot store', function () {
    it('has no rule allowing a value outside the column enum', function () {
        $offenders = [];

        foreach (storeEnumRules() as [$store, $field, $allowed, $method]) {
            $table = tableFor($store, $method);

            if ($table === null) {
                continue;
            }

            $enum = columnEnumValues($table, $field);

            if ($enum === null) {
                continue;
            }

            $impossible = array_values(array_diff($allowed, $enum));

            if ($impossible === []) {
                continue;
            }

            $offenders[] = sprintf(
                '%s::%s permits [%s], which %s.%s cannot store (enum: %s)',
                $store, $field, implode(', ', $impossible), $table, $field, implode(', ', $enum)
            );
        }

        expect($offenders)->toBe([], implode("\n", array_merge(
            ['A Store permits a value its column has no room for. Anything sending'],
            ['it passes validation and dies at the write — the W-0326 shape. Either'],
            ['widen the enum by migration or drop the value from the rule:'],
            $offenders
        )));
    });

    it('refuses a value the column stores only where that is a recorded decision', function () {
        $offenders = [];

        foreach (storeEnumRules() as [$store, $field, $allowed, $method]) {
            $table = tableFor($store, $method);

            if ($table === null) {
                continue;
            }

            $enum = columnEnumValues($table, $field);

            if ($enum === null) {
                continue;
            }

            $refused = array_values(array_diff($enum, $allowed));

            if ($refused === []) {
                continue;
            }

            $sanctioned = DELIBERATELY_NARROWER[$store.'::'.$field] ?? [];

            $unsanctioned = array_values(array_diff($refused, $sanctioned));

            if ($unsanctioned === []) {
                continue;
            }

            $offenders[] = sprintf(
                '%s::%s refuses [%s], which %s.%s stores',
                $store, $field, implode(', ', $unsanctioned), $table, $field
            );
        }

        expect($offenders)->toBe([], implode("\n", array_merge(
            ['A Store refuses a value its column stores, and nothing records that as'],
            ['deliberate. That is how W-0326 hid: the mortgage form offered "Mixed",'],
            ['the column stored it, all three requests allowed it, and the Store alone'],
            ['said no — so the save 422d and the modal closed as though it had worked.'],
            [''],
            ['If the narrowing IS intended, add it to DELIBERATELY_NARROWER with the'],
            ['mechanism that guarantees the value never arrives. If it is not, the'],
            ['Store is wrong:'],
            $offenders
        )));
    });
});
