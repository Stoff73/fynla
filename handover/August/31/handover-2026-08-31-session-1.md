---
type: handover
mode: session-end
date: 2026-08-31
session: 1
repo: fynla
branch: chore/board-verification-31-august
---

# Session Handover — 2026-08-31, Session 1

## Where things stand

**The board now has a number that survives "show me one".** 327 items, 195 resolved, 132
outstanding. Every **high and critical** item on the board has been opened and verified
against the code this session — 81 items read, **65 closed with the code that replaced the
defect cited at file:line, 16 confirmed still live with the defect code quoted at its current
line.** The 116 items nobody has opened are **all medium or low. Zero high, zero critical.**

Five defects were also fixed, tested and mutation-verified: W-0227, W-0157, W-0226, W-0264,
W-0222. **All work is on a local branch that has never been pushed** — see Branch and deploy
state before doing anything else.

**The dominant finding is not new work. It is that the backlog was mostly finished work nobody
restamped.** Twelve items carried "FIXED" in their own working notes from 21 August and sat at
`gated` ever since; six more merged in the previous session's own PRs.

## Priorities for the next session

1. **Open the PR. BLOCKED ON CSJ — it is his call whether this goes to `dev` as one PR or
   several.** The branch **is now pushed** (`origin/chore/board-verification-31-august`, 13
   commits), so nothing is at risk, but no PR exists. The 5 code fixes and the ~70 board
   restamps could reasonably be split; CSJ has not been asked which he wants.

2. **The rate-literal set — W-0432 + W-0461, now converged on ONE remaining set.** Both were
   re-measured today. W-0432 is six-of-eight fixed with **two survivors**, and both survivors
   are W-0461's. Do these together; do not work them as separate items.
   - `IHTPlanning.vue:621` — *"the Home Allowance (up to £175,000)"*. **Named by two verdicts,
     survived four batches**, and sits three lines below a sentence W-0432 already made
     configuration-driven.
   - `TaxSettingsController:330` — `sprintf('%g%% (if 10%%+ to charity)', ((float) ($iht['reduced_rate'] ?? 0.36)) * 100)`.
     Two literals in one line, inside the admin screen that displays the tax settings.
     `grep -rn '?? 0.36' app/` returns exactly one non-comment hit and this is it.
   - **W-0461's criterion 1 IS the item, and it is not met: "a guard that moves a configured
     rate and asserts on a rendered Vue template."** Every existing guard drives PHP and asserts
     on service output, so re-hardcoding any of the nine instances leaves the whole suite green.
     The nine fixes are the easy half.
   - W-0461's instance 6 has **grown**: `EstateOverviewCard.vue` now carries
     `futureTaxableEstate * 0.40` at **:158 AND :169**.

3. **W-0508 — civil partnership, and it is the half-fixed shape.** `WillController:80`,
   `GiftingController:81` and `:272`, `TrustController:187` still read `['married']` alone.
   **`TrustController` is the sharp one:** `:207` resolves the spouse through
   `HouseholdPooling::hasSpousalStatus()` (W-0480 F2, reasoning in the comment at :203) while
   `:187`, twenty lines above in the same method, still uses `['married']` for the default
   `nrb_transferred_from_spouse`. A civil partner gets the corrected calculation and a
   single-person default profile **in one request**. A reader asking "does this file use the
   canonical rule?" answers yes.

4. **The pension-in-estate family — W-0513, W-0514, W-0515.** One subsystem, three items,
   all verified live today. W-0515 is the sharpest: `IHTCalculationService:2382` still tells the
   user *"pensions pass outside the estate"* and `:2386` still publishes **today's pot** as
   `pension_value_included` — the exact figure W-0482 exists to reject, so one household carries
   two different pension-in-estate numbers.

5. **W-0495** — `EmergencyFundCalculator:14-16` returns `0.0` for both "no runway" and "cannot
   be calculated", so a household with cash and no recorded expenditure is told 0 months. Small,
   self-contained, and the error runs in the alarming direction.

6. **W-0463 — work SEVEN, not twenty.** Re-measured today with the method stated on the item:
   12 accessors have zero callers, but only 7 are genuinely unwired capabilities. Three are dead
   *accessors* whose capability is wired elsewhere (blind person's allowance via
   `blindPersonsAllowanceFor()`; the fourteen-year rule is implemented, from the CLT config
   block — that is W-0526, a consolidation); agricultural relief is CSJ's recorded deferral
   (W-0524). **Do not re-open the agricultural design decision.**

7. **The 116 unopened mediums and lows.** Same method as today: open the item, find the defect
   in the code, record FIXED with evidence or LIVE with file:line. Nothing inferred from
   citations.

## Context to load

- `workforce/ops/reports/2026-08-30-board-evidence-audit.md` — the audit method and, more
  importantly, **its limits**. Read the limits section before producing any board figure.
- `workforce/ops/board/W-0461-the-rule-2-sweeps-never-entered-the-estate-frontend.md` — priority
  2. The 2026-08-31 note lists all nine instances at their current lines and states which
  acceptance criterion is actually the item.
- `workforce/ops/board/W-0432-rate-literals-survive-in-user-facing-strings-across-the-estate-services.md`
  — the other half of priority 2; its 2026-08-31 note names the six fixed and the two survivors.
- `workforce/ops/board/W-0463-tax-config-is-the-source-every-estate-and-tax-service-must-call-it.md`
  — priority 6, and the board's counter-example proving a test citation is not a verification.
- `app/Services/Protection/CoverageGapAnalyzer.php` — `debtProtectionBasis()` is the pattern the
  next consolidation should copy: return the total, the components, and which source produced it.
- `docs/tech-debt-report.md` — three warnings from this session, all real, none fixed.
- `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php` — the persona figures are locked here.
  Household £1,728,780, bill £343,512. **If either moves, something upstream broke.**

## Completed this session

**Merged to `dev` first thing** (both were green and waiting): **#758** (W-0340) and **#757**
(board reconciliation + the evidence audit + CSJ's amended W-0228 ruling).

**Board verification — 81 items opened, 65 closed.** Twelve commits, `c18af889e`..`80107b1c3`.
Every closure cites the code that replaced the defect. **Nothing was closed on a citation** —
that was the previous session's documented failure and the rule it left behind.

**Five defects fixed** (`80b1cef61`, `f18205d7d`):

- **W-0227** — the protection debt panel published `mortgage_balance £0, other_debts £0` as the
  inputs to a £182,500 need built from the records. `debtProtectionBasis()` now returns the
  total, its components and its source; records win where records exist, the profile summary
  demoted to a fallback. Live persona now reads **170500 + 0 = 170500** with the source named.
- **W-0157** — the signing step's "automatically void" corrected to name the Wills Act 1968
  amendment. **Browser-verified end to end.**
- **W-0226** — net worth charged the recorder 100% of every shared debt. Now reach from
  `forUserOrJoint()`, fraction from `calculateUserShare()`.
- **W-0264** — `PensionProjector` was the last reader on the raw `has_custom_risk` pair.
- **W-0222** — mechanism was already right; the missing acceptance test is now written.

**W-0491 closed by doing the one thing its author could not:** it sat at `review` since
2026-08-25 asking *"someone on the Mac should seed one violation and confirm a block."* Seeded a
probe and ran all three Stop hooks on macOS. All three fired, including the Rule 15 emoji check
the old `python3` implementation passed silently.

**W-0322 and W-0227 closed after CSJ corrected my framing.** I presented both as decisions he
owed me. Neither was: W-0322's open criteria were a rule he had already settled and the code
already implements, and W-0227's "should the override exist" was answerable by grepping — no
surface writes those columns.

## Verification state

- **Green at `f18205d7d`:** 753 protection+estate, 472 net worth/investment/protection/goals,
  179 retirement, 1,124 frontend (107 files), 9 `/m` parity.
- **Every one of the five fixes mutation-verified** — reverted, confirmed the test fails,
  restored. W-0264's mutation is the instructive one: restoring the raw flag pair turns **only**
  the legacy-row test red while the other two stay green, which is exactly why it survived three
  fixes.
- **W-0157 browser-verified** in Playwright: walked a throwaway premium account through the
  entire will wizard to the Signing step and read the sentence.
- **NOT verified in a browser: W-0226, W-0264, W-0222, and W-0227's `/m` rendering.** See below.
- **Pint:** the changed files pass. `pint --test` across the repo fails on ~60 pre-existing
  `public/pages/*` files — not this session's, and not to be swept up.
- Persona figures confirmed intact after the browser work: £1,728,780 / £343,512.

## Decisions and dead ends

**CSJ decisions this session — do not re-litigate:**

- **W-0322 is settled.** *"as per the rule if there are no holdings the account will show cash,
  with a zero, I thought this was clear?"* Unallocated value is Cash. The code implements it.
- **CSJ rejected all three questions I raised as decisions**, correctly. The lesson, recorded
  because it cost the morning: **check whether a question is answerable from the code before
  asking it.** W-0227's "should the override exist" was answered by one grep showing no surface
  writes those columns.
- **"Roughly" is not an answer.** CSJ: *"I do not want roughly, I want exactly."* Every board
  figure in this handover is computed from the files, and the `done` column reconciles exactly:
  120 (30 Aug TSV) + 3 (closed 30 Aug after it) + 65 (today) = 188 done, + 3 closed_invalid
  + 2 closed_duplicate + 2 deferred = 195 resolved.

**Settled by evidence:**

- **W-0157 was un-held from W-0153 deliberately**, against its own acceptance. W-0153 asks
  whether legal statements must carry a source; the "automatically" sentence is wrong on its own
  facts either way, and registry row **A14** already recorded that on 2026-08-21. Holding an
  incorrect statement of law behind a general policy question is cost with no benefit.
- **W-0264 needs no backfill.** The canonical reader ignores `has_custom_risk` entirely, so
  pre-normaliser rows are repaired by being read correctly rather than rewritten. **Do not write
  a migration for this.**
- **W-0139's criterion 1 was re-worded, not claimed as written.** "One number everywhere" is the
  WRONG target: the s23(1) exemption pools across both wills, the Schedule 1A 10% test is the
  survivor's will alone. Summing both would over-qualify households for the 36% rate.

**Dead ends:**

- **A test citation is not a verification.** Carried forward from yesterday and re-proved:
  W-0432, W-0461, W-0463 and W-0515 are all heavily cited in code and all still live.
- **A completion note is the thing that stops the next reader looking.** Hit three times today:
  `NetWorthService`'s docblock asserted a reciprocal-record model the schema contradicts;
  `TaxConfigService`'s `?? 0.36` note; `PensionProjector` reading as though it handled the
  override. **Correct the note in the same edit as the code, or the next survivor hides the
  same way.**
- **`grep 'dump('` matches `toArray()`.** Two minutes lost to a false positive; anchor with `\b`.

## Things that will bite you

- **`public/build/` and `public/m-build/` are BOTH the csjones build** (base `/fynla/`). A cold
  `localhost:8000` serves a blank page with MIME errors, and `/m/app/*` double-prefixes to
  `/fynla/m/app/m/app/...` and 404s. **This is why `/m` could not be browser-verified.**
  Rebuilding either overwrites what CSJ has staged — ask first.
- **Vite cannot use its canonical port.** `hermes-desktop` (electron-vite) owns **5173**, and
  Fynla's config is `strictPort`. **5174 works and 5176 does NOT** — the app's CSP allowlists
  only 5173/5174, and a Vite on any other port is silently blocked with a blank page and a CSP
  console error. I left one running on 5174 plus `php artisan serve` on 8000, and wrote
  `public/hot`. **`rm public/hot` and kill both when you want the machine back to normal.**
- **Persona passwords are `Password1!`, not `password`.** CLAUDE.md says `password` for
  `john@example.com`; `david.jones@example.com` (16) and `sarah.jones@example.com` (17) do not
  match it. Verify with `Hash::check()` before assuming a 401 is a bug.
- **The will Signing step is unreachable for a completed will** — that is W-0133, still live.
  To reach it you need a fresh premium account and a full wizard walk. The will builder is
  premium-gated, so a throwaway needs `plan`/`tier` = premium AND an active `Subscription` row.
- **The board directory mixes filename conventions** — early items are `W-0002.md`, later ones
  `W-0002-some-slug.md`. A glob for `W-0002-*.md` silently matches nothing. Every script in this
  session used `ls ${id}-*.md ${id}.md 2>/dev/null | head -1`.
- **The SPA stores its token in `localStorage['fynla-state'].auth.token`**, not `auth_token`.
  `credentials: 'include'` alone gets a 401 from `/api/*`.

## Tech debt deferred

Full report at `docs/tech-debt-report.md`. Three warnings, none critical, none fixed:

1. **Two mechanisms answer "what does this user owe"** —
   `NetWorthService:155` and `CrossModuleAssetAggregator:404`. W-0226 made them AGREE; they are
   still two implementations, with parity held by a test rather than by construction.
2. **The debt protection panel exists twice** — the canonical `protection_gap_v1` service that
   `/m` consumes, and the web `/protection` page's own component. This is why the W-0227 defect
   was live on one surface and absent on the other.
3. **`InvestmentController`'s write paths disagree** — create guards the auto-Cash row with
   `&& ! $hasCashHolding` (`:439`), update does not (`:587`). 70% equities + 20% Cash through
   update yields two Cash rows.

Carried forward, untouched: 52 unused private injections outside the TaxConfigService cluster;
`database/schema/mysql-schema.sql` is stale; the gifting UI still offers edit and delete on a
trust-owned gift.

## Branch and deploy state

- **Branch:** `chore/board-verification-31-august`, cut from `dev` after #757/#758 merged.
- **Tree:** clean.
- **Pushed** to `origin/chore/board-verification-31-august` at session end. 13 commits.
  **No PR opened — that is CSJ's call** (see priority 1).
- **`dev`** carries #750–#758. **`main`** unchanged; PR #736 still deliberately unmerged.
- **Nothing deployed this session.** csjones and production untouched. Migrations from 29 August
  (`add_trust_id_to_gifts_table`, `allow_estate_planning_in_user_assumptions_type`) are on `dev`
  and still not applied to either server.
