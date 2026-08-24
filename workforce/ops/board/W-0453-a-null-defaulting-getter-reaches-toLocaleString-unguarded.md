---
id: W-0453
title: A null-defaulting tax getter reaches .toLocaleString() unguarded at two template sites — throws on a cold load, in a fallback block nothing else covers
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0032-cycle4-rate-literals-and-the-charitable-denominator.md
owner: null
reviewers: [quality-lead]
status: queued
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
