---
type: handover
mode: session-end
date: 2026-09-04
session: 1
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-09-04, Session 1

## Where things stand

The four items the previous handover left with unverified visual acceptance are
now **actually verified in a browser on csjones** — and the verification found
three defects, two of them introduced by this session's own wiring. Everything
found was fixed, re-verified live, and merged to `dev` through PRs #760–#765.

Then CSJ pushed back on five board items I had *raised* rather than fixed. Four
are now fixed and committed (W-0532, W-0533, W-0534, and W-0535 answered). The
fifth, **W-0540 — 79 dead Vue components — is the one live piece of unfinished
work**: the guard is written and the deletion list is verified, but the bulk
`git rm` was **blocked by the permission classifier** and needs CSJ's approval.

## Priorities for the next session

1. **W-0540 — delete the 79 orphaned components. BLOCKED ON CSJ: approval for a
   bulk `git rm`.**
   Everything else is done. The list is at
   `/private/tmp/claude-501/.../scratchpad/dead.txt` (regenerate by running the
   guard — it prints the list on failure). The command that was blocked:
   `xargs git rm -q < dead.txt`. If CSJ prefers reviewable chunks, do it
   directory by directory: `Investment/` (20), `Dashboard/` (15), `Goals/` (8),
   `UserProfile/` (6), `Risk/` (3), then singles.
   **One file needs handling, not blind deletion:** `Public/CalculatorCard.vue`
   is pinned by `tests/Feature/Public/FreemiumCopyContractTest.php:25`, which
   asserts it EXISTS as part of a historical remediation inventory. Deleting it
   turns that test red. Move its entry to `'deleted'` and adjust the counts in
   the same file (34 changed → 33, 3 deleted → 4), with a comment noting it was
   `changed` during that remediation and deleted later by W-0540 — otherwise the
   record is falsified.
   After the sweep: guard goes green, run the full frontend suite AND a
   production build, because a deleted component that something imports fails at
   build time rather than test time.

2. **The Rule 15 lint and the grandfather rule — a question for CSJ.**
   *CSJ asked for this to be in the handover explicitly.* The stop hook's
   design-system lint fired on two files touched today and named four emoji
   hits. **All four are pre-existing and none are in today's diff hunks** —
   verified with `git blame`:
   | Hit | Introduced |
   |---|---|
   | `resources/js/components/Estate/IHTPlanning.vue:747` `✓` | `8c58e2f446`, 2026-01-14 |
   | `resources/js/components/Estate/IHTPlanning.vue:1938` `❌` | `ade16f97bf`, 2026-03-30 |
   | `resources/js/components/NetWorth/Property/PropertyForm.vue:470` `⚠️` | `43a04bad4c`, 2025-11-15 |
   | `resources/js/components/NetWorth/Property/PropertyForm.vue:645` `⚠️` | `43a04bad4c`, 2025-11-15 |
   Today's hunks were `IHTPlanning.vue` 620–624 and 1113–1116, and
   `PropertyForm.vue` 235–241, 268–271, 274–279, 1307–1334. None overlaps.
   **Rule 15 is forward-only** — "Existing violations are grandfathered — don't
   rip them out or flag them in audits" — so nothing was owed and nothing was
   changed. **The decision CSJ needs to make:** the lint currently keys on
   *changed files*, not *changed lines*, so every future session that touches
   either file will be stopped by the same four hits and have to re-prove the
   grandfathering. Either (a) narrow the lint to lines within the diff, (b) keep
   an explicit grandfather allowlist the lint reads, or (c) clear the four hits
   as a one-off so the noise stops. **(a) is the recommendation** — it makes the
   lint match the rule as written, rather than encoding a list that itself rots.
   Note (c) contradicts "don't rip them out", so it needs CSJ to say so directly.

3. **W-0535 — CoordinatingAgent. Awaiting one word from CSJ.**
   CSJ asked "w-0535 is not shown anywhere?" and the answer given was: correct,
   it has no user-facing surface — it is internal code health, on the board at
   `tasks.md:175`. It grew again today, 6,768 → **6,785 lines**, from the two
   capability guards. If CSJ meant it should not be on the board at all, close
   it. Otherwise it stays plan-first: a seam decided against the
   `fyn-architecture` contract before anything moves, or Fyn handlers end up in
   two homes and Rule 20 breaks.

4. **Get `31c2bc4ad` onto `dev`.** The branch is pushed; nothing is on `dev` yet.
   Per CSJ's standing rule — *"always pr to dev and merge, so we are always
   testing on dev"* — W-0532/0533/0534 should go now. **`960f23308` must NOT**:
   the W-0540 guard is red by construction until the sweep in priority 1 lands.
   Either PR `31c2bc4ad` alone, or do the sweep first and take all three
   together. Note these are backend + frontend changes, so csjones needs a
   `public/build/` rebuild and upload after the merge, not just a `git pull`.

5. **W-0539 — `/m` has no trusts surface.** Deferred by CSJ today; no action
   wanted. Listed only so it is not rediscovered as a gap.

## Context to load

- `tasks.md` — the board. 12 outstanding: 6 `deferred-ios`, 1 `deferred`
  (W-0539), 5 `queued`. The counts line at the top was recomputed today.
- `workforce/ops/board/W-0540-a-component-can-lose-its-last-importer-and-nothing-fails.md`
  — carries the verified detector method and why the allowlist option was
  withdrawn. Read before touching the sweep.
- `tests/Architecture/EveryComponentIsRenderedSomewhereTest.php` — the guard.
  Running it prints the current orphan list, so it replaces the scratchpad file.
- `docs/tech-debt-report.md` — today's audit. 0 critical, 2 warnings, 2
  suggestions, plus a "Clean" section recording what was checked and found fine.
- `.claude/skills/board-loop/SKILL.md` — CSJ treats this as law and stopped a
  session over it. Announce steps by number; step 6 (`systematic-debugging`)
  applies to every live bug.
- `workforce/ops/board/W-0532-*.md` through `W-0538-*.md` — the day's items, each
  carrying its own outcome section.

## Completed this session

**Browser verification on csjones — the previous handover's whole first priority:**
- **W-0504** — `/m` rings verified by reading the rendered arcs from the DOM, not
  eyeballing: net worth **277.2°** = 77% captioned `Equity`, protection a full
  **360°**, investment **39.6°** = 11%. Each matches the household's own ratio
  computed independently from the displayed figures. Two different correct ratios
  from one mechanism rules out a replacement constant (the old one was 72).
- **W-0034** — `/m` Health and lifestyle: changed all three fields, saved,
  reloaded, confirmed in the database (`yes_previous`, `quit_long_ago`,
  `postgraduate`). No 500, so the W-0006 trap has not returned.
- **W-0500** — the spouse question asked, answered and stored.
- **W-0045** — trusts palette confirmed on three of four surfaces from **computed
  styles read off the live page**: badges `light-blue-100`/`horizon-500` beside
  `spring-100`/`spring-700`, `flex-wrap: wrap` with no overflow at 1440/700/420,
  the Inheritance Tax Charges block on `light-blue-100`, and the Tax Implications
  card on a 1px `light-blue-500` border. The fourth surface did not exist.

**Defects found by that verification, all fixed:**
- **W-0536** (`eed47e7ce`, high) — **every partial PUT to a property converted it
  to sole ownership.** Answering the `/m` spouse question took `ownership_type`
  joint → individual and `ownership_percentage` 50 → 100: a £180,000 house going
  from a £90,000 share to £180,000, into net worth and the estate.
  `PropertyController::update():270` resolved the effective type from the stored
  record and never wrote it back, so `PropertyNormaliser::fromForm():49` injected
  its own `'individual'` default. The existing test drove exactly this request and
  asserted only its own column. Re-verified live after deploy.
- **W-0537** (`1cdd4b79d`) — `PremiumEntitlementResolver` returned `free()` for
  **every** preview user before any provider resolution, so no demo could ever
  show the paid product. Per CSJ, David & Sarah Mitchell (`peak_earners`) resolve
  premium always; the rest stay free. `users.tier` is read **only** inside the
  preview branch, so W-0018 still holds for real users — pinned by a test.
- **W-0538** (`4ecc1a2f2`, `08ec4f0e6`) — `TrustsOverviewCard.vue` had no
  importers, so W-0045's palette fix to it reached no screen. Wired into the web
  dashboard per CSJ (web only, not `/m`). Live verification then found it **403'd
  twice per load and told the user "No trusts set up" while they held a trust** —
  fixed by gating on the predicate the endpoint actually enforces, rendering one
  instance, and distinguishing a failed load from an empty one.

**CSJ's five-item push-back, four fixed (`31c2bc4ad`):**
- **W-0532** — `family_module` and `benefits_child` were in the pricing comparison
  and enforced nowhere. `TeaserGate::requireCapability()` is the throwing form of
  `allows()`, raising the same exception every controller and Fyn already catch.
  `family_module` is gated on **all three** write paths — controller, Fyn's
  `create_family_member`, Fyn's onboarding dependants create. `benefits_child` is
  gated inside `ChildBenefitService` where the position is produced. Refusal
  wording derives from the pricing page's own label, so advert and refusal cannot
  diverge. Both off the allowlist.
- **W-0533** — the leasehold config was not orphaned, it was **copied three
  times**: the literal `80` in `PropertyCalculationService`, both bands as prose
  in its docblock, and "less than 80/60 years" in `PropertyForm.vue`. All read
  configuration now; the bands are published on the property and rendered on web
  **and `/m`** — a 62-year lease previously produced silence everywhere. The
  tenure select also hardcoded "Freehold"/"Leasehold" over a configured version,
  so `tenure_types` crosses the snapshot too. **`property_ownership` is now a
  `GUARDED_AREA`** in `ConfiguredRulesHaveConsumersTest` with no orphans left.
- **W-0534** — the current column's pension-exclusion sentence was true and
  written inside a component behind the upgrade gate, so the free teaser printed
  a figure computed *with* that exclusion and could not say so. The engine
  publishes it; the component renders the published string; teaser detector
  carries it to web and `/m`. The date comes from configuration.
- **W-0535** — answered, not code: no user-facing surface.

**Also:** four findings raised as W-0532–W-0535; W-0539 and W-0540 raised;
the consistency sweep's "watch for a rise" rule given a measured baseline
(`9f8ca9e7c`) — it had none, so nothing could be compared to anything.

## Verification state

At `31c2bc4ad` / `960f23308`:

| Gate | Result |
|---|---|
| Tiers | **127 passed** |
| Property / Tax / Stores / Estate | **502 passed**, 1 contention-only |
| Estate / Unit Tiers | **273 passed**, 1 contention-only |
| Frontend (vitest) | **822 passed, 73 files** |
| Property + Estate + Tiers new tests | 6 + 8 + 5 + 11 new, all green |
| `EveryComponentIsRenderedSomewhereTest` | **RED by design** — 79 orphans |

The one red in the combined PHP runs is `BequestsStateWhatTheyExcludeTest`, which
**passes standalone twice** and was last touched by W-0398, not today — DB
contention under a large combined run, not a regression.

**Not verified:**
- No full PHP suite. Targeted families only, per CLAUDE.md #17.
- The **"We couldn't load your trusts"** state was not reproduced in a browser and
  is not claimed as browser-verified. Reaching it now needs a user who passes
  `hasFullCapability('estate')` and is still refused by the endpoint, and the gate
  fix eliminates that combination. Its guard is the unit test.
- The `.badge.inactive` chip on `/trusts` was not seen — no inactive trust exists
  in the data. Its rule was read at `TrustCard.vue:248-251` instead.
- Nothing from `31c2bc4ad` or `960f23308` has been near a browser.

## Decisions and dead ends

- **CSJ: "always pr to dev and merge, so we are always testing on dev."** A
  feature branch on csjones is a verification step, never the resting state.
  Saved to memory as `feedback_always_pr_to_dev_and_merge`.
- **CSJ: David & Sarah Mitchell premium always, the rest of the personas free.**
  The comparison is the point — a visitor can see both.
- **CSJ: trusts on `/m` deferred** ("we will get back to it").
- **CSJ, on the five raised items: "why are you bothering me with this?"** The
  correction taken: 79 dead components is a defect, not a decision to put to
  anyone. The allowlist option offered on W-0540 was **withdrawn** — it would have
  recorded 15% of the component tree as permanently acceptable.
- **The wide-haystack orphan scan was WRONG and was rejected.** A second scan over
  `resources tests public/pages routes app` reported 72 rather than 79. Its seven
  extra "references" are **substring collisions with unrelated backend
  identifiers**: `calculateHouseholdNetWorth()` and `generateSpousalOptimisations()`
  are service methods, and `AssetLocationOptimizer`, `PerformanceAttribution` and
  `RebalancingCalculator` are PHP classes sharing a name with a dead `.vue`. The
  narrower frontend-only haystack is correct. *Verify the instrument before
  trusting the measurement* — this board has paid for that lesson before.
- **An earlier count of 32 was wrong** — the file was read while the scan was
  still writing it. 79 is the number.
- **`hasCapability` was the wrong gate and is not the same question as
  `hasFullCapability`.** The first mirrors `TeaserGate::allows()` (admin and
  preview bypass); the second mirrors `isFull()` (no bypass). Estate sub-routes go
  through `EnsureFullEstateAccess`, which calls `isFull()`. A screen must gate on
  whichever one the endpoint behind it enforces. Both getters now exist and the
  old one is untouched.
- **A component that never rendered had never had its failure path looked at
  either.** `TrustsOverviewCard` swallowed its error and read an empty list as
  "you have no trusts". Worth carrying forward to the other 79.

## Things that will bite you

- **The lint keys on changed files, not changed lines** — see priority 2. Touching
  `IHTPlanning.vue` or `PropertyForm.vue` will stop you with the same four
  grandfathered hits until CSJ decides.
- **`./vendor/bin/pint` on a whole directory times out** at two minutes and killed
  a compound command mid-chain today. Format named files only — and it is PHP-only,
  so it does nothing for `.vue`/`.js` anyway.
- **`ssh-add ~/.ssh/fynlaDev` is needed at the start of a session.** The key is
  passphrase-protected and is not loaded automatically; csjones SSH returns
  `Permission denied (publickey)` until it is.
- **rsync to csjones needs `$HOME`, not `~`, inside the quoted `-e`** — `~` is not
  expanded there and the transfer fails with `Can't open user config file`.
- **The playwright MCP failed to connect** (30s timeout). The Chrome extension
  tools were used instead and worked for everything, including reading computed
  styles and network status codes.
- **A hard browser navigation loses the SPA session.** Navigate within the SPA by
  clicking nav links; `navigate` to a deep route logs you out.
- **`/m` and the web hold separate token stores.** Signing out of one does not sign
  out of the other, and each needs its own MFA cycle.
- **DB contention gives false reds.** Running five PHP suites in one command
  produced a failure that passes standalone twice. Re-run the file alone before
  believing it.
- **Property 8 on csjones was used as the W-0536 fixture** — `joint`, 50%,
  co-owner "wife", and `joint_owner_is_spouse` now genuinely `true`. It was
  restored with `saveQuietly()` after the defect corrupted it.

## Tech debt deferred

Full report at `docs/tech-debt-report.md` — 0 critical, 2 warnings, 2 suggestions.

- `tests/Architecture/EveryComponentIsRenderedSomewhereTest.php` — **red by
  design** until the sweep. Must not reach `dev` before it.
- `app/Agents/CoordinatingAgent.php` — **6,785 lines**, up from 6,768 this
  morning. That is W-0535 and it is plan-first; nothing opportunistic.
- Four grandfathered Rule 15 hits (priority 2).
- `app/Services/Estate/IHTCalculationService.php` — 2,723 lines. Watch, do not
  split without a decided seam.

## Branch and deploy state

- Branch: `chore/board-verification-31-august`
- **Unpushed commits: none** — the branch is pushed. But **two commits are NOT on
  `dev`** and one must stay off it: `31c2bc4ad` (W-0532/0533/0534) is mergeable
  now; `960f23308` (the W-0540 guard) is **red by construction** until the sweep
  and must not go to `dev` before it. Open the PR for `31c2bc4ad` alone, or wait
  and take both together after the deletion.
- **`dev`**: at `41771cca0`, carrying PRs #760–#765
- **csjones**: on `dev` at `41771cca0`, backend pulled and `public/build/` rebuilt
  and uploaded; caches cleared with `route:clear`/`cache:clear`/`view:clear` then
  `config:cache` only. Neither forbidden caching command was run.
- **production**: untouched. Nothing from today is on fynla.org.
