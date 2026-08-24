---
id: HANDOVER-fix-cycle4-wills
type: handover
parent: core/constitution/08-process.md
surfaces: [web, m]
consistency_checked: 2026-08-23T04:15:00Z
status: active
---

# Handover — build-lead (`fix-cycle4-wills`), 2026-08-23

**Rule 22 handover, written at team-lead's instruction while nothing is mid-edit.**
Not a summary — the board items are written to be read cold and the branch docs
hold the detail. **This is only what is NOT in either.**

**Read instead of this:** [`F-0029`](F-0029-cycle4-wills-and-estate-figures.md) ·
[`F-0031`](F-0031-cycle4-charitable-figures.md) ·
[`F-0032`](F-0032-cycle4-rate-literals-and-the-charitable-denominator.md)

---

## 1. Where I left the board, and why

| Item | State | Why there |
|---|---|---|
| W-0391, W-0393, W-0394, W-0395, W-0396, W-0397 | `handoff` → quality-lead | F-0029. Browser-verified both accounts. |
| W-0399, W-0431 | `handoff` → quality-lead | F-0031. Gate cleared with conditions; all discharged. |
| W-0432, W-0433 | `handoff` → quality-lead | F-0032. Gate cleared with conditions; all discharged. |
| **W-0451** | `queued`, **HIGH, unowned** | **Highest-value item on the board.** Changes a published figure — deliberately not folded into a batch cleared for Rule 2. |
| **W-0452** | `queued`, **HIGH, unowned** | Same reason. **Note its history: I filed it backwards and corrected it from the browser.** Trust the measured table, not the original framing below it. |
| W-0453, W-0454 | `queued`, unowned | Small, self-contained, outside what either gate cleared. |
| W-0392 | `queued`, `blocked_by: [csj-decision]` | Product call, escalated. |
| W-0398 | `queued` | Design limit, not a defect. Its first paragraph says so — read it before investigating. |

**Nothing is half-applied.** Every edit is linted, tested, mutation-tested and
documented. No commit, no PR, no git lock. The Playwright tab is free.

## 2. The four branches the persona cannot reach

**Covered by tests only. A browser pass on `peak_earners` proves nothing about
them, and a green screenshot would be worse than none.** Full detail in
`F-0032` §7; the reasons, so nobody re-attempts them on this household:

1. **The corrected statement of law** (`EstatePlanService`) — this estate is on
   the **standard** rate branch, so the reduced-rate sentence never renders.
   **The batch's top-priority fix is the one the page cannot show.**
2. **`IHTPlanning.vue:599`** — inside `v-if="!secondDeathData?.mitigation_strategies"`.
   The server supplies strategies for this household, so the block is suppressed.
3. **The `const` unvalued-gifts sentence** — needs a charitable gift of an asset
   or a residuary share. This household has neither.
4. **The C2 profile branch** — needs `ihtprofiles.charitable_giving_percent > 0`
   **with zero recorded bequests**. No seeded persona has that combination.

**To exercise 3 or 4 you must build a household, not pick one.**

## 3. Things I know that have no board item

- **Vite HMR will remount a page mid-interaction in this shared tree.** Two
  agents editing `resources/js/` is enough. Symptom: `fill()` sets the DOM value,
  Vue's reactive state stays empty, the submit handler fires no request — and it
  reads as "the login form is broken". It is not. **Make fill-and-click atomic in
  one `browser_evaluate`.** Checking `public/hot`'s age does NOT catch it: Vite
  was running and serving correctly. Written up in `F-0031`.
- **My test database is `laravel_testing_t`.** Nothing else used it tonight.
- **`estate_analysis_16` / `_17` must be cleared by hand before any estate
  reading** (W-0381). I cleared them before every measurement in all three
  batches; a figure read without clearing is not evidence.
- **The persona's data moved £12,000 on David's side mid-run**, which is why
  F-0029's acceptance figures were stale. **Assert relationships, not literals,
  on this household while other agents are live in the tree.**
- **Scratch files** are outside the repository, in the session scratchpad. Backup
  copies used for mutation testing are there too; nothing needs cleaning up
  inside `Desktop/fynla`.
- **Tax verdicts** live at `workforce/ops/handoffs/W-0399/` and
  `workforce/ops/handoffs/W-0432/`. Both are worth reading in full before
  touching `IHTCalculationService` — the 2026-08-21 pooling ruling is quoted in
  the code but its **coupling** (recorded on W-0399) is not obvious from the
  code alone.

## 4. The one thing that would be expensive to rediscover

**W-0399 carries a coupling that is invisible until someone improves the model:**

> If any future change makes the model actually settle the first death, the
> pooled section 23(1) exemption must be removed in the **same** change, or the
> first legacy is relieved twice. **They are correct only together.**

Two mechanisms each wrong alone and right in combination. **Every test on the
current model stays green while an improvement to one of them breaks the tax.**

## 5. Decisions taken, so they are not re-litigated

- **The will page shows `user_net_estate`, not the household aggregate** —
  team-lead's ruling, reasoning on W-0391.
- **`potential_saving` keeps the baseline as its base.** F-0032 made the RATE
  configuration-driven and deliberately did not re-derive the formula. The gate
  confirmed the boundary and answered the question behind it — that answer is
  **W-0451**.
- **The plan-config threshold key is inert, not deleted.** Deleting a seeded key
  is a data change; this was a Rule 2 fix.
- **`TaxConfigService`'s consolidation note records two grep commands rather than
  a count**, because three successive statements of that count, by three
  different authors, were each confident and each wrong.

## 6. Dead ends — do not re-walk

- **Do not "fix" `EstatePlanService:504`.** It reads the array directly but falls
  back to `TaxDefaults::IHT_CHARITABLE_RATE`, which is the sanctioned convention.
  Named in `TaxConfigService`'s note for exactly this reason.
- **Do not run the generator's bidirectional swap as a repair.** It makes a
  correct will name its own testator. `repairSelfNamedParties()` is
  one-directional by design; the reasoning is in its docblock.
- **Do not assume a coordinator relay of session or path state.** Seven were
  wrong tonight, including one that pointed at a fenced directory. Check
  `GET /api/auth/user` per token, and `find` the file before opening it.
