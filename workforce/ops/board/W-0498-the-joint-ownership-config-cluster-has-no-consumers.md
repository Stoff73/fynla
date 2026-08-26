---
id: W-0498
title: The joint-ownership configuration cluster is live, populated and read by nothing — three accessors with zero callers
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, quality-lead]
status: open
claimed_by: null
severity: low
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0375, W-0463]
prior_art_outcome: extends
source: found while fixing W-0375, 2026-08-26
---

## Intent

W-0463's premise: *"a configured tax rule that nothing reads is a rule the
application knows and never applies."* `ConfiguredRulesHaveConsumersTest` exists to
catch exactly that, and does not currently cover this cluster.

`property_ownership.joint_ownership_types` is **live and populated** on the active
tax configuration:

```php
'joint_tenancy'     => ['survivorship' => true,  'will_override' => false, 'notes' => …],
'tenants_in_common' => ['survivorship' => false, 'will_override' => true,
                        'notes' => 'Your share passes according to your will or intestacy rules'],
```

Every accessor over it has **zero callers**:

| Accessor | Callers |
|---|---|
| `TaxConfigService::getPropertyOwnership()` | none |
| `TaxConfigService::hasSurvivorshipRights()` | none |
| `TaxConfigService::allowsWillOverride()` | none |
| `TaxConfigService::getJointOwnershipType()` | only the two above |

Verified by grep across `app/`, `resources/js/` and `resources/mobile/`.

The `notes` strings are written as user-facing sentences — *"Your share passes
according to your will or intestacy rules"* — and nothing displays them.

## Why this is NOT simply "delete the dead code"

**On the estate path the absence is correct.** `EstateAssetAggregatorService`
produces a second-death estate, where there is no survivor left for a joint tenancy
to pass to, so survivorship must not be consulted. W-0375 rewrote that docblock and
names these accessors explicitly as a trap — they look like missing wiring for a
first-death treatment that was never implemented.

So the question this item has to answer is **which** of three this is, and the answer
may differ per accessor:

1. **A genuine gap** — some surface should be explaining survivorship to a user
   (a will or intestacy narrative is the obvious candidate, given the `notes`
   wording) and silently is not.
2. **Correctly unused** — the estate model is second-death throughout and the config
   is documentation for a feature not yet built. Then it should be *stated* as such,
   and `ConfiguredRulesHaveConsumersTest` extended to allow it deliberately rather
   than by omission.
3. **Dead** — nothing wants it, and both accessors and config should go.

Deciding by deleting would be the wrong order: the `notes` text suggests someone
intended it to reach a user.

## Acceptance

1. Each of the four accessors is classed as gap / deliberately-unused / dead, with
   the reasoning recorded.
2. Whatever the answer, `ConfiguredRulesHaveConsumersTest` covers this cluster
   afterwards — either asserting a consumer exists, or listing it as a known and
   accepted exception with its reason. Silence is what let it sit.
3. **Nothing on the second-death estate path starts consulting `hasSurvivorshipRights()`.**
   That is W-0375's warning and it is not reopened by this item.

## Related

- **W-0375** — the docblock that described the treatment these accessors would
  implement. Fixed 2026-08-26; its rewritten docblock points here.
- **W-0463** — the test and the principle.
