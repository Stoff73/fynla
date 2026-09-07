---
id: W-0498
title: The joint-ownership configuration cluster is live, populated and read by nothing — three accessors with zero callers
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer, quality-lead]
status: done
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

## 2026-09-01 — CLOSED

**The item offered three classifications and the evidence says none of them, as stated.**
`AssetForm.vue:299-301` already showed the user *"Automatically passes to surviving owner
on death"* and *"Your share passes via your will"* — **hardcoded**, beside a configured
version of the same two sentences that nothing read. So it was not a silent gap and not
dead: it was a Rule 20 duplicate with a boundary in the middle. The cluster reached the
backend config and never crossed into the frontend snapshot, so the one consumer that
needed it reimplemented it, in slightly different words.

**Acceptance 1 — classified per accessor, as the item allowed:**

| Accessor | Class | Reasoning |
|---|---|---|
| `getPropertyOwnership()` | **gap, now closed** | A consumer existed and could not reach it. Now called by `TaxConfigSnapshotService`, which publishes `property_ownership.joint_ownership_types` to the frontend. |
| `getJointOwnershipType()` | **gap, now closed** | Serves the above; the `notes` it returns are what the form renders. |
| `hasSurvivorshipRights()` | **deliberately unused** | A FIRST-death question. `EstateAssetAggregatorService` produces a SECOND-death estate — no survivor left for a joint tenancy to pass to — so it must not be consulted there (W-0375). |
| `allowsWillOverride()` | **deliberately unused** | Same reason. |

The two boolean accessors are **kept, not deleted**: the data they read is real and now
reaches users, and a first-death treatment would compose from them rather than
re-deriving. The decision is recorded in their docblock at
`TaxConfigService.php:828-846`, so the absence of a caller reads as a decision instead of
forgotten wiring.

**The fix:** `TaxConfigSnapshotService` publishes the cluster through
`getPropertyOwnership()`; `taxConfig.js` gains a `jointOwnershipTypes` getter;
`AssetForm.vue:297` reads the configured `notes` instead of its own copy. `individual`
and `trust` stay hardcoded there deliberately — the configured cluster is
`joint_ownership_types` and describes joint holdings only, and inventing config entries
for the two sole-ownership cases to make the line uniform would put words in the
configuration nobody wrote.

**Acceptance 2 — covered by `JointOwnershipConfigReachesTheUserTest`**, which asserts
the consumer exists, that no hardcoded copy remains, and that the deliberate-unused
decision is recorded.

**Acceptance 3 — held, and guarded.** A test fails if `->hasSurvivorshipRights(` or
`->allowsWillOverride(` ever appears in `EstateAssetAggregatorService`. W-0375's warning
is not reopened.

### FINDING — two more orphans in the same area, raised not fixed

Adding `property_ownership` to `ConfiguredRulesHaveConsumersTest`'s `GUARDED_AREAS` turns
it **red**: `tenure_types` and `leasehold_reform` have **no consumer anywhere in `app/`**.
They are genuine orphans of exactly this class, and they are outside W-0498.

They were **not** added to `UNIMPLEMENTED_RULES`: an entry there is a decision someone has
taken, with a board item and a date, and nobody has taken one about leasehold reform.
Registering them to make a test pass is the thing that register exists to prevent. The
area therefore stays unguarded, with the audit written into the test file so the next
reader inherits the finding rather than the silence. **This needs a board item and a CSJ
decision.**

**How the miss nearly happened, worth recording:** a plain `grep -rl` over `app/` reports
a consumer for both rules — because it counts `TaxConfigService.php`, the file this
test's haystack deliberately excludes so that a rule cannot look consumed by its own
getter. My first audit said "all three clean" on that count and was wrong; the test
caught it.

Tests: **154 passed** across TaxConfig / ConfiguredRules / JointOwnership / Snapshot;
frontend **26 files passed**.

**Not done:** the `tax-compliance-reviewer` and `quality-lead` reviewers on this item's
front matter were not run — no agent was dispatched, per the session instruction. No
browser drive of the asset form.
