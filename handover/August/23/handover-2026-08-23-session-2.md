---
type: handover
mode: session-end
date: 2026-08-23
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-08-23, Session 2

## Where things stand

**Everything is committed, pushed, and deployed to dev (csjones.co/fynla) at `5c556e252`.**
Tree clean, nothing unpushed. Both full suites green — **7,878 backend / 1,237 frontend, zero
failures** — and that is the first uncontaminated full-suite run in two days, for a reason that
matters (see Verification state).

The day was: fix the spouse-linking CRITICAL, fix W-0154 and W-0463, run
`tax-compliance-reviewer`, fix everything it rejected, deploy. All of that landed. **What did
NOT happen: `quality-lead` never ran.** 145 board items still sit at `handoff`, uncertified,
and they went to dev anyway on CSJ's instruction ("all changes to dev").

**Read the tax verdict before touching estate code.** Two review rounds, 26 findings, at
`workforce/ops/handoffs/W-0463/tax-compliance-reviewer-verdict-2026-08-23.md`. It nearly did
not exist — the reviewer wrote nothing to disk and both reviews lived only in the session
transcript until 19:20.

## Priorities for the next session

### 1. BLOCKED ON CSJ — five items, two of them new and both pure copy

Ask these first. Engineering cannot start without the words.

| Item | What is needed |
|---|---|
| **W-0466** | Caveat wording for an estate holding farmland or AIM shares. We model **neither** Agricultural Property Relief nor AIM treatment, and the two errors run in **opposite directions** — APR absence overstates tax by up to ~40% of land value; AIM recorded as a business interest understates it. The requirement is settled by the reviewer; the words are CSJ's. |
| **W-0467** | The `/m` Free teaser says *"your estate could be subject to up to £X"* where the figure is a **pooled second-death household** number. That user's own first-death liability is typically £0. It is the ONLY Inheritance Tax figure `/m` ever shows, on a conversion surface. |
| **W-0340** | Unmarried linked couple — headline taxes one estate, projection pools two. |
| **W-0392** | `is_iht_exempt` removes business relief assets from the **estate** rather than the **tax**. |
| **W-0350** | The 53 `spouse_id` consumers census. No longer blocked by W-0347 — that block is lifted. |

### 2. W-0469 — `/m` never got the business relief row or the failed-gift tax

Rule 19. Conditions C3 and C5 were discharged on **web only**. Taper relief on failed gifts is
the thing CSJ called critical and on `/m` it remains invisible.

**This is a decision, not a port.** `/m`'s estate screen has **no allowance breakdown at all**,
and in Premium mode no Inheritance Tax liability either. So the question is what that screen is
for — full breakdown, or an honest summary that hands off to web. Either is defensible; leaving
it undecided is not.

### 3. Run `quality-lead`

It never ran. 145 items at `handoff`. Before certifying any of them, read the note at the foot
of `workforce/ops/queue/cycle4-fix-queue.md` — `status: handoff` is a moment-in-time claim.
**And know this**: `./vendor/bin/pest` was fatal from 2026-08-22 until `1af23f8e5` today, so
**nothing at `handoff` has a full-suite green behind it from before today**.

### 4. W-0465 — the projection applies no business relief at all

The two columns of the same table disagree by the whole relief. Fixing it **invalidates a
comment in `assessTaxPosition()` that is only accidentally true** — it says the projection is
"already relief-free", which is correct only because the projection is wrong. The taper base
must be revisited in the same change.

### 5. W-0468 (same-day transfers don't cumulate) and W-0461 (Rule 2 never entered the frontend)

## Context to load

- `workforce/ops/handoffs/W-0463/tax-compliance-reviewer-verdict-2026-08-23.md` — **the most
  important file here.** Two rounds, 26 findings, legislation and HMRC citations, the stated
  assumptions, and what the reviewer could not verify. Read before any estate work.
- `workforce/ops/reports/deploy-2026-08-23-dev.md` — what went to dev, what was verified, and
  the two facts a future reader would otherwise never learn.
- `workforce/ops/queue/cycle4-fix-queue.md` — cycle-4 traps, non-regression baselines, the
  certification warning, and the persona-tester restart brief.
- `app/Services/Estate/FailedGiftTaxCalculator.php` — new; the one home for gift tax and taper.
  Its docblocks carry the law and the two stated assumptions.
- `tests/Feature/Tax/ConfiguredRulesHaveConsumersTest.php` — the coverage guard, and its
  docblock explains why the move-the-value guards structurally could not catch W-0463.
- `workforce/ops/fix-queue-page/README.md` — how to refresh the board page CSJ reads.

## Completed this session

- **`70c5014da` — the spouse-linking CRITICAL.** One POST plus a victim's email wrote their
  `users` row, forged `accepted` on both permission rows and returned their whole profile. Now
  an invitation. **The whole consent flow already existed and had never been reachable** — the
  UI component was mounted nowhere and the notification email linked to a route that did not
  exist, so the backend forged consent. All 10 permission rows on dev were forged.
- **`0385fe6cc` — W-0154 and the W-0463 coverage guard.** One household one answer; allowances
  that reconcile; no rate on a nil bill. The guard found the three orphaned rules independently
  and goes red under mutation.
- **`6302cd661` — taper relief on failed gifts, and `/m` stopped calculating.**
- **`1af23f8e5` — the full suite could not run at all** since 2026-08-22: two files declaring a
  global `spouseRow()`. Fatal at collection; not one test ran.
- **`a1d36b90b` — nine blocking tax findings.** F1 (residence band capped before the taper —
  statute is the reverse), F2, F6, F7, F8, F9, F10, F15, F19.
- **`33966d9e0` — C3/C4/C5.** Four independent hardcoded taper schedules deleted from the
  frontend, all deciding relief from the gift's age alone.
- **`19bd1c83f` — R1-R5, two HIGH defects I introduced while fixing.**
- **`5201f16eb`, `5c556e252`** — board records, five new items, the generator moved into the repo.
- **Deployed to dev**: 16 commits, 12 migrations, both bundles, caches cleared.

## Verification state

- Backend **7,878 passed / 30 skipped / 0 failed** (126,693 assertions) at `19bd1c83f`.
- Frontend **1,237 passed** (122 files).
- `tax-compliance-reviewer` — two rounds, verdict recorded.
- Browser-verified: estate allowance table reconciles on screen, both columns; spouse invite →
  accept → revoke end to end on both accounts.
- Deployed bundles grepped for strings only today's changes could produce.

**NOT verified:**
- **`quality-lead` never ran.**
- **iOS: not built, not launched, not looked at.**
- The `/m` Free teaser could not be reached in a browser — Free-tier surface, personas are Premium.
- No fixture can exercise the relief cap, taper relief, or a gift over seven years old. **Every
  worked example in the review is hand-computed, not observed.** That is why all of it survived
  a persona run.

## Decisions and dead ends

**CSJ decisions — do not re-litigate:**
- **`/m` must NEVER work anything out.** It renders what the backend computed. Four violations
  fixed; the remaining arithmetic is progress bars and a sparkline scale, named in W-0464.
- **All changes to dev**, including the two `wip:` snapshots.
- The tax config is the source; every estate and tax service must call it.

**Settled by the reviewer — do not re-derive:**
- **s7(5) and the IHTM14576 credit are ONE rule.** s7(4) is the only route to the death rate and
  works by disapplying the half-rate s7(2), so switching it off drops back to the lifetime
  charge. `additional = max(0, tapered − lifetime)`, nothing repayable.
- **`rnrb_transferred` = 0 for a living couple is CORRECT** (s8G). Do NOT "fix" it by writing
  £175,000 into it. Same for the nil rate band under s8A.
- **The £2.5m relief cap is right** (FA 2026 Sch 12 / s124D(2)). The reviewer arrived expecting
  £1m and corrected itself.
- **Relief allocation is pro rata** (s124D(7)), not largest-first. Total relief is invariant to
  order — the "relieves the most" rationale was false.
- **`suppressRateOnNilLiability` is legally required**, not cosmetic — Sch 1A para 1(1)(b) means
  the Schedule is not engaged at all when the estate is covered.

**Dead ends:**
- **Agricultural Property Relief cannot be implemented as the schema stands** — no agricultural
  asset type exists. Same for AIM shares. Both registered; do not attempt without a schema change.
- The `workforce/registry/` "missing directory" is a **myth** — it is at `workforce/core/registry/`
  and always was. W-0415 closed invalid.

## Things that will bite you

- **ONE Pest process at a time.** Concurrent runs share `laravel_testing` and one drops tables
  under the other. This produced "232 failures" and then "61" today, both of which I reported to
  CSJ as real before finding the cause. Three files from that list passed cleanly in isolation.
  **Re-run in isolation before believing any red.**
- **The published board page is a SNAPSHOT.** It went stale twice today. Rebuild AND republish in
  the same breath as any board change: `python3 workforce/ops/fix-queue-page/build.py --stamp "..."`.
- **SSH to csjones needs `ssh-add ~/.ssh/fynlaDev`** (passphrase-protected). The symptom is
  `Server accepts key` then `Permission denied` — which looks exactly like an IP allowlist and
  is not. I misdiagnosed it as SiteGround allowlisting twice.
- **`EstateAgent::analyze()` caches under a key the invalidator never clears** (W-0381). Clear
  `estate_analysis_{id}` by hand before every estate reading.
- **The `tax-compliance-reviewer` agent definition is stale** — 2025/26, "frozen until April
  2028", no relief cap. The active config is 2026/27, frozen to 2031. It grepped the live config
  instead of trusting its table; the next one may not. **Worth refreshing.**
- Persona passwords: `Password1!`, one attempt — shared lockout.

## Tech debt deferred

Scanned today's 40 changed code files: **no hardcoded tax literals**, and every method added has
callers. Nothing new to report. Outstanding from earlier:

- `formatAssetType` has eleven copies across four directories (W-0443).
- `rent` and `utilities` never persist from the expenditure form.
- `months_remaining` is a phantom key — the goals plan runs on 12 months.
- Pre-existing `$set` deprecation at `GiftingStrategy.vue:735` — untouched, forward-only.

## Branch and deploy state

- **Branch: `dev`.** Tree clean, nothing unpushed. Note this session ended ON dev, not a
  working branch — start the next round by branching off it.
- **Deployed: csjones.co/fynla at `5c556e252`.** 12 migrations run. Both bundles uploaded with
  old chunks preserved. Caches cleared, config re-cached only.
- **Prod: untouched.** Nothing has gone to `main`.
