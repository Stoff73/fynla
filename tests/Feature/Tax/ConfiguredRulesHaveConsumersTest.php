<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

/**
 * W-0463 — a configured tax rule that nothing reads is a rule the application
 * knows and never applies.
 *
 * **Why this test exists, and why the existing guards could not do its job.**
 *
 * `RateLiteralsComeFromConfigurationTest` moves configured rates to values nothing
 * else uses and asserts the service output changes. It is a good guard and it is
 * structurally incapable of catching this defect, because it proves a consumer
 * reads the RIGHT value — it cannot prove a consumer EXISTS:
 *
 *   - Move `business_relief.allowance_cap` from £2.5m to £2.9m → nothing changes,
 *     because nothing reads it. Green.
 *   - Move every `taper_relief` band → nothing changes, because the code emits a
 *     boolean `taper_relief_applicable` instead of applying a percentage. Green.
 *   - Move `quick_succession_relief` → nothing changes, because there is no output
 *     to move. Green.
 *
 * So "the Rule 2 sweep is complete" has been reported, honestly, twice — meaning
 * "no wrong rate literal in the files we read", never "every configured rule is
 * implemented". Absence of a wrong value was read as presence of the right one.
 *
 * **What this asserts.** Every second-level rule group under `inheritance_tax` in
 * the ACTIVE configuration is referenced somewhere in `app/` — by its config key or
 * by its `TaxConfigService` accessor — or is named in the exclusions register below
 * with a reason and an owner.
 *
 * **What it deliberately does NOT assert**, so nobody mistakes green here for more
 * than it is: that the consumer is on a live path, that it reads the rule
 * correctly, or that it applies the whole rule rather than one field of it. A
 * reference in dead code counts. This catches "configured and read by nothing",
 * which is the disease that produced W-0091 and W-0463 — not "configured and read
 * badly", which is what the move-the-value guards are for. The two are
 * complementary and neither replaces the other.
 *
 * **Scope: `inheritance_tax` only, deliberately.** That is the area CSJ named and
 * where every measured orphan sits. Extending to `income_tax`, `capital_gains_tax`,
 * `pension` and the rest is real work with its own exclusions list; doing it
 * speculatively here would produce a register nobody has reviewed, which is worse
 * than no register. Add a group to `GUARDED_AREAS` when its consumers are audited.
 */
uses(RefreshDatabase::class);

/** Tax areas whose rules must have consumers. Add one only after auditing it. */
const GUARDED_AREAS = ['inheritance_tax', 'property_ownership'];

/*
 * `property_ownership` — ADDED 2026-09-04 (W-0533). The note below is kept because it
 * records why it was not added on 2026-09-01, and what changed.
 *
 * `leasehold_reform` now has a consumer: `PropertyCalculationService` reads both bands
 * through `getLeaseholdValuationWarnings()`, `PropertyResource` publishes them on the
 * property, and web and `/m` render them —
 * `tests/Feature/Property/LeaseholdBandsReachTheUserTest.php` asserts the whole chain.
 * `tenure_types` is consumed by the same service, which reads `tenure_type` to decide
 * whether the question arises at all.
 *
 * ── the 2026-09-01 note, kept ──────────────────────────────────────────────────
 * Audited 2026-09-01 (W-0498) and NOT added, deliberately.
 *
 * `joint_ownership_types` is now consumed (TaxConfigSnapshotService → AssetForm.vue) and
 * is covered by `JointOwnershipConfigReachesTheUserTest`, which asserts the consumer
 * directly. But adding the whole area turns this test red on two rules W-0498 does not
 * cover: **`tenure_types` and `leasehold_reform` have no consumer anywhere in `app/`.**
 *
 * They are genuine orphans of the same class. They are not registered in
 * UNIMPLEMENTED_RULES here because an entry there is a decision someone has taken, with
 * a board item and a date — and nobody has taken one about leasehold reform. Raised on
 * W-0498's board file for CSJ instead of parked quietly in a register.
 *
 * Worth recording how they were missed: a plain `grep -rl` over `app/` reports a
 * consumer for both, because it counts `TaxConfigService.php` — the file this test's
 * haystack excludes precisely so a rule cannot look consumed by its own getter.
 */

/**
 * Rules configured but knowingly not implemented.
 *
 * Every entry needs a reason, a board item and a date. An entry is a decision that
 * has been taken, not a place to park a failure — if a rule belongs here, someone
 * decided the application does not implement it yet and said so out loud.
 *
 * @var array<string, string>
 */
const UNIMPLEMENTED_RULES = [
    'inheritance_tax.quick_succession_relief' => 'W-0463 · 2026-08-23 · no implementation anywhere; relief for a second death within five years of an inherited estate. Not modelled and not claimed on any screen.',
    'inheritance_tax.fourteen_year_rule' => 'W-0463 · 2026-08-23 · IMPLEMENTED as an emergent effect rather than a rule of its own — `FailedGiftTaxCalculator` cumulates each transfer against the seven years before ITSELF, which is what produces the fourteen-year reach (IHTM14513). The config group stays registered because nothing reads it as a discrete rule.',
    'inheritance_tax.chargeable_lifetime_transfers' => 'W-0463 · 2026-08-23 · PARTIALLY implemented — `FailedGiftTaxCalculator` reads the lookback, cumulation period, death rate, lifetime rate and taper schedule. Still unmodelled: trust entry/exit and ten-year anniversary charges, and grossing-up where the settlor bears the tax (`lifetime_rate_grossed_up`), which `gifts` cannot express.',
    'inheritance_tax.agricultural_relief' => 'W-0463 · 2026-08-23 · NOT IMPLEMENTABLE AS THE SCHEMA STANDS — there is no agricultural asset type or flag anywhere in the data model, so there is nothing to relieve. Needs a product decision before code. The cap is configured as shared with business relief (`cap_shared_with_bpr`), so when agricultural property becomes expressible it must join the allocation in EstateAssetAggregatorService::applyBusinessPropertyRelief(), NOT get a second cap.',
    // Found by tax-compliance-reviewer 2026-08-23 (F11/F13). Registered here so the
    // gaps are recorded rather than left to be rediscovered:
    //   - `business_relief.rates` (land_used_by_partnership 0.5, land_used_by_company
    //     0.5, investment_company 0.0) and `excluded_businesses` are unread. Every
    //     qualifying asset gets 100%-to-cap. `business_interests` has no column
    //     expressing the category — `business_type` does not map — so this is the
    //     same not-expressible-in-the-schema class as AIM shares.
    //   - `business_relief.cap_transferable_to_spouse` (s124E, £5m combined) is
    //     unmodelled; a single £2.5m cap is applied to the pooled household.
    'inheritance_tax.pension_iht_inclusion' => 'W-0463 · 2026-08-23 · the April 2027 change bringing unused pension funds into the estate. Upcoming law, seeded ahead of its effective date.',
];

function taxConfigRuleGroups(): array
{
    $config = TaxConfiguration::where('is_active', true)->first();
    expect($config)->not->toBeNull('No active TaxConfiguration — seed TaxConfigurationSeeder before running this guard.');

    $data = is_string($config->config_data) ? json_decode($config->config_data, true) : $config->config_data;

    $groups = [];
    foreach (GUARDED_AREAS as $area) {
        foreach (array_keys($data[$area] ?? []) as $rule) {
            // Scalars directly under the area (standard_rate, nil_rate_band) are
            // single values the move-the-value guards already cover. This test is
            // about RULE GROUPS — a configured structure with its own fields.
            if (is_array($data[$area][$rule])) {
                $groups[] = ['area' => $area, 'rule' => $rule, 'path' => $area.'.'.$rule];
            }
        }
    }

    return $groups;
}

function appSourceHaystack(): string
{
    static $haystack = null;

    if ($haystack === null) {
        $parts = [];
        foreach (File::allFiles(base_path('app')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            // The config service itself declares every accessor, so including it
            // would make every rule look consumed by its own getter.
            if (str_ends_with($file->getPathname(), 'Services/TaxConfigService.php')) {
                continue;
            }
            $parts[] = File::get($file->getPathname());
        }
        $haystack = implode("\n", $parts);
    }

    return $haystack;
}

/**
 * Which `TaxConfigService` accessors read which configuration path.
 *
 * Derived by reading the service, not by guessing from the key name. The first
 * version of this guard turned `potentially_exempt_transfers` into
 * `getPotentiallyExemptTransfers(` and reported a false orphan — the accessor is
 * `getPETRules()`. A naming heuristic cannot survive the abbreviations a real
 * codebase uses, and a guard that cries wolf gets switched off.
 *
 * @return array<string, list<string>> config path => accessor method names
 */
function accessorsByConfigPath(): array
{
    static $map = null;

    if ($map !== null) {
        return $map;
    }

    $source = File::get(app_path('Services/TaxConfigService.php'));
    $map = [];

    // `public function getX(...): T { return $this->get('area.rule', ...); }`
    preg_match_all(
        '/public function (\w+)\([^)]*\)[^{]*\{\s*return \$this->get\(\s*[\'"]([\w.]+)[\'"]/',
        $source,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as [, $method, $path]) {
        $map[$path][] = $method;
    }

    return $map;
}

it('has a consumer for every configured inheritance tax rule', function () {
    $this->seed(TaxConfigurationSeeder::class);

    $source = appSourceHaystack();
    $orphans = [];

    foreach (taxConfigRuleGroups() as $group) {
        if (array_key_exists($group['path'], UNIMPLEMENTED_RULES)) {
            continue;
        }

        $referenced = str_contains($source, "'".$group['rule']."'")
            || str_contains($source, '"'.$group['rule'].'"');

        foreach (accessorsByConfigPath()[$group['path']] ?? [] as $accessor) {
            $referenced = $referenced || str_contains($source, '->'.$accessor.'(');
        }

        if (! $referenced) {
            $orphans[] = $group['path'];
        }
    }

    expect($orphans)->toBe([], implode("\n", [
        'Configured tax rules with no consumer anywhere in app/:',
        '  '.implode("\n  ", $orphans),
        '',
        'A rule here is one the application knows and never applies. Either implement it,',
        'or add it to UNIMPLEMENTED_RULES with a reason, a board item and a date — which is',
        'a decision someone has taken, not a way to make this test pass.',
    ]));
});

it('keeps the exclusions register honest', function () {
    $this->seed(TaxConfigurationSeeder::class);

    $configured = array_column(taxConfigRuleGroups(), 'path');

    // A rule that has since been implemented, or removed from the configuration,
    // must not keep an exemption it no longer needs — that is how a register
    // silently becomes a list of things nobody rechecks.
    $stale = array_diff(array_keys(UNIMPLEMENTED_RULES), $configured);

    expect($stale)->toBe([], 'Excluded but no longer a configured rule group — remove the exclusion: '.implode(', ', $stale));

    foreach (UNIMPLEMENTED_RULES as $rule => $reason) {
        expect($reason)->toMatch('/W-\d{4}/', "Exclusion for {$rule} must cite a board item.");
        expect($reason)->toMatch('/\d{4}-\d{2}-\d{2}/', "Exclusion for {$rule} must carry a date.");
    }
});
