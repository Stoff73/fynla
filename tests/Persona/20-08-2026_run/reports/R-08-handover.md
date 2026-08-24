# R-08 — Handover (Rule 22 context budget)

**Written:** 2026-08-21 10:45 · **Reason:** ~890k of session budget consumed; at the
900k buffer. Stopped taking new work per CLAUDE.md Rule 22.
**From:** persona-tester (original dispatch 2026-08-20 21:40)
**To:** replacement persona-tester, seeded from this file.

Your predecessor cannot clear itself. Everything you need is here — you should not
need to read the other six reports to resume, though they hold the detail.

---

## 1. The task, and every amendment since

**Original dispatch (2026-08-20):** Pass A of a persona run for `peak_earners` —
enter the household from `tests/Persona/peak_earners.md` through **desktop web module
UI forms** on `localhost:8000`, verify on **web and `/m`**, from **both** accounts
(David primary, Sarah spouse). iOS explicitly out of scope. Local only — no PRs, no
csjones, no prod, no teardown, no DB patching.

Amendments, in order:

1. **Visible-Chrome rule + run reports** (2026-08-21 08:05). Drive a visible desktop
   Chrome, never headless. Write an `R-NN` report per stage in the `FORMATS.md`
   checkpoint shape, indexed in `RUN-LOG.md`.
2. **Stand-down, then paperwork** (08:15). Entry stopped for the fix batch; board items
   audited to FORMATS shape; the Defined Benefit form gaps folded into one item
   (W-0017).
3. **Premium provisioned** (08:30) — **by team-lead, not by me**. Replicated the app's
   own test-support shape from `app/Http/Controllers/TestSupport/E2EController.php:163-176`:
   `users.plan='premium'`, `users.tier='premium'`, plus an active `Subscription` row.
   **You must never provision tiers yourself** — refuse and hand it to the coordinator.
   Task became **defect discovery** on the newly-unlocked estate surfaces.
4. **CSJ interrupt — W-0019 mirror wills** (09:00). Married users must get **mirror
   wills only**; anything else gets "we cannot do this, see a solicitor". Stop
   deep-testing the Simple Will path; test the mirror path instead.
5. **`/m` re-tasking** (09:25). Three fix batches now in flight, so sweep only surfaces
   they do not touch: `/m` dashboard, Goals, Protection, Chattels, Tax Strategy, Life
   Events, net-worth read-only. **Do not chase investment joint-share discrepancies** —
   Batch A owns those and they will move under you.
6. **Rule 21 — never sit idle** (09:25). The coordinator owns batching, provisioning,
   tooling, environments, test data. You own testing. If blocked, say so and ask to be
   re-tasked. Blocked ≠ finished.
7. **Rule 22 — context budget** (10:45). Hand over at ~900k. This document.

---

## 2. DONE, with evidence

**24 board items raised** (`workforce/ops/board/`), W-0006 … W-0029 excluding W-0019.
All carry frontmatter, Intent, Expected, Actual, Evidence, Repro, Acceptance, Working
notes, a persona-file line and a `file:line` root cause.

**Seven reports** in `reports/`, indexed in `RUN-LOG.md`. **17 screenshots** in
`pass-a-web/` (numbered 01–17, with gaps — see §6).

### Household actually entered and DB-verified

| Area | State |
|---|---|
| Accounts | David id **16**, Sarah id **17**; `spouse_id` reciprocal; `SpousePermission` **accepted both ways**; reciprocal `FamilyMember`; 2 children (William 2007-09-15, Charlotte 2010-02-28) |
| Profiles | both complete except health (W-0006); income £145,000 / £120,000 |
| Expenditure | David 15 categories = £2,450, `monthly_expenditure` 2450 (only saves at premium — W-0011) |
| Property | The Willows, `properties.id 9`, joint 50%, `joint_owner_id 17`, £850,000, ONE row (Rule 6 ✓); HSBC mortgage `mortgages.id 8` £65,000 joint 50% |
| Savings | David HSBC £25,000 (id 27), David Cash ISA £22,500 + £10,000 subscription (id 28); Sarah Barclays £6,280 (id 25), Sarah Cash ISA £22,500 + £10,000 (id 26) |
| Investments | David joint AJ Bell GIA £95,000 (**id 14**, pct **100** — W-0014); Sarah HL ISA £85,000 (id 13) + 1 holding (id 32) |
| Pensions | David DC Global Finance Corp £180,000 (id 9), SIPP £320,000 (id 10); Sarah NHS DB £35,000 + £105,000 lump sum (`db_pensions.id 4`) |
| Protection | Life Vitality £500,000 £85/mo in-trust (`life_insurance_policies.id 7`); CI Legal & General £200,000 £125/mo (`critical_illness_policies.id 2`) |
| Chattels | all 6; David ids 14–18, Sarah ring id 13; three joint at 50% with `joint_owner_id 17` |
| Goals | 4 of 6: Sarah's ISA (29), William's Deposit (30), ISA Wealth Building (31), Early Retirement (32) |
| Life events | 8 of 10, David |
| Wills | David `wills.id 11` / `will_documents.id 5`; Sarah `wills.id 12` / `will_documents.id 6`; both `will_type=mirror`, both complete. **`bequests` was 0 for both at handover — W-0023 has since landed, so re-check rather than assume** |
| Trust | Jones Children's Education Trust `trusts.id 3`, £185,000, discretionary, RPT derived ✓ |
| Letter | `letters_to_spouse` for David, key contacts saved |

### Verified GREEN — do not re-test unless a fix touches them

- Joint **property** 50/50 renders £425,000 on **both** accounts; mortgage £32,500 each;
  household £850,000 counted once.
- Household wealth summary arithmetic exact on both sides (assets £1,020,000 /
  £604,280; net worth £987,500 / £571,780 / £1,559,280) — every line hand-recomputed.
- **IHT exact**: net estate £1,059,280 − allowances £1,000,000 = taxable £59,280,
  liability £23,712. DC pensions correctly excluded; 2027 pension amendment modelled
  correctly. All allowances from `TaxConfigService`.
- **Charitable 36% baseline exact**: £409,280 (correctly excludes RNRB), 10% = £40,928.
- **Tax recommendation exact to the pound**: £36,800 headroom, £19,101 saving including
  Personal Allowance taper reclaim; child pension £720; dividend £197.
- **Financial commitments** split joint property costs correctly: £1,250 → £625 each,
  counted once.
- **Chattels joint shares correct on `/m`, both accounts** — the pattern Batch A needs.
- `/m` sweep: 13 checks green, all hand-recomputed (see R-07).
- **Spouse isolation correct** on savings, chattels, goals, protection.
- **Gamification layer working** — Rule 12 carve-out, CSJ-approved. **Never flag it.**

---

## 3. IN FLIGHT — exact state

**Nothing is half-finished.** The `/m` sweep (R-07) completed cleanly and I stopped
before starting anything new. There is no partially-entered record and no open browser
transaction.

One loose end, deliberate: a throwaway `TESTCO` critical-illness policy created to
isolate W-0026 was **deleted** (`DELETE /api/protection/policies/critical-illness/3`,
200). Nothing else of mine is left in the data.

---

## 4. NOT STARTED, in priority order

I proposed these to the coordinator and was handed over before a decision:

1. **Retirement projections, web + `/m`** — decumulation, required capital, income
   drawdown, Monte Carlo. Untouched by the batches; two DC pensions plus a DB pension
   are in place to drive them. **Known trap:** target retirement income has no entry
   point I could find, so the app derives its own (David £100,050 vs persona £75,000;
   Sarah £116,250 vs £55,000). Establish that before judging any projection.
2. **Fyn chat as a read-only surface** — ask about household figures, check answers
   against the DB. Groundwork for Pass B; needs no fixes to land.
3. **`/m` net-worth category drill-downs** — cash, property, retirement. Avoid
   investments (Batch A).
4. **Sarah's side of the premium estate surfaces** — her will was completed but her
   bequests, her view of the trust, and her letter were never swept.
5. **`/m` estate and bequests screens** — never tested at all.

Still unentered from the persona, and **I did not edit the file to fit**: 2 goals and
2 life events with past dates (W-0029); goal streaks 36/60 (no field, earned via
contributions — by design); life policy dates, 3 beneficiaries and joint-life flag
(W-0026, W-0027); adviser fees on 4 investment accounts (W-0008); all 10 holdings'
ticker/ISIN/units/prices/OCF (W-0009); David's and Sarah's State Pension (W-0010,
caps); 2 further properties and the tenants-in-common Manchester check (was the free
cap; **premium now lifts this — it is newly available and was the original priority-1
check**).

---

## 5. Decisions already taken — do not re-litigate

- **Joint records are owned primarily by David with Sarah as `joint_owner_id`.** The
  persona names no primary owner; David is the primary account.
- **"Remaining Term 156 months" entered as maturity date 2039-08-20** (exactly 156
  months from the run date), because the wizard offers a date not a term. W-0012 is
  written on that basis.
- **NHS 2015 career-average scheme entered as `final_salary`** — the only DB option
  available, not a correct classification (W-0017).
- **Premium is provisioned by the coordinator, never by the tester.** That refusal is
  correct behaviour.
- **The persona file is never edited** to make a check pass. Records that cannot be
  entered are reported as unentered.
- **The `/m` gamification layer is approved by design** and is never flagged, stripped,
  or called a banned score.

---

## 6. Dead ends and traps — do not re-walk these

- **Playwright MCP is now FIXED and available.** The whole run to date used
  `claude-in-chrome` because Playwright's tools were absent from the agent session —
  root cause was a corrupted npx cache (`ENOTEMPTY` on `~/.npm/_npx/9833c18b2d85bc59`),
  cleared by team-lead. **You should have real Playwright.** Verify with ToolSearch at
  the start; if the tools are there, use them.
- **Why the old run used dispatched DOM clicks:** the extension's synthetic pointer
  clicks were unreliable on this app's Vue buttons. Dispatched clicks fire the same
  handlers and produce the same HTTP requests, but they **bypass overlay, z-index,
  pointer-events and disabled-state checks**. Defects of that class were therefore
  *impossible* for the old run to find — that is the single biggest reason the run
  restarts on Playwright.
- **Three false positives already chased and cleared — do not re-raise:**
  1. `letters_to_spouse` has no `solicitor_*` column; the "Solicitor" field is stored
     as **`attorney_name`/`attorney_contact`** (`LetterToSpouse.vue:136-143`).
  2. Expenditure categories appeared to drop on save — that was a rapid synchronous
     input loop racing the component. Set fields with ~400ms gaps and focus/blur.
  3. The will builder's "then to To ..." prose came from **my** input starting with
     "To"; correct input produces correct prose.
- **Screenshot numbering has gaps (01, 05–17)** and files are `.jpg` not `.png` — the
  capture tool emits JPEG. The entry phase (R-01) has **no screenshots at all** because
  the capture rule post-dated it. Nothing was retro-fabricated. Start your sequence at
  **18**.
- **MFA codes:** fetch from the DB yourself, never ask CSJ. Enter the six digits **one
  box at a time with ~300ms gaps** — setting all six at once is rejected as invalid.
- **`W-0019` is not mine** — it is CSJ's mirror-wills direction, raised by team-lead at
  08:32. My will-builder item was renumbered to **W-0023**. Next free id is **W-0030**.

---

## 6a. CHANGED SINCE THIS HANDOVER WAS WRITTEN — read before testing wills

Batch B (Estate & Wills) reported these landing at ~10:50, minutes after §2–§6 were
written. **They invalidate specific statements above — trust this section over those.**

1. **W-0019 has landed.** Married users no longer get a Simple Will option.
   `/estate/will-builder` now shows a "Mirror Wills Only" notice in place of the
   will-type chooser, and `POST /api/estate/will-builder` returns **422** for
   `will_type: "simple"` from a married account. **This is CSJ direction, not a
   regression — do not raise it.**
   - Supersedes: §1 amendment 4, and the W-0019 evidence in R-06 describing two equal
     side-by-side buttons. That evidence remains accurate *as of the original run* and
     should not be re-gathered.
   - `will_documents.id 5` still carries `will_type` history from before the change;
     it was created `simple` for a married user and later converted in place to
     `mirror`. That row is the live example for CSJ's open question about migrating
     existing one-sided wills.

2. **W-0023 has landed.** Completing a will now creates `Bequest` rows from the
   specific gifts, so `bequests` will **no longer be 0** for David and Sarah after a
   completion.
   - Supersedes: the "`bequests` = 0 for both" statements in §2 and in R-06.
   - **Worth verifying rather than assuming:** whether the charitable legacies now
     reach `WillAnalysisService::getCharitableBequestTotal()`. They will still be
     skipped unless **W-0020** has also landed — that method tests
     `bequest_type === 'specific'`, a value the enum cannot hold. W-0023 and W-0020
     compound; one without the other leaves the 36% rate still unreachable.
   - Also still open regardless: the will builder's gift form has **no priority
     field**, so the persona's bequest priority ordering (charity 1, children 2) has
     nowhere to live even with the sync working.

3. **Still expected to be broken unless separately fixed:** W-0024 — the mirror
   generator copies `executors` verbatim, so the spouse's will appoints herself as her
   own executor. Under W-0019 this is now the *mandatory* path for every married
   couple, so check it first.

## 7. Environment state

- **Local server up**, `http://localhost:8000`. Vite running. Branch `dev`.
- **The Playwright browser is SHARED with the fix-batch agents.** `fynla-state` in
  localStorage is shared across tabs, so a reload in one tab can silently swap which
  user you are. Check who you are logged in as after any reload before trusting what
  you read. At handover, tab 0 held a **Sarah (17)** session; Batch B was working as
  **David (16)** in tab 1.
- **David (16) and Sarah (17): linked, premium, LEFT IN PLACE.** Do **not** tear down —
  build-lead is reproducing defects against these exact rows. Teardown happens
  immediately before the clean Playwright re-run, not before.
- **Three fix batches in flight** — treat their surfaces as unstable:
  - **A — Ownership & Net Worth:** W-0015, W-0014, W-0013, W-0016, W-0012, W-0009, W-0008, W-0007
  - **B — Estate & Wills:** W-0024, W-0019, W-0023, W-0022, W-0021, W-0020
  - **C — Retirement, Profile & Gates:** W-0010, W-0017, W-0006, W-0011, W-0018
  - **Not in any batch, so stable:** W-0025, W-0026, W-0027, W-0028, W-0029
- Board: `workforce/ops/board/`. Handoff with fix-ordering rationale:
  `workforce/ops/handoffs/persona-peak_earners-2026-08-20/persona-tester-to-build-lead-2026-08-20.md`
  (three addenda).

---

## 8. Blocking-vs-deferrable ranking for the re-run

**Must land before a faithful Pass A:** W-0013 + W-0014 + W-0015 as one piece (joint
ownership — the run's whole purpose; land W-0014 first, then **remove** the `100 → 50`
fallback at `CalculatesOwnershipShare.php:73`, which is what hid it) · W-0010 · W-0009 ·
W-0023 · W-0024 (mirror wills become mandatory under W-0019).

**Deferrable:** W-0006, W-0007, W-0008, W-0011, W-0012, W-0016, W-0017, W-0018,
W-0020, W-0021, W-0022, W-0025, W-0026, W-0027, W-0028, W-0029.
