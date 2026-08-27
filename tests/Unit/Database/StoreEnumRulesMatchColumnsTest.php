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
 * **Four properties are checked, because they fail in four different ways:**
 *
 *   - **Rule allows what the column cannot store** → the value passes validation
 *     and dies at the write. `capped` and `offset` were this.
 *   - **Column stores what the rule rejects** → a legitimate value is refused.
 *     `mixed` was this.
 *   - **A column has no rule at all** (W-0501) → nothing to diverge from, so the
 *     two checks above pass by saying nothing. Nineteen columns sat like this.
 *   - **A rule exists but this guard cannot read it** → the same silence, and the
 *     worse kind, because the rule LOOKS covered. See `resolveInList`.
 *
 * **The second direction is not automatically a defect**, which is why this
 * carries an exception list rather than asserting equality. A rule deliberately
 * narrower than its column is a decision, not drift — see DELIBERATELY_NARROWER.
 * Asserting the two must match exactly would have "fixed" a documented design
 * decision and disturbed a CSJ ruling.
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

    // `life_events.event_type` stores 21 values; the application creates 16.
    // The five here are **dead enum values with no creator anywhere** (W-0501):
    // absent from LifeEvent::INCOME_EVENT_TYPES and ::EXPENSE_EVENT_TYPES, from
    // StoreLifeEventRequest:26, and from CoordinatingAgent::handleCreateLifeEvent
    // — i.e. from the form, from Fyn, and from the only vocabulary the code
    // composes with. They survive in LifeEvent::eventTypeLabel() (:161-165),
    // which is what makes them look live.
    //
    // The mechanism guaranteeing they never arrive is that nothing constructs
    // them. Widening the Store to accept them would be worse than refusing them:
    // LifeEventService::createEvent derives impact_type by asking whether the
    // type is in INCOME_EVENT_TYPES, so every one of these five would be filed
    // as an EXPENSE — a divorce and a pay rise both booked as money going out.
    'LifeEventStore::event_type' => ['divorce', 'marriage', 'new_child', 'job_loss', 'income_change'],
];

/**
 * Enum columns knowingly left without a rule in their Store, and why.
 *
 * Same contract as DELIBERATELY_NARROWER: an entry must name the mechanism that
 * validates the column instead, not merely assert that one exists.
 */
const UNVALIDATED_ENUM_COLUMNS = [
    // ActuarialLifeTableStore extends ReferenceDataStore and takes admin/seeder
    // input only, delegating its whole canonical shape to a normaliser. Gender is
    // validated there — ActuarialLifeTableNormaliser:39-40 checks
    // in_array($input['gender'], ['male', 'female'], true) and throws
    // StoreValidationException, which is the same exception a rule here would
    // raise. A ruleset on the Store would be a second home for one check.
    'ActuarialLifeTableStore::gender' => 'ActuarialLifeTableNormaliser:39-40',
];

/**
 * Short class name => fully-qualified name, from a file's `use` statements.
 *
 * Needed to resolve a rule composed from constants: the source says
 * `LifeEvent::INCOME_EVENT_TYPES`, and `constant()` needs the FQN.
 *
 * @return array<string, string>
 */
function storeUseMap(string $source): array
{
    preg_match_all('/^use\s+([^\s;]+);/m', $source, $matches, PREG_SET_ORDER);

    $map = [];
    foreach ($matches as [, $fqn]) {
        $parts = explode('\\', $fqn);
        $map[end($parts)] = $fqn;
    }

    return $map;
}

/**
 * The values an `in:` rule accepts, or null when this guard cannot read them.
 *
 * Two forms are in use and both must be read:
 *
 *   - **Literal** — `'f' => 'sometimes|in:a,b,c'`.
 *   - **Composed** — `'f' => 'sometimes|in:'.implode(',', array_merge(X::A, X::B))`,
 *     which is how a rule reuses a vocabulary that already has a home rather than
 *     retyping it (Rule 20). Resolved by reading the constant references out of
 *     the line and asking PHP for their values.
 *
 * **Returning null matters as much as returning values.** The composed form was
 * added to `LifeEventStore::event_type` and, written behind a local variable,
 * matched the old literal-only regex as an `in:` rule with an empty list — so the
 * guard skipped it and went green while the rule refused five values the column
 * stores. A rule this function cannot read is reported by its own test rather than
 * passed over, because a silently-skipped rule looks exactly like a checked one.
 *
 * @param  array<string, string>  $useMap
 * @return array<int, string>|null
 */
function resolveInList(string $line, array $useMap): ?array
{
    if (preg_match('/\bin:([a-z0-9_,]+)/i', $line, $m) === 1) {
        return explode(',', $m[1]);
    }

    preg_match_all('/([A-Za-z_][A-Za-z0-9_]*)::([A-Z][A-Z0-9_]*)/', $line, $refs, PREG_SET_ORDER);

    if ($refs === []) {
        return null;
    }

    $values = [];
    foreach ($refs as [, $short, $constant]) {
        $fqn = $useMap[$short] ?? $short;

        if (! defined($fqn.'::'.$constant)) {
            return null;
        }

        $resolved = constant($fqn.'::'.$constant);

        if (! is_array($resolved)) {
            return null;
        }

        $values = array_merge($values, $resolved);
    }

    return $values;
}

/**
 * Every `'field' => '...in:...'` rule declared in the Stores.
 *
 * Parsed from source rather than by calling the method: Store rule sets are built
 * inside private methods that take a partial-update flag, so instantiating them
 * here would test the harness rather than the rules.
 *
 * The enclosing method is captured alongside each rule, because in a Store that
 * writes more than one table it is the only thing identifying which table the rule
 * governs.
 *
 * @return array<int, array{0: string, 1: string, 2: array<int, string>|null, 3: string}>
 */
function storeEnumRules(): array
{
    $found = [];

    foreach (glob(base_path('app/Services/Stores/*.php')) as $file) {
        $store = basename($file, '.php');
        $source = file_get_contents($file);
        $useMap = storeUseMap($source);

        preg_match_all(
            '/function\s+([a-zA-Z0-9_]+)\s*\(/',
            $source,
            $methods,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        preg_match_all(
            "/'([a-z0-9_]+)'\s*=>\s*([^\n]*\bin:[^\n]*)/i",
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

            $found[] = [$store, $match[1][0], resolveInList($match[2][0], $useMap), $method];
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

/**
 * The slice of a Store's source belonging to one ruleset.
 *
 * A multi-table Store's rules must be looked for in the method that writes the
 * table in question, not anywhere in the file — `scheme_type` appears in both of
 * PensionStore's DC and DB rulesets, against different columns with disjoint enums.
 */
function rulesetScope(string $source, ?string $method): string
{
    if ($method === null || preg_match('/function\s+'.$method.'\s*\(/', $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
        return $source;
    }

    $start = $m[0][1];
    $end = strlen($source);

    preg_match_all('/function\s+\w+\s*\(/', $source, $all, PREG_OFFSET_CAPTURE);
    foreach ($all[0] as $declaration) {
        if ($declaration[1] > $start && $declaration[1] < $end) {
            $end = $declaration[1];
        }
    }

    return substr($source, $start, $end - $start);
}

describe('a Store never permits a value its column cannot store', function () {
    it('can read every in: rule it finds', function () {
        $unreadable = [];

        foreach (storeEnumRules() as [$store, $field, $values, $method]) {
            if ($values !== null) {
                continue;
            }

            $unreadable[] = sprintf('%s::%s (in %s)', $store, $field, $method !== '' ? $method : 'file scope');
        }

        expect($unreadable)->toBe([], implode("\n", array_merge(
            ['This guard found an in: rule it cannot read, so it is checking NOTHING'],
            ['about that field while appearing to cover it — the worst of the four'],
            ['failure modes, because a skipped rule and a passing rule look alike.'],
            [''],
            ['It happened once already: LifeEventStore::event_type composed its list'],
            ['from constants behind a local variable, matched as an in: rule with no'],
            ['values, and the guard went green while the rule refused five values the'],
            ['column stores.'],
            [''],
            ['Write the list literally, or compose it with the constants named inline'],
            ['on the same line so resolveInList can read them:'],
            $unreadable
        )));
    });

    it('has no rule allowing a value outside the column enum', function () {
        $offenders = [];

        foreach (storeEnumRules() as [$store, $field, $allowed, $method]) {
            $table = tableFor($store, $method);

            if ($table === null || $allowed === null) {
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

            if ($table === null || $allowed === null) {
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

    it('has an accepted-value list for every enum column its Stores write', function () {
        $missing = [];

        foreach (STORE_TABLE as $store => $mapped) {
            $file = base_path("app/Services/Stores/{$store}.php");

            if (! is_file($file)) {
                continue;
            }

            $source = file_get_contents($file);
            $rulesets = is_string($mapped) ? [$mapped] : $mapped;

            foreach ($rulesets as $method => $table) {
                $scope = rulesetScope($source, is_string($method) ? $method : null);

                $columns = DB::select(
                    "SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE = 'enum'
                     ORDER BY COLUMN_NAME",
                    [$table]
                );

                foreach ($columns as $column) {
                    if (preg_match("/'".$column->c."'\s*=>[^\n]*\bin:/", $scope) === 1) {
                        continue;
                    }

                    if (array_key_exists($store.'::'.$column->c, UNVALIDATED_ENUM_COLUMNS)) {
                        continue;
                    }

                    $missing[] = sprintf('%s::%s (%s.%s)', $store, $column->c, $table, $column->c);
                }
            }
        }

        expect($missing)->toBe([], implode("\n", array_merge(
            ['An enum column has NO accepted-value list in the Store that writes it,'],
            ['so the two checks above pass by having nothing to compare. Nineteen'],
            ['columns sat exactly here (W-0501), including every enum on goals and'],
            ['life_events — whose Stores contained no Validator::make at all.'],
            [''],
            ['An unruled key is NOT a dropped key: every Store validates with'],
            ['Validator::make() and none calls validated(), so the write persists the'],
            ['canonical payload as supplied. The column is then the only check, and it'],
            ['reports a failure MySQL cannot attribute to a field.'],
            [''],
            ['Add the list, or record the column in UNVALIDATED_ENUM_COLUMNS naming'],
            ['what validates it instead:'],
            $missing
        )));
    });
});
