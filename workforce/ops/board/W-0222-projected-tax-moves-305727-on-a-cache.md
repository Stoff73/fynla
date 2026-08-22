---
id: W-0222
title: The headline projected tax figure moves by £305,727 depending on whether a cache is warm
mission: M-0002-persona-fidelity
branch: null
owner: build-lead
reviewers: [chief-of-staff]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-22T08:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: ["W-0137 the cash floor - fixed, and the reason both readings are internally consistent", "W-0188 the two logins diverging - fixed and still holding", "W-0217 the investment model produces a higher return from the lower risk preference", "W-0131 the Inheritance Tax cache is never written - a different cache"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: observed by persona-passA3 in cycle 3; deliberately not raised by it, and raised here because the magnitude is the finding
---

## Intent

**The same household, unchanged, produces two different headline projected tax figures
depending on the state of an investment cache.**

| Measured | Projected net estate | Projected tax |
|---|---|---|
| `cycle2-projection`, read-only via the service | £7,499,393 | £2,791,757 |
| `persona-passA3`, in a browser, next cycle | **£8,263,710** | **£3,097,484** |
| Difference | £764,317 | **£305,727** |

**Neither reading is a regression and both are internally consistent.** The two logins
agree with each other in both readings — which was the invariant W-0188 established and
it still holds. The variance is the Monte Carlo investment projection, which caches.

**Why this is an item rather than an accepted property.** A projection is allowed to be
uncertain. **But the number on the page is presented as a single figure with no stated
uncertainty**, and it moves by more than £300,000 for reasons that have nothing to do
with the household — nobody edited anything, nothing was recorded, no assumption changed.
**Two users of the same household, or the same user on two days, can be shown figures
£305,727 apart and neither is told why.**

The question the item exists to answer is **which number a user is entitled to see**, and
it is a product and modelling decision rather than a defect to correct:

- a fixed seed, so the figure is stable and reproducible;
- or a stated range rather than a point, so the uncertainty is disclosed rather than
  hidden behind whichever run happened to be cached;
- or a documented cache lifetime, so at least the figure is stable for a stated period.

## Acceptance

- [ ] A decision is taken and recorded on which of the three (or another) is right.
- [ ] Whatever is chosen, **the per-login invariant survives** — both spouses must
      continue to see the same household figure. W-0188 must not reopen.
- [ ] If a range or an uncertainty is shown, it obeys Rule 12: **no score, no rating** —
      a currency range or a percentile stated in plain words.
- [ ] The chosen behaviour is pinned by a test that would fail if the figure became
      cache-dependent again.

## Working notes

(append-only)

- 2026-08-22 team-lead: raised by me, not by the tester, and the tester's judgement not to
  raise it was correct on the rule I gave it — per-login agreement was the invariant, and
  it held. **It flagged the magnitude anyway, which is why this exists.** Its words:
  *"a headline projected-tax figure that moves by £305,727 depending on whether a cache is
  warm is a real question about which number a user is entitled to see."*
- 2026-08-22 team-lead: **read alongside W-0217.** That item establishes the investment
  model produces a **higher** return from the **lower** risk preference, across the whole
  distribution. If the model itself is wrong, this variance may be a symptom of it rather
  than ordinary Monte Carlo dispersion — **settle W-0217 first**, because a fixed seed
  over a wrong model would make a wrong number stable rather than making it right.
