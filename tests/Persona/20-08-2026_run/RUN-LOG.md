# Run log — `peak_earners`, 20 August 2026

## How this pass ran — read before trusting its coverage

| | |
|---|---|
| **Pass** | A (module UI forms), local `http://localhost:8000` |
| **Status** | Entry halted for the fix batch; **premium sweep completed** 2026-08-21. Full re-run on genuine Playwright MCP still to come. |
| **Driver used** | `claude-in-chrome` against CSJ's own real, headed Chrome — **not** Playwright MCP, whose tools were absent from the agent session all run (root cause: corrupted npx cache, `ENOTEMPTY` on `~/.npm/_npx/9833c18b2d85bc59`; fixed by team-lead after this pass). |
| **Interaction method** | Field entry via the extension's `form_input` or a native-setter plus `input`/`change` dispatch. **Button activation via dispatched DOM click events**, because the extension's synthetic pointer clicks proved unreliable on this app's Vue buttons. |
| **Coverage caveat this creates** | Dispatched clicks fire the same handlers and produce the same HTTP requests, but they **bypass overlay, z-index, pointer-events and disabled-state checks**. Defects of that class could not have been found by this pass. The re-run uses real pointer clicks specifically to close that gap. |
| **Verification standard** | Every write confirmed against the MySQL row via `php artisan tinker`, never against the screen. Silent failures caught by hooking `XMLHttpRequest` in the live page. |
| **Screenshots** | 6, all from the verification phase. The entry phase has **none** — both the screenshot and run-report rules were issued after that work was done, and nothing was retro-fabricated. |
| **Household** | David Jones (16) and Sarah Jones (17) **left in place** for build-lead to reproduce against. Teardown happens immediately before the re-run. |

One line per report, in order. Newest at the bottom.

| # | Report | When | Outcome |
|---|---|---|---|
| R-01 | [Pass A, data entry](reports/R-01-pass-a-entry.md) | 2026-08-20 21:44–23:05 | Household created and linked; 15 records entered and DB-verified; 11 persona blocks unenterable. **Backfilled — this phase has no screenshots.** |
| R-02 | [Pass A, verification](reports/R-02-pass-a-verification.md) | 2026-08-20 23:05–23:35 | 13 checks GREEN (joint property 50/50 both sides, wealth summary exact, web↔/m net worth parity). 7 checks RED → W-0006, W-0014, W-0015, W-0016. |
| R-03 | [Pass A halted — tier blocker](reports/R-03-pass-a-halted-tier-blocker.md) | 2026-08-20 23:05 | Free-tier caps and premium gates make ~25% of the persona unreachable. **Decision needed before Pass A can continue.** |
| R-04 | [Pass A stand-down](reports/R-04-pass-a-stand-down.md) | 2026-08-21 08:20 | Entry stopped by decision. 12 items handed to build-lead, board audited to FORMATS shape, W-0017 created. Household left in place. |
| R-05 | [Premium sweep](reports/R-05-premium-sweep.md) | 2026-08-21 08:30–09:00 | Premium provisioned by team-lead. Estate/wills/trusts/LPA/letter/holistic swept. 11 GREEN checks (IHT exact, tax recommendation exact to the pound). 5 defects: W-0018, W-0020, W-0021, W-0022, W-0023. |
| R-06 | [Mirror will test](reports/R-06-mirror-will-test.md) | 2026-08-21 09:00–09:20 | CSJ interrupt (W-0019). Simple-will testing stopped; mirror path tested end to end, both wills generated and completed. Residuary swaps correctly; executors do not. 1 new defect: **W-0024**. |
| R-07 | [`/m` sweep](reports/R-07-m-sweep.md) | 2026-08-21 09:25–10:35 | Re-tasked off the fix batches. Entered 6 chattels, 2 policies, 3 goals, 8 life events; swept `/m` dashboard, goals, protection, chattels, tax strategy, net worth on BOTH accounts. 13 GREEN checks, all hand-recomputed. 5 defects: W-0025…W-0029. |
| R-08 | [**HANDOVER**](reports/R-08-handover.md) | 2026-08-21 10:45 (§6a amended 10:52) | **Rule 22 context budget reached (~890k).** Stopped taking new work. Full handover for a fresh agent: task + 7 amendments, DONE with evidence, nothing in flight, NOT STARTED in priority order, decisions taken, dead ends ruled out, environment state, fix ranking. |
| R-09 | [Playbook + static defects](reports/R-09-playbook-and-static-defects.md) | 2026-08-21 11:00–12:45 | **Re-run playbook written** (`PASS-PLAYBOOK.md`, 1,250 lines): entry map, precomputed expected values with arithmetic, per-account matrix, a regression check per defect, Pass B and Pass C scripts. No surface tested — four fix batches in flight. **4 new defects found by static analysis: W-0035, W-0036, W-0037, W-0038.** |
| R-10 | [Batch A checks folded in](reports/R-10-batch-a-checks-folded-in.md) | 2026-08-21 13:00–13:35 | Coordinator's three independent-confirmation checks added as playbook **§4.1**; W-0039 and W-0040 folded into the regression table. **Batch A's four consolidation claims independently verified by reading the code** — `SharedOwnership` is genuinely the one write rule, `ownership.js` the one display rule, the `100 → 50` read fallback is gone, and a backfill migration repaired existing 100/0 rows. `/m` remains untested by anyone. |
| R-11 | [Onboarding + spouse-linking sweep](reports/R-11-onboarding-and-spouse-linking-sweep.md) | 2026-08-21 13:45–15:10 | **First leg driven on real Playwright pointer clicks.** Registration → verification → onboarding → spouse linking, throwaway accounts only, in an isolated browser context so another agent's session was untouched. Spouse linking **GREEN** (reciprocal `spouse_id`, reciprocal `FamilyMember`, `SpousePermission` auto-accepted both ways). **2 defects: W-0050, W-0051.** 3 false positives chased and cleared. |
| R-12 | [Batch A confirmation + regression](reports/R-12-batch-a-confirmation-and-regression.md) | 2026-08-21 15:20–16:00 | Batch A independent confirmation begun. **W-0014 does NOT reproduce** — Joint Owner select present, populated and hit-testable, with full DOM evidence. **W-0052 found: creating any investment account 500s** (`advisor_fee_percent cannot be null`), a regression of the W-0008 fix — blocks the investments module. Free-tier capability matrix read live and folded into the playbook. |
| R-13 | [Batch B leads verified](reports/R-13-batch-b-leads-verified.md) | 2026-08-21 16:20–17:05 | **The Review-step screenshot captured** — error banner, offending will preview and greyed-out Complete & Finalise in one frame. Validation confirmed **both ways** (gate closes on the defect, opens when fixed). Will completed, `executor_name` correctly regenerated. **W-0053 found: completing a mirror will strands the user — no route to Generate Spouse's Will afterwards.** |
| R-14 | [**HANDOVER** (early, ~705k)](reports/R-14-handover.md) | 2026-08-21 17:20 | Written at two-thirds by instruction, not at the buffer. Carries the frozen cap-lift protocol state (step 4 of 7, `users.id 31` held), the three browser contexts and which is frozen, all four defects raised, GREEN list, **six cleared false positives**, next actions in order, and the standing rules. **Not blocked — still working.** |
| R-15 | [Cap-lift test **GREEN**](reports/R-15-cap-lift-test-GREEN.md) | 2026-08-21 17:55–18:10 | **The cap lifted live — no reload, no re-login.** Same click that left `formOpen: false` at the free baseline returned `formOpen: true` after server-side provisioning, and the second life event **saved** (`POST /api/life-events` → 201; `life_events.id 84` written post-provisioning). Outcome 1, the only acceptable one; steps 2–3 not needed. Protocol run to the letter after the voided first attempt. **1 note raised: W-0054.** |
| R-16 | [Batch B estate regression, browser-verified](reports/R-16-batch-b-regression.md) | 2026-08-21 18:10–19:30 | Independent Rule 14 close on batch B's four estate items plus W-0037, on Priya (20) and Arjun (30). **Checks 2, 3 and 4 GREEN** — `deleteBequest` reports success as success and the row really goes; two gifts produce two correct `Bequest` rows visible on web **and** `/m`; W-0053's mirror is generatable after completion **and after a reload**, and `will_documents.14` was rescued through the interface (`mirror_document_id = 15`). **Check 1 green on the behaviour but not on the reason** — the rate figure moves 36% ↔ 40% with only a bequest changed, but there is no cache to invalidate and the rate *label* is wrong on both estate screens. **W-0037 RED as predicted.** Batch B's "deleted bequest comes back" is **not reproducible from the interface** — completion is a one-way door, which is worse. **3 new defects: W-0131, W-0132, W-0133**; W-0037 extended with live evidence. |
| R-17 | [Persona estate figures — full ledger, both accounts, web + `/m`](reports/R-17-persona-estate-figures.md) | 2026-08-21 19:45–21:00 | **Re-scoped by the coordinator onto the actual persona household (David 16, Sarah 17), figure-by-figure instead of a rate label.** Read-only; no persona data touched. **Composition is GREEN throughout** — every joint record split 50/50, both subtotals adding to £1,299,280 gross and £1,234,280 net, identical from both logins. **Everything downstream of it is RED.** Hand-computed household answer **£145,712**; the app shows **£149,712** to David and **£89,712** to Sarah. David's four allowance rows sum to £1,000,000 beneath a £850,000 subtotal; the £10,000 charitable deduction has no row on either account. `/estate/inheritance-tax` and `/plans/estate` disagree by £7,595 (David) and £7,882 (Sarah) on projected tax, and the drill-down's figure reconciles to nothing on its page. The residence nil rate band taper is never applied to a £2.34m projected estate while the footnote says it is below the threshold. Projected cash reaches **−£1.8m**, a Cash ISA **−£854,179**. `/m` omits chattels entirely (£132,250 / £60,750) and shows no tax figure. Sarah is told her charitable rate is **0%** on a page deducting her £10,000. **Nothing explains the £500,000 pension exclusion, the £150,000 gift, or the charitable deduction — the words "pension", "2027" and "charitable" do not appear on the drill-down at all.** **7 new defects: W-0134–W-0140**; W-0154 and W-0132 cross-referenced, not re-raised. **SECOND PASS appended ~21:30** — the missing two-thirds of the household entered through the module forms (3 properties, 3 mortgages, 6 savings, 4 investments; gross **£2,021,780**, net **£1,716,780**). A fix landed in `IHTCalculationService` at 20:14 mid-entry: **W-0154 and W-0139 VERIFIED FIXED** — both accounts now show taxable **£846,780** and tax **£338,712**, matching the hand-computed household answer to the pound. **W-0134, W-0135, W-0136 NOT fixed**, and W-0136 is now the largest error on the page: a **£4,368,401** projected estate carries an untapered **£850,000** allowance under a footnote saying it is below the £2,000,000 threshold — tax understated by **£152,356**. **2 more defects: W-0171** (the estate cannot be audited by its owner, promoted from a product note) and **W-0172** (a tenants-in-common property saves its mortgage as joint 50%). W-0131–W-0140 exhausted; **W-0171–W-0175 claimed**. |
| R-18 | [**Persona estate ledger — complete household, contract-grade**](reports/R-18-persona-estate-ledger-complete-household.md) | 2026-08-21 21:45–23:10 | **Supersedes R-17 §2.** Full ledger re-run against the complete household, read-only, both accounts, web + `/m`. **GREEN:** estate composition (£2,021,780 gross, identical from both logins); every ownership share on every record; **the £177,000 third-party share appears nowhere**; Sarah sees no Manchester anything; **W-0154 verified fixed** — both accounts at taxable £846,780 / tax **£338,712**, matching an independent hand-computation to the pound; **W-0139's calculation half fixed** (charitable now the household's £20,000). **RED:** allowance rows still sum to £1,000,000 beneath an £850,000 subtotal and the column is now **£20,000** out (W-0134); **the taper is never applied to a £4,368,401 projected estate — understated by £152,356** (W-0136, and the taper logic already exists, so it is a scoping fix); the two logins project households **£103,206 apart**, a gap that **scales with the estate** (W-0135); Sarah's £10,800 rental share reaches nobody (**W-0173**, W-0172's twin in the income module); expenditure is property costs not recorded expenditure (W-0140); `/m` narrowed to **exactly the missing chattels class** — £132,250 / £60,750 — with every other split correct (W-0138). **W-0172** visible on a second surface: the Manchester equity card reads £58,000 where £70,000 is due. Persona file and playbook annotated on Premium Bonds (individual-only, the file was wrong, not the app). |

## Defects raised this run

All routed to `build-lead`. Board: `workforce/ops/board/`.

| W-item | Sev | Summary |
|---|---|---|
| W-0006 | med | Health/smoking never persist; education persists but is never exposed |
| W-0007 | high | Investment modal reports Cash ISA usage as £0 — £25,000 ISA subscription passes with no warning |
| W-0008 | med | Adviser fee has no input, yet is displayed and compounded in projections |
| W-0009 | high | Every holding edit sends a null body and returns 200 |
| W-0010 | high | Dead-end: with only a Defined Benefit pension there is no Add Pension control |
| W-0011 | high | Free tier cannot save expenditure at all — Simple View silently 403s |
| W-0012 | med | Mortgage term hardcoded to 300 months; Rate Fix End Date unmapped |
| W-0013 | high | Joint savings account cannot be created |
| W-0014 | high | Joint investment saves `ownership_percentage = 100` |
| W-0015 | high | One joint share computed three ways; both spouses shown 100% of the same £95,000 |
| W-0016 | low | Property card names the viewer as their own co-owner |
| W-0017 | med | Defined Benefit form cannot hold Normal Retirement Age, Spouse Pension %, CPI/RPI enum, or career-average scheme type |
| W-0018 | low | `TierResolver` docblock says explicit `users.tier` wins; `resolve()` never reads it (gated on CSJ) |
| W-0020 | high | Charitable total tests `bequest_type === 'specific'`, a value the enum cannot hold — cash legacies never reach the 36% rate |
| W-0021 | low | Trust card shows bare acronym "RPT" (Rule 9); same page spells it out correctly |
| W-0022 | high | Letter tells the spouse "No outstanding liabilities recorded" while a £65,000 mortgage exists |
| W-0023 | high | Will builder gifts never become `Bequest` rows — invisible to Estate, `/m` and the charitable IHT rate (reproduces on the mirror path too) |
| W-0025 | med | Joint chattel saves with no joint owner and no error — 50% belongs to nobody |
| W-0026 | high | `policy_end_date` validated, 201'd and silently discarded on 4 of 5 policy models; Life form has no date fields |
| W-0027 | med | Life policy takes one beneficiary from a list excluding the children; no joint-life flag |
| W-0028 | high | `/m` "Goals and life events" page renders no life events — never fetches them |
| W-0029 | med | Goals and events cannot be dated today or earlier — 4 persona records unenterable |
| W-0024 | high | Mirror will copies executors verbatim — the spouse's will appoints **herself** as her own executor; charity legacy also seeded with the other spouse's charity; spouse gets no Guardians step |
| W-0035 | high | Target Retirement Income has no module-UI entry point — every retirement projection runs on a 75%-of-income fallback |
| W-0036 | high | A Defined Benefit pension counts as income in payment from the day it is entered — a 48-year-old is treated as receiving her NHS pension |
| W-0037 | med | Bequest form cannot record priority, beneficiary type or charity number — charitable status is inferred from the beneficiary's name |
| W-0038 | med | Goal form cannot record "essential" or joint ownership — no goal ever splits between spouses |
| W-0050 | high | Cannot create an account without consenting to Google Analytics + Awin affiliate tracking — a cookie wall, justified by copy that is factually untrue |
| W-0051 | high | Onboarding creates an unlinked spouse family member, calls it a "Linked account", removes Edit/Delete — and linking properly later leaves a permanent undeletable duplicate |
| W-0052 | **critical** | REGRESSION of W-0008 — creating any investment account 500s, `advisor_fee_percent cannot be null`; `nullable` validation rule added for a NOT NULL column |
| W-0053 | high | Completing a mirror will strands the user — "Generate Spouse's Will" exists only pre-completion, so the mirror pair is never created |
| W-0054 | med | Two tier caps, two gating philosophies — life events block before entry, detailed expenditure blocks after submit with a silent 403 (extends W-0011) |
| W-0039 | high | Holding form has no units/quantity input — all ten persona unit counts unenterable; **blocks holdings entry in Pass A** (raised by Batch A) |
| W-0040 | med | Whether a deliberate 100/0 joint split should be expressible — product-lead decision, not a blocker (raised by Batch A) |

**Note on numbering:** `W-0019` on the board is *not* from this run — it is
**CSJ direction of 2026-08-21 (mirror wills for married users)**, raised by another
agent at 08:32 while this premium sweep was in progress. It took the number first, so
my will-builder-bequests item was renumbered **W-0019 → W-0023**. All references in
these reports point at W-0023.

## Screenshots

`pass-a-web/` — 6 files, all from the R-02 verification phase.

| File | Evidences |
|---|---|
| `01-web-health-not-specified-W-0006.jpg` | W-0006 |
| `05-web-sarah-investments-your-share-100pct-95000.jpg` | W-0014 / W-0015 |
| `06-web-sarah-property-425000-correct-but-joint-with-self.jpg` | GREEN share + W-0016 |
| `07-web-sarah-wealth-summary-household-split.jpg` | GREEN household arithmetic |
| `08-web-estate-premium-gate-blocks-wills-trusts.jpg` | R-03 blocker |
| `09-m-sarah-investments-full-95000-no-share.jpg` | W-0015 on `/m` |
| `10-web-david-estate-iht-59280-23712-GREEN.jpg` | IHT correct (GREEN) |
| `11-web-david-will-builder-review-GREEN.jpg` | Will builder quality + W-0023 |
| `12-web-david-trust-185000-RPT-acronym-W-0021.jpg` | W-0021 |
| `13-web-david-expenditure-commitments-split-GREEN.jpg` | Joint commitments split correct (GREEN) |
| `14-web-sarah-mirror-will-names-herself-executor-W-0024.jpg` | W-0024 |
| `15-m-david-dashboard-levelup-gamification-BY-DESIGN.jpg` | Gamification working (Rule 12 carve-out — not a defect) |
| `16-m-david-chattels-132250-joint-shares-GREEN.jpg` | Chattel joint shares correct on `/m` (GREEN) |
| `17-m-sarah-chattels-60750-isolation-GREEN.jpg` | Spouse isolation + joint shares correct (GREEN) |

**Known gaps, not backfilled:** no screenshots of any form fill or submit (entry
phase predates the capture rule); David's investments screen quoted from the live DOM
but not captured; files are `.jpg` not `.png` because the capture tool emits JPEG;
numbering has gaps for the same reason.

## Open

**Handover R-08 is superseded by R-09.** Read
[`PASS-PLAYBOOK.md`](PASS-PLAYBOOK.md) before starting the re-run — it carries the
entry map, the precomputed expected values, the per-account matrix and a regression
check for every board item, so nothing needs re-deriving at the keyboard.

- **Batch A (Ownership & Net Worth) is COMPLETE** — eight items plus W-0025, with the
  W-0015 consolidation done properly. Its four structural claims were independently
  re-verified by reading the code (R-10). **Its `/m` half is untested by anyone** and
  its three behavioural claims need independent browser confirmation — playbook §4.1.
- **Pass A is blocked on W-0039** for holdings entry only; everything else can run.
- **Pass A restarts from zero** on genuine Playwright MCP, real pointer clicks, once
  the remaining three fix batches land.
- **Fix-ordering note: W-0036 must land before W-0035** — fixing W-0035 first would
  mask W-0036 on the retirement screen while it kept corrupting income tax, Personal
  Allowance and Child Benefit.
- **Must land before a faithful re-run:** W-0013, W-0014, W-0015 (joint ownership —
  the run's core purpose), W-0010 (dead-end blocks pension entry), W-0009 (blocks all
  10 holdings), W-0023 (blocks all 6 bequests), W-0036 (corrupts every income-derived
  figure for Sarah).
- **W-0020's acceptance cannot be met by this persona alone** — its £20,000 of
  charitable legacies is far below the £107,878 threshold on the full estate. See
  playbook §2.5 for the two-part verification.
- **Board ID collisions:** four items were written as W-0030–W-0033 and renumbered to
  W-0035–W-0038 after another agent claimed those numbers concurrently. Check the
  board immediately before claiming an ID.
- **Premium provisioning:** granted by team-lead 2026-08-21; must be re-granted on the
  fresh accounts after teardown.
- **Not started:** Pass B (`/m` via Fyn), Pass C (iOS via Fyn), and the registration /
  verification / onboarding / spouse-linking sweep.
- **iOS:** out of scope for the local passes by construction — it reads the csjones
  staging database, so Passes A and B pick up their iOS leg on the dev re-run.






## CSJ ruling, 2026-08-21 — the persona source of truth

**`tests/Persona/peak_earners.md` is the source for every figure entered and checked.
The PDF is not.** CSJ: *"this is solved, the markdown file, not the PDF, is your source
for the entry in the tests/Persona folder."*

This closes the "persona file self-contradictions" question that was pending a decision
(expenditure headline £2,500 against categories summing £2,450; a net-worth range that
only fits when pensions are excluded). Those discrepancies were traced to the source PDF
rather than the transcription — **the PDF is now out of scope, so they are not
discrepancies at all.** Where the two disagree, the markdown wins, full stop, and no
agent re-derives an expected figure from the PDF.

Applies to Pass A, B and C, both accounts, and to every expected value precomputed in
`PASS-PLAYBOOK.md`.
| R-19 | 2026-08-22 18:11 | Cycle 4 batch 1 — BMW PCP thread GREEN; 6 defects (mortgage share unenterable + hardcoded 50%; one mortgage four figures across two mechanisms; Total Balance Owed charges a third party; dashboard module cards wrong in opposite directions for the two spouses; 24h cache served a wrong dashboard and hid a same-morning fix; mortgage cannot be edited) | [R-19-cycle4-batch-1.md](reports/R-19-cycle4-batch-1.md) |
| R-20 | 2026-08-22 18:21 | Cycle 4 batch 2 — risk engine + projections. 4 findings: two contradictory emergency-fund runways (83.3 months "Excellent" vs 0 months "invest more conservatively"); a linked spouse's risk profile assessed as childless; the projection card contradicts its own printed rate (£132,500 to £316,777 in 10y on a stated 5.00%, no contributions); D-04's wrong totals reach the risk assessment. Withdrew one false positive. | [R-20-cycle4-batch-2.md](reports/R-20-cycle4-batch-2.md) |
| R-21 | 2026-08-22 18:35 | Cycle 4 batch 3 — entry-side (holdings + wills). W-0039 GREEN with a real form submit. 6 findings incl. CRITICAL D-16: a field labelled "(Optional)" is NOT NULL and blank submits print raw SQL to the user (blocks all holdings entry; ocf_percent carries the same latent fault). Joint account holds a placeholder "Cash" row for its whole £95,000. Sarah's will still names herself executor; "£10,000 to <blank>"; "100% to spouse (£1,716,780)" against a £739,280 estate. W-0217 reinforced: a subset account projects higher than the portfolio containing it. | [R-21-cycle4-batch-3.md](reports/R-21-cycle4-batch-3.md) |
| R-22 | 2026-08-22 18:45 | Cycle 4 batch 4 — pensions + second will. Two persona gaps CLOSED for real: David's three ISA holdings entered and exact, state pension entered (£221.20/wk → £11,502). 2 new findings: a Defined Contribution pension's holdings cannot be entered at all (no Holdings tab; blocks 3 persona holdings); pension detail shows Retirement Age 67 where the row and the page header both say 60 (an eighth hardcoded default, and the wrong field). D-15 strengthened — BOTH wills show the identical £1,716,780. Pension Monte Carlo verified well-behaved, which narrows W-0217 to the investment projection. | [R-22-cycle4-batch-4.md](reports/R-22-cycle4-batch-4.md) |
| R-23 | 2026-08-22 18:56 | Cycle 4 batch 5 — goals, fees, risk, projections. CRITICAL D-20: the projected value shown bears no relation to the simulation behind it — £172,500 displays as £4,650 at 5 years while the simulator's own 10th percentile is £88,914 and the correct 80% band is ~£105,113; localised to the hand-off between band extraction and render. D-21: changing risk moves the caption 5.41%→7.07% and the projected value not at all. D-19: every overdue goal reports "On track" (start_date auto-set after target_date + absolute diffInDays defeats the guard). GREEN: W-0008/W-0052 (adviser fee), W-0029 (past-dated goals); persona goal 6 of 6 entered. | [R-23-cycle4-batch-5.md](reports/R-23-cycle4-batch-5.md) |
| R-24 | 2026-08-22 19:07 | D-04 RE-VERIFIED GREEN (W-0238 landed mid-run). Both directions closed on both accounts and both surfaces: David SAVINGS £102,000→£99,750 and INVESTMENT £220,000→£172,500 (fraction); Sarah £28,780→£31,030 and £85,000→£132,500 (reach); her Defined Benefit card £0→£35,000/year. /m parity confirmed; the two halves of the dashboard payload now agree. Includes a correction to R-19's framing of the defect. | [R-24-cycle4-D04-reverified-green.md](reports/R-24-cycle4-D04-reverified-green.md) |
| R-25 | 2026-08-22 19:16 | Cycle 4 batch 6 — pensions + the D-05 cache test answered properly. D-23: invalidation is ONE LAYER DEEP — a pension update cleared mobile_dashboard_16 but left all three *_analysis_16 caches, so a "fresh" dashboard rebuilds from analyses up to 24h old. D-22: the pension risk control is silently discarded — StoreDCPensionRequest has no risk_preference rule so validated() strips it, proven with a control (platform_fee saved on the same submit; the investment form saves risk fine). D-17 narrowed — the API already accepts pension holdings, only the UI has no control. /m deep routes COULD NOT BE TESTED locally. | [R-25-cycle4-batch-6.md](reports/R-25-cycle4-batch-6.md) |
| R-26 | 2026-08-22 19:20 | Cycle 4 batch 7 — Letter to Loved Ones, a whole persona section untested until now. All four key contacts, employer HR and funeral wishes verbatim correct; W-0022 GREEN. D-24 (HIGH): the Letter computes six totals client-side at 100% (LetterToSpouse.vue:981-986) — a FIFTH un-shared mechanism that survived W-0238, in a printable PDF addressed to the bereaved spouse, telling her the estate holds a £295,000 property of which £177,000 is Mike Barrett's. Stale cache ruled out by clearing all six keys first. D-25 (LOW): annual_allowance_used_gbp holds a percentage. | [R-26-cycle4-batch-7.md](reports/R-26-cycle4-batch-7.md) |
| R-27 | 2026-08-22 19:25 | Cycle 4 batch 8 — expenditure. CORRECTS A RUN BASELINE ERROR: the persona's 15 categories sum to £2,500, matching its own headline — the playbook's £2,450 and its "persona contradicts itself" note are an arithmetic slip. The real £50 gap was Healthcare & Medical entered as £50 (the field's own placeholder) instead of £100; corrected through the form. D-26 (HIGH): in declared "Joint (50/50)" mode an edit updates only the editing spouse's half — David £1,250 vs Sarah £1,225, household £2,475 not £2,500; a residual of W-0190. | [R-27-cycle4-batch-8.md](reports/R-27-cycle4-batch-8.md) |
| R-28 | 2026-08-22 19:29 | Cycle 4 batch 9 — income. FOUR board items verified GREEN with the arithmetic checked by hand: W-0173 (rental reaches the spouse at 50%/40%), W-0175 (rental stated once), W-0189 (definitions chain adds up; Personal Allowance £0 and Annual Allowance £60,000 both correct), W-0176 (linked spouse income £120,000). W-0221 appears addressed. Income tax and NI reconcile exactly. D-27 (MED): "Net Income (after tax, pension contributions...)" does not deduct the £11,600 pension — Disposable Income overstated by £11,600. D-28 (LOW): "Earned Income £159,290 · NI Applies" includes rental. Section 24 credit of £780 COULD NOT BE VERIFIED — routed to tax-compliance-reviewer. | [R-28-cycle4-batch-9.md](reports/R-28-cycle4-batch-9.md) |
