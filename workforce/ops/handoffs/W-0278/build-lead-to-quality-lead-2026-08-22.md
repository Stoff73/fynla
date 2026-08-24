# W-0278 — build-lead (`fix-cycle4-lifecover`) → quality-lead

Companion note: `handoffs/W-0341/build-lead-to-quality-lead-2026-08-22.md`, which carries
the shared context (test-design traps, assumptions, surfaces). This note covers only what
is specific to the disclosure bug.

## Done

- One private gate, `LifeCoverReach::coveringSpouse()`, composed from `User::liveSpouse()`
  and `User::hasReciprocalSpouseLink()`. `policiesCovering()`, `householdCoverInTrust()`
  and `otherLifeAssured()` all pass through it — one home, three consumers.
- Acceptance criterion 2 asserted directly: deleting the partner drops the household
  **amount and the count together** (£500,000/1 with `spouse_amount` 0.0). Mutation M3
  confirms it reddens when the gate is removed.
- **Severity raised `medium` → `high`.** This discloses another person's financial
  position; same class as `LiabilityCard.vue` earlier this cycle.
- A lazy-loading hazard closed in passing: `otherLifeAssured()` reached for
  `$viewer->spouse` under `preventLazyLoading()`. **Not a live crash** — its only call
  site passes `$request->user()`, which is loaded singly — but it would throw the first
  time a caller passed a user loaded as part of a collection.

## Not done, and why

- **State 4 — a refused or never-accepted `SpousePermission` — still reaches.** Decided,
  not overlooked; the three reasons are in `F-0027` §4 and the decision is CSJ's to
  confirm via **W-0345**. Two tests pin the current answer so a reversal lands visibly.
- **"Revoked" could not be tested because it does not exist.**
  `spouse_permissions.status` is `enum('pending','accepted','rejected')` — once
  `accepted` is written there is no value meaning withdrawn. Raised as **W-0346**,
  medium, routed to compliance-lead. The test asserts `rejected` and `pending` instead
  and says so in the file.
- Browser verification outstanding — see the companion note.

## What you need that isn't obvious from the artefacts

- **A third bad state was found that nobody had raised, and it is the more dangerous
  one: a link claimed from ONE SIDE ONLY was also disclosing.** `spouse_id` is written
  unilaterally by its own account holder, so any user could name another account as
  their spouse and read back that person's joint-life sum assured — no deletion needed,
  just an unreciprocated write. If you are checking one thing in this batch, check that
  case (`it does not disclose a policy on a link claimed from one side only`).
- **The persona exercises none of the three bad states.** No deleted spouse, no one-sided
  link, no permission row of any status. Every branch is constructed in the test file; a
  fixture built from `peak_earners` alone cannot reach them.
- `hasAcceptedSpousePermission()` looks like the natural gate and is not: it returns true
  for any married reciprocal pair *without reading the permission row at all*. Anyone
  "tightening" this later by reaching for it will change nothing for state 4 and will
  silently break linked unmarried couples.

## Assumptions I made

(stated as assumptions, never as facts)

- That blocking one-sided links is in the spirit of W-0278 rather than scope creep. It
  is a strictly stronger gate than the item asked for, and it costs one extra `exists()`
  query per call on the protection read path.
- That naming nobody as the second life (rather than hiding the policy's joint-life
  nature) is right once the link dies. The policy stays joint-life on the owner's
  account, because it is still the contract he bought — only the name disappears.

## Surfaces covered / not covered

As the companion note: backend-only, one endpoint per surface, no rebuild required, no
rendered UI verified on any surface yet.
