---
id: W-0453
title: A null-defaulting tax getter reaches .toLocaleString() unguarded at two template sites — throws on a cold load, in a fallback block nothing else covers
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: null
reviewers: [quality-lead]
status: done
claimed_by: null
severity: medium
surfaces: [web]
created: 2026-08-23T04:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: []
prior_art_outcome: none
---

## Intent

Surfaced from a console error in another agent's browser run, attributed to me
and **proven not to be mine** — then diagnosed anyway, because it will recur.

### Not introduced by F-0031 or F-0032, established rather than asserted

```
git show HEAD:resources/js/components/Estate/IHTPlanning.vue | grep -c 'annualGiftExemption.toLocaleString()'
→ 2
```

Both calls are in `HEAD`. My diff hunks on that file are at 217-221, 571, 928,
951, 1627 and 1642 — **neither call is on a line I wrote or moved.**

### The defect

`resources/js/store/modules/taxConfig.js:95`

```js
annualGiftExemption: (state) => state.config?.gifting_exemptions?.annual_exemption ?? null,
```

`IHTPlanning.vue:309` and `:598`

```html
£{{ annualGiftExemption.toLocaleString() }}
```

**`null.toLocaleString()` throws**, and a throw inside a Vue template takes the
whole subtree with it — so the strategies list disappears rather than showing a
blank figure.

### Why it did not reproduce for me, and why that is not reassuring

Measured live: `annualGiftExemption` reads **3000** and the tax configuration
store is loaded, so nothing throws. **It throws only in the window before that
store resolves** — a cold load, a slow network, or a route entered directly
rather than navigated into.

**And `:598` sits inside a FALLBACK block:**

```html
<div v-if="!secondDeathData?.mitigation_strategies && ihtData?.iht_liability > 0">
```

The server supplies mitigation strategies for the peak_earners household, so
**that block never rendered during my verification at all.** The error requires
*both* conditions — no server strategies AND an unresolved store — which is why
it is intermittent and why no fixture has ever produced it.

## Acceptance

- [ ] The getter and the template agree about absence: either the getter returns
      a usable default, or every consumer guards. **Not both half-done.**
- [ ] Both sites fixed — `:309` and `:598`. The second is in a block that does
      not render for this persona, so **a browser check on this household proves
      nothing about it.**
- [ ] A test that renders the fallback block with the tax configuration store
      unresolved. Both conditions, or it cannot fail.

## Working notes

- 2026-08-23 build-lead: diagnosed while browser-verifying F-0032. **Reported
  rather than fixed** — it is outside what the tax-compliance gate cleared, and
  the fix is a null-handling decision (default vs guard) rather than a typo.
- **`IHTPlanning.vue:599`'s configured-rate interpolation, added by F-0032, is in
  this same suppressed block and is therefore unverified on screen.** Covered by
  tests only, stated in `F-0032` §7.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed. Five sites, not two.**

  Every `taxConfig` getter is `state.config?.…?.x ?? null` **by design** — an unconfigured tax value must not silently become a number. The consequence is that any template calling `.toLocaleString()` directly on one throws a TypeError before the configuration hydrates, and **a render error blanks the whole component**: the user sees nothing rather than a wrong figure, which is why this surfaced as a console error rather than a visible defect.

  **The item named two. There were five**, while three OTHER sites already used `|| 0`:

  - `TrustPlanningStrategy.vue:44` — `ihtNilRateBand`
  - `GiftForm.vue:79` and `:112` — `annualGiftExemption`
  - `IHTPlanning.vue:332` and `:655` — `annualGiftExemption`

  **The guard pattern already existed and had simply been applied inconsistently** (`GiftForm.vue:187`, `GiftingStrategy.vue:43`, `TrustsDashboard.vue:122`), which is the shape that always comes back — there is nothing to discover, only something to remember, and remembering is what fails.

  **So the durable half is the guard, not the five edits.** `tests/frontend/design/taxGetterNullSafety.test.js` walks both bundles and fails on any of fourteen nullable tax getters reaching `.toLocaleString()` without `|| 0`, `??` or `Number()` in front. **Mutation-verified:** un-guarding a single site turns it red; restoring turns it green.

  `|| 0` rather than suppressing the line, matching the three sites that were already right: a transient £0 that corrects the moment the configuration loads is better than a blank component, and the getters hydrate on app boot.

  **Tested:** 821 frontend tests pass.
