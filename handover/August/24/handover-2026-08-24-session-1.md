---
type: handover
mode: session-end
date: 2026-08-24
session: 1
repo: fynla
branch: estate-copy-and-m-handoff
---

# Session Handover — 2026-08-24, Session 1

## CSJ'S STANDING INSTRUCTION FOR THE NEXT SESSION — READ FIRST

**Clear the board. Alone. No agents. No time wasted.**

Verbatim intent, issued after this session wasted hours:

- **DO NOT SPAWN ANY AGENTS.** Not build-lead, not quality-lead, not reviewers, not
  a "quick" Explore. This session dispatched three fix agents in parallel, CSJ killed
  them, and told me to do it myself. **Every agent was killed at 11:53 BST and none
  are running.** Keep it that way.
- **One item at a time.** Fix it, test it, commit it, move to the next.
- **Fast, and without shortcuts.** Both. Do not gold-plate; do not skip verification.
- **All board issues resolved, tested and working.** That is the goal state.

**The one hard technical reason agents are banned here:** they share one MySQL test
database (`laravel_testing`). Two Pest processes = deadlocks and 0-assertion failures
that look exactly like real breakage and cost hours to disbelieve. **This session lost
time to that twice, once by its own hand.** Working alone removes the whole class.

## Where things stand

**Tree clean, nothing unpushed, 20 commits landed on `estate-copy-and-m-handoff`
(branched off `dev`).** The branch has NOT been merged or deployed anywhere.

The day divided in two. The first part fixed the five `quality-lead` rejections from
2026-08-23 and ran three review gates over the work; those gates found **real defects
inside my own fixes**, repeatedly, and all of those are now corrected. The second part
was supposed to clear the board and barely started: **three items landed** before CSJ
stopped the agent approach.

**215 board items are still open** (3 critical, 80 high, 109 medium, 23 low). That is
the work. 105 `queued`, 104 `gated`, 4 `blocked`, 3 `handoff`.

## Priorities for the next session

**Work these alone, one at a time, in this order.** Every one below is diagnosed to
file:line in its board item — read the item, do what Acceptance says, do not re-derive.

1. **W-0473 — the entire `/m` Insights feature is dead.** I was mid-fix when the
   session ended; nothing is changed yet. All six readers look one level above where
   the agent puts its data: they read `$estate['iht_liability']` where the payload is
   `$estate['data']['summary']['iht_liability']`. Every agent returns
   `success/message/data/timestamp` (`BaseAgent::response()`), so **every branch is
   skipped and the endpoint returns `[]`**. Measured on user 14, not inferred.
   **Fix by unwrapping ONCE at the call site** (`InsightsController::extractInsights`),
   not six times, so a seventh module cannot get it wrong. **The Inheritance Tax insight
   must keep its `unmodelled_relief_caveat`** — that line is already in place at
   `InsightsController:151-172` and is currently unreachable.

2. **W-0474 — a civil partnership pools two estates against one person's allowances.**
   HIGH, tax-moving, fully specified by the tax reviewer. `IHTCalculationService.php:125`
   reads `in_array($user->marital_status, ['married'])` where nine sibling services read
   `['married','civil_partnership']`. The projection predicates use
   `$dataSharingEnabled && $spouse` while the current column uses `$isMarried &&
   $dataSharingEnabled`, so a civil partnership gets **both partners' assets against one
   person's £325,000 + £175,000**. OVERSTATES tax.

3. **W-0475 — the projected gross estate omits whole asset types.** HIGH.
   `$projectedGrossAssets` is assembled from five category totals; any `assets` row of
   type `other` is in the current column and absent from the projection AND from the
   taper base. UNDERSTATES projected tax.

4. **W-0476 — the account-enumeration oracle moved one endpoint over.**
   `GET /api/spouse-permission/status` returns a different shape for a registered vs
   unregistered invited address, because only the registered branch can create a
   `SpousePermission` row. Two requests distinguish any email. **Closes together with
   W-0472** (the invited address is never retained, which is *why* the branches differ).

5. **W-0477 — a deleted spouse leaves expenditure stored as halves** that nothing
   treats as halves any more. Household spending reads at half, disposable income at
   double.

6. **The rest of the queued critical/high**, in board order:
   `W-0037 W-0050 W-0133 W-0138 W-0139 W-0144 W-0155 W-0171 W-0216 W-0222 W-0226
   W-0227 W-0361 W-0363 W-0364 W-0365 W-0462`
   Then the 109 medium, then the 23 low.

7. **The 104 `gated` items.** These are NOT fixed-and-waiting — most are
   `CANNOT CERTIFY` because evidence is missing, not because the code is wrong. Read
   `workforce/ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md` before
   assuming any of them is close to done.

### BLOCKED ON CSJ — ask these at the start of the day

- **W-0347 (CRITICAL) is FLAGGED by `compliance-lead` on five findings**, acceptances 3
  and 4 unmet. The live question: **what happens to the 10 historically forged
  `spouse_permissions` rows on dev.** A migration backfilled them as `accepted` so
  nobody lost access — is retrospectively legitimising forged consent acceptable, or
  must those households be re-asked?
- **Rule 9 amendment.** The Inheritance Tax caveat spells out "the Alternative
  Investment Market" and a test asserts the acronym's ABSENCE. If CSJ wants
  "…Market (AIM)" for recognisability, **that is a Rule 9 amendment and only CSJ can
  make it.** Do not settle it in the string.

## Context to load

- `workforce/ops/handoffs/quality-lead/cycle4-recertification-2026-08-24.md` — **the most
  important file.** Four findings against my own work, the five rejections re-judged, and
  the reason most gated items cannot be certified.
- `workforce/ops/handoffs/W-0465/tax-compliance-reviewer-round5-2026-08-24.md` — round five.
  Confirms the tax figures are right; G2/G3 are the enumerated-mapping trap again.
- `workforce/ops/handoffs/compliance/w0347-w0466-w0467-2026-08-24.md` — both compliance
  passes, including W-0347's five findings and the copy replacements.
- `workforce/ops/board/` — 273 items. The work itself.
- `tests/CLAUDE.md` — section "When a green suite goes inexplicably red". **Five variants
  of a test that cannot fail.** This session wrote three vacuous assertions and caught
  them only on mutation; read it before writing a guard.
- `CLAUDE.md` — Rules 19 (/m parity) and 20 (one change, one place) decided most of today.

## Completed this session

- **`26564407f` W-0349** — the spouse-invite endpoint stops creating accounts for
  unregistered addresses (CSJ decision) and both branches return an identical response.
  **CERTIFIED** by quality-lead.
- **`bc9156718` W-0012** — the wizard's hand-copied mortgage field list replaced by a
  rule; recovered **eight** more dropped fields. Browser-verified.
- **`e4aa4cdc9`** — round-four tax findings: F2 (I had fixed two of three implementations
  of my own formula), F5/W-0470 (deleted the liability overwrites at both call sites),
  F3, F4, F8.
- **`484197e14`** — compliance copy findings: Rule 9, an unhedged efficacy claim, and the
  teaser branch boundary.
- **`140381dca`** — the W-0467 fix had caught one of three groups; fourth branch added.
- **`9d35b79bb`** — round five: caveat reached two more payloads and no more screens.
- **`ff72d3a49` W-0466** — placeholder notice on both holdings entry forms (CSJ direction).
- **`a8fa14e21` W-0190/W-0202** — migration making the unanswered sharing state
  expressible; Fyn now ASKS instead of writing 100/0.
- **`3bbb2cb08`** — re-certification findings, including restoring three board items I
  destroyed and correcting a wrong tax claim.
- **`04ecb0ee5` W-0381** — estate analysis cache key now matches what clears it.
  **Measured end to end.**
- **`6ab21b928` W-0052** — verified already fixed; guard 4/4 green.
- **`f6810f963` W-0471** — three Goals consumers read a column that does not exist.
  Fixed with a source sweep plus behavioural tests.
- **Filed W-0470 to W-0477** — eight new items, each diagnosed rather than noted.

## Verification state

- **Targeted suites green** at `f6810f963`: Goals 66, estate/agents 580, investment
  guard 4/4, property 12, estate/tiers/tax families ~500 each. Pint clean.
- **The full suite has NOT been run to completion since `19bd1c83f` (yesterday).** Two
  attempts today: the first reported "6 failed" and was **contaminated by my own
  concurrent Pest run**; the second reached 7,126 lines with **zero** failures before I
  killed it to free the database. **Run the full suite as a consolidation point, once,
  alone.**
- **Not verified:** W-0202 acceptance 4 (needs Fyn on `/m`, on BOTH accounts of a linked
  household — only a web profile save was ever done); W-0008 untouched; iOS not built or
  looked at all day; `/m` verified only on localhost, never on csjones.

## Decisions and dead ends

**CSJ decisions — do not re-litigate:**
- **Spouse invites: invite only.** No account is created for an unregistered address.
- **`/m` estate = honest summary that hands off to web**, not a second breakdown.
- **The Alternative Investment Market is not modelled** — a placeholder notice sits on
  the holdings entry forms until it is. The component is written to be **deleted**, not
  edited.
- **The farmland caveat triggers on farmland, not the whole `other` bucket** —
  "if other = bitcoin it does not trigger".
- **W-0202: make the unanswered state expressible first** (companion timestamp, no
  backfill).

**Settled by reviewers — do not re-derive:**
- The W-0470 ruling: the **service's** projected liabilities are correct; the display
  breakdown does not project at all. Adopting the breakdown's figure would UNDERSTATE tax.
- `looksAgricultural()` is defensible **only because it gates a sentence, not a figure**.
  It must never become an input to a relief calculation (IHTA 1984 s115(2), s116, s117).
- "Linking your accounts" is **not** a fair-value concern — it points at a FREE action.

**Dead ends:** Agricultural Property Relief and AIM shares cannot be implemented as the
schema stands. `workforce/registry/` is a myth — it is `workforce/core/registry/`.

## Things that will bite you

- **ONE Pest process at a time, always.** Deadlock + 0 assertions = contention, not
  breakage. Re-run in isolation before believing any red.
- **`!` and `DEPR` marks are NOT failures.** Read the summary line; no `failed` count
  means nothing failed.
- **`TaxStrategyCalculatorTest`'s 250ms benchmark is load-sensitive.** It reddens under
  a busy machine and passes three times over on a quiet one. Not a regression.
- **NEVER write a file with `open(p,'w').write(open(p).read() + note)`.** Python evaluates
  the write-open FIRST, truncating before the read. **This destroyed three board items
  today.** Read into a variable, then open for write.
- **This codebase drops published fields in enumerated frontend mappings.** It happened
  **five times in two days**. A field published by a service is not on screen until every
  hand-written mapping between them names it. Check the whole chain, every time.
- **Pest's `toContain` takes VARARGS, not a message.** A second string is asserted as
  another needle. I made this mistake twice today.
- **`EstateAgent` cache** — now keyed correctly, so `estate_analysis_{id}` by hand is no
  longer needed. Use `CacheInvalidationService::invalidateForUser()`.
- Persona passwords `Password1!`, one attempt — shared lockout. Local MFA codes come from
  the database, never ask CSJ for them.

## Tech debt deferred

- `formatAssetType` has eleven copies across four directories (W-0443).
- `rent` and `utilities` never persist from the expenditure form.
- The footer module's light variant points "Unsubscribe" at `$links[last]['url']` rather
  than the unsubscribe entry by name — a custom `$links` array silently re-points it.
- W-0470's second half: the per-liability detail rows still come from the non-projecting
  breakdown, so the panel can show −£3,500 above a £0 total.
- W-0012 Rule 19: `/m` and native have no property form; Fyn's `handleCreateProperty`
  accepts five mortgage fields, not nine.

## Branch and deploy state

- **Branch: `estate-copy-and-m-handoff`**, 20 commits ahead of `dev`. Tree clean.
- **Unpushed commits: none.**
- **Deploy: nothing from this branch has been deployed.** dev (csjones.co/fynla) is still
  at `5c556e252`. Prod untouched.
- **No agents running.** All killed 11:53 BST.
