---
type: handover
mode: session-end
date: 2026-08-23
session: 1
repo: fynla
branch: wip/persona-cycle4-snapshot
---

# Session Handover — 2026-08-23, Session 1

## Where things stand

**Persona run `peak_earners` cycle 4 is complete on the fix side.** Fourteen fix batches ran overnight; thirteen were verified in a live browser and three cleared statutory `tax-compliance-reviewer` gates. Around 110 board items were raised, 138 sit at `handoff` awaiting quality-lead, and roughly 60 defects were fixed and verified.

**Nothing is running. No agent is live. The tree is dirty — 368 uncommitted files, 148 changed since the last commit — and ALL of cycle 4's fix work is in it, uncommitted.** That is the single largest risk in this handover (see Priority 1).

**Six board items are formally `blocked_by: [csj-decision]` and cannot progress without CSJ.** They are the first thing the next session must surface. One is a verified CRITICAL security defect on production-shaped code.

**The persona tester is stopped** and its restart brief is written. Per CSJ's Rule 23 (fixers and testers never overlap), the next phase is: fixers down → tester restarts **from the beginning**.

---

## Priorities for the next session

### 1. BLOCKED ON CSJ — ask these four before doing anything else

The next session **must put these to CSJ at the start of the day.** They are ranked by consequence. Do not begin new fix work until at least Decision 1 has an answer — it is a live security defect.

---

#### DECISION 1 — The spouse-linking CRITICAL. **Ask first, every session, until answered.**
**Items: W-0347 (critical), W-0348, W-0349, W-0350, W-0344.** All `blocked_by: [csj-decision]`, unclaimed, deliberately unfixed.
**Memory: `project_spouse_linking_critical_unfixed.md`.**

**One authenticated POST plus a victim's email address gets an attacker that person's complete financial profile AND write access to their account.**

`POST /api/user/family-members` → `SpouseLinkingService::linkExistingSpouse()` (`app/Services/Onboarding/SpouseLinkingService.php:226-256`) **writes the VICTIM's `users` row** — `spouse_id`, `marital_status`, `annual_employment_income` (attacker-supplied), five address fields. Then `createSpousePermissions()` (`:476-486`) writes `status => 'accepted'`, `responded_at => now()` on **BOTH** permission rows — no request, no acceptance, nothing from the victim.

The same 201 returns `'spouse_user' => $spouseUser`, a **raw Eloquent User** (`FamilyMembersController.php:219`). `$hidden` strips password, tokens, MFA secret, NI number — **and nothing else**: email, date of birth, address, phone, occupation, employer, every `annual_*_income`, monthly/annual expenditure plus all 21 category columns, health status, smoking status, domicile.

**Only precondition: the victim's `spouse_id` is NULL** — every unlinked account. `StoreFamilyMemberRequest::authorize()` returns `true`; the email is validated as an email and nothing more. **No proof of control of that address at any layer.** Also reachable via Fyn's `capture_spouse_details` tool (`CoordinatingAgent.php:1866`).

**Every correctly-gated site in the application is defeated by this one endpoint** — a reciprocity gate and an accepted-permission gate are both SATISFIED, because the server forges both sides.

**Two compounding facts:**
- `NetWorthController.php:78` carries `// Check if data sharing is enabled (you can add permission checks here)` above a full net-worth and liabilities disclosure. The gate was marked and never installed.
- **`preventLazyLoading` is OFF in production** (`AppServiceProvider.php:216`), so several raw `$user->spouse` reads **throw 500 on dev/staging and SUCCEED in production** — **a csjones test cannot surface them.**

**THE ASK:** this needs a real accept/decline flow — invite, token, expiry, notification — touching onboarding, Fyn's capture tool and the email pipeline. **Specified work, not a patch.** A half-fix leaves a system that looks gated and is not. **The principle to fix against: no account's row should ever be written by another account.**

**Verified against the code by the coordinator, not taken on an agent's word.**

---

#### DECISION 2 — A recommendation that leaves beneficiaries £37,891 worse off, labelled a "saving"
**Item: W-0462 (high), gate `compliance-lead`. Raised by `tax-compliance-reviewer` as verdict condition C2.**
**Verdict: `workforce/ops/handoffs/W-0451/tax-compliance-reviewer-verdict-2026-08-23.md`.**

The estate screen tells David: *"increase charitable bequests by £112,878 … Saving £74,987."* **The figure is correct.** Giving away £112,878 to save £74,987 in tax leaves the beneficiaries **£37,890.72 worse off.** The word "saving" stands alone with no disclosure of the net effect.

The reviewer derived the break-even: **`S < E·(r_s − r_r)/(1 − r_r)`** — a shortfall under **6.25%** of the chargeable estate at 40/36. **Below that line the recommendation is genuinely good advice.** The sentence should be able to say which side of the line the user is on. Break-even is already encoded in the item's acceptance.

**THE ASK:** (a) does this need product sign-off before compliance work starts, and (b) may the next session route it to `compliance-lead`? It has a Consumer Duty edge and was deliberately not patched by an engineer.

---

#### DECISION 3 — Uncapped Business Property Relief. **This is live law being missed today.**
**Items: W-0362 (high, from the tax ledger) and W-0091 (high, pre-existing — same defect, filed earlier).**

Business Property Relief is applied **flat and uncapped**, while the **£2.5m cap has been in force since 2026-04-06** — i.e. current law, not upcoming. Roughly **£700k understated** on a £6m business. **Agricultural Property Relief is absent entirely.**

**THE ASK:** priority and sequencing. This is not a decision about *whether* — it is wrong — but it needs a tax-compliance-gated batch and CSJ's call on when. Note W-0091 and W-0362 are the same defect filed twice; one should be closed as a duplicate.

---

#### DECISION 4 — Four queued rulings, each small, each blocking an item
These are genuine product/modelling calls with no single right answer. **Ask them together.**

| Item | The question |
|---|---|
| **W-0340** | An **unmarried linked couple** gets a headline taxing one estate beside a projection pooling two — against a single nil rate band and no spouse exemption, neither of which they get. What should the product model? |
| **W-0392** | `is_iht_exempt` marks Business Property Relief assets, but the relief removes an asset from the **tax**, not from the **estate** — so the will page understates a business owner's estate. Which is right? |
| **W-0426** | **Should the Letter to Loved Ones be premium-to-read?** It is currently write-gated and read-open (`api/user/` sits in `READ_ONLY_EXCLUDED_PATHS`, which returns early before the capability check). **If yes: narrow that path entry, do NOT delete it — deleting it costs a churned paying customer access to their own profile.** |
| **W-0442** | The `/m` holdings display needs extending a payload carrying a **`contract_version`** read by `/m` **and native**. That is a version decision with a release consequence, not a display fix. |

**Also unowned and needing a steer, lower priority:** **W-0258/W-0259** — the investment projection card's headline number is the **20th percentile**, and p20 is **hump-shaped**, so "higher risk → higher return at every percentile" is **not a property a correct Monte Carlo has.** The remaining "inversion" is correct behaviour displayed badly. The question is whether that card shows the median, a range, or p20 with an explanation. Measured table in W-0259.

---

### 2. Commit the tree — 368 files, all of cycle 4's fix work, uncommitted
**Not yet done because CSJ was not asked.** The last commit `d5fe9f9f7` is a wip snapshot from 20:22 on 22 Aug and **its message was already corrected once** — it contains cycle 1–3 **and** the W-0238/W-0239 fix, despite reading as a pre-cycle-4 baseline. **There is no clean pre-cycle-4 commit; the nearest is `496d722f1`.**

Everything since is uncommitted: ~60 verified fixes across estate, protection, retirement, goals, expenditure, investment projections and validation, plus ~110 board items, 8 branch documents and the governing-file entries.

**Ask CSJ, then commit.** A dirty tree of this size is a trap.

### 3. Restart the persona tester — from the beginning
Per **Rule 23** (fixers and testers never overlap — CSJ, 2026-08-22). The tester was stopped mid-cycle-4 so fixers could have the tree.

**Its restart brief is already written** at the foot of `workforce/ops/queue/cycle4-fix-queue.md`: every figure that moved, the environment traps that cost hours, the named checks carried forward, and the things it must not re-raise. **The next session should not re-derive any of it.**

### 4. Quality-lead has 138 items at `handoff`
**Before certifying any of them, read the note in the queue file:** `status: handoff` is a moment-in-time claim, and an acceptance list can be entirely green while the criterion that matters has no line on it. A list of items lacking browser evidence is in that file — **absence is a prompt to look, not a verdict.**

**One specific warning:** screenshots `162-` to `167-` in `tests/Persona/20-08-2026_run/pass-a-web/` are **not** evidence of the final state — `163-` photographs a defect that was later fixed. Both W-0451 and W-0452 handoff notes say so at the top.

### 5. Unowned high-severity work, ranked
- **W-0461** — the Rule 2 sweeps never entered the frontend; nine instances, seven files, and **no guard in the codebase moves a configured rate and asserts on a Vue template.** The acceptance that matters is the guard, not the nine fixes.
- **W-0325**, **W-0327** — a joint-property 500 (fixed this session, needs verification) and a savings-rate edit that silently does nothing.
- **W-0154** (critical, pre-existing) — one household, two different inheritance tax bills depending on who is logged in.
- **W-0263** — 18 validation rules permit values their column cannot store; only 2 fixed. **Do not blanket-cap** — for most the *column* is wrong.

---

## Context to load

- `workforce/ops/queue/cycle4-fix-queue.md` — **the single most important file.** Carries the fix queue, the traps, the non-regression baselines, the certification warning, and the tester restart brief.
- `workforce/ops/FORMATS.md` — process rules written or corrected this session: the board is prior art; consolidations stop at the edge of the diff; completion notes record the check not the count (**and the check decays too**); the single-browser protocol; the coordinator's log obligation.
- `app/Http/CLAUDE.md` — seven rule-versus-schema axes, the read-boundary/join patterns, and the frontend sweep blind spot. All added this session.
- `tests/CLAUDE.md` §4 — now **five** test-blindness variants (Mock, Clamp, Fixture, Collision, Decoy) plus the subject-blindness of reconciliation checks and the `setData` injection trap.
- `workforce/ops/handoffs/W-0451/tax-compliance-reviewer-verdict-2026-08-23.md` — the most detailed of the three statutory verdicts; its C2 is Decision 2.
- `workforce/branches/fixes/HANDOVER-fix-cycle4-wills-2026-08-23.md` — a Rule 22 agent handover with dead ends worth not re-walking.

---

## Completed this session

**Fourteen fix batches. Highlights, all browser-verified unless noted:**

- **A £95,000 silent data-loss path** — collapsing a *default-collapsed* panel and pressing Update sent `holdings: []`, which the controller read as "delete them all" and replaced with an auto-created Cash row. **It had already destroyed real data in this run** (`holdings.id=33`). Fixed both frontends; the backend contract question is filed.
- **A projection showing £172,500 as £4,650** at the 5-year horizon — a cache key naming neither capital nor risk, serving a £47,500 simulation 22 hours later. Now £86,944. Same root cause fixed a risk-change that moved the caption and not the figure.
- **A projection compounding £1,667/month of contributions nobody entered** — the estimator assumed the full ISA allowance while the same card printed "Monthly Contribution —".
- **A `×20` capitalisation of Defined Benefit pensions live on `/m` and native** (the option CSJ rejected), making an asset list sum to £1,666,780 against a stated £861,780 with percentages totalling **193%**. Now reconciles at 100%; headline net worth unmoved.
- **Sarah's estate plan assumed she had no life cover** while insured for £500,000 by a joint-life policy; her `/m` protection showed **£0 above the £500,000 policy it was counting**, with three false HIGH shortfalls derived from the zero.
- **Every overdue goal reported "On track"** and the page congratulated the user; **a declared 50/50 expenditure split drifted** because the backend halved one row and trusted the frontend to make a second request it never verified.
- **The Letter to Loved Ones handed a third party's £177,000 to the estate**, in the cards *and* in the exported PDF. Verified by exporting the document.
- **A decision trace publishing £19,580 beside figures that subtract to £34,351** — 43% wrong, on a surface whose purpose is auditability. Four answers existed; the published one was furthest from right. Now £74,987 and reconciles from the printed figures.
- **CSJ's mortgage-share ruling implemented** — household debt £305,000 → £293,000, with the property card's equity line reconciling on screen for the first time.
- **Pension holdings made enterable** (the feature existed, routed, with a written service and zero consumers, gated behind a tab that required the thing it creates).

**Three statutory `tax-compliance-reviewer` gates cleared**, each with conditions discharged.

**Governing-file entries added** — see Context to load. Several corrected rules written earlier the same night.

---

## Verification state

- **Thirteen of fourteen batches browser-verified**, both accounts, web and `/m` where the surface exists.
- Test suites green per batch (typically 300–900 Pest plus Vitest); **no full-suite run** — Rule 17, and batches used separate databases.
- **Pint clean** on every touched path.
- **Deployed nowhere.** No commit, no PR, no push. `public/build/` and `public/m-build/` were rebuilt locally during the session.

**NOT verified — carried forward honestly:**
- **iOS: not built, not launched, not looked at, not claimed.** Two Swift items filed with the changes written out (W-0311, W-0243, W-0416).
- David's `/m` liabilities screen — never rendered; figure confirmed at source only.
- The dashboard's second "All goals on track" banner — the card renders **Locked** on a tier-gated account.
- Four branches in `F-0032` and four in `F-0033` — **the persona cannot reach them**; test-covered only, named individually in each branch doc.
- The investment holdings table is **unreachable with data on this build** for three separate reasons (W-0442).

---

## Decisions and dead ends

**CSJ decisions taken this session — do not re-litigate:**
- **Mortgage liability follows the PROPERTY SHARE, not the borrowers.** Recorded in full on W-0228. Accepted limitation, stated explicitly: **this model cannot express a mortgage in one spouse's sole name against a jointly-owned property.** Do not add a borrower-split field.
- **Defined Benefit pensions are EXCLUDED from net worth, and the exclusion is disclosed on screen.** W-0241. No `transfer_value` column, no capitalisation multiple, and no user's headline number moves.
- **`RetirementAgent` fixed properly rather than worked around** — W-0244; provision and capital are separate facts.
- **Rule 23 issued: fixers and testers never run at the same time.** And **max three agents at once** — both now in memory.

**Dead ends — do not re-walk:**
- **The "IHT double count" does not exist.** Two agents independently proved it: `where('user_id')` never matches `joint_owner_id`, so the queries are disjoint. £305,000 either way. W-0280 §1 is withdrawn.
- **Do not run the mirror-will generator's swap as a repair** — over a *correct* will it makes the primary their own executor.
- **Do not "fix" `EstatePlanService:504`** — a legitimate `TaxDefaults` fallback.
- **`is_emergency_fund` is a designation, not a definition.** A household with £130,780 and no ticked boxes does not have zero runway.
- **Do not add a borrower-split input, a `transfer_value` column, or a capitalisation multiple.** All three explicitly out of scope by CSJ ruling.

---

## Things that will bite you

**Environment traps, every one of which cost time this session:**

| Trap | What it looks like | Do this |
|---|---|---|
| **Session-state relays** | A coordinator says who is signed in | **Ignore it — seven were wrong in one night.** `GET /api/auth/user` **on the token in use.** |
| **`fynla-state.auth.user`** | Reports Sarah; the token is David | **Never use it.** It went stale across an account switch. |
| **Two token stores** | `/m` and desktop disagree | `sessionStorage.auth_token` and `localStorage.m_scaffold_token` **can hold different users at once.** |
| **HMR remount** | Field shows your text, framework state empty, submit fires nothing | Another agent editing `resources/js/` remounts mid-interaction. **Fill-and-click atomic in ONE `browser_evaluate`.** |
| **Wedged CDP input** | `fill()` works, `click()` produces **zero** events, `<a href>` won't navigate | **Tooling, not a defect. New tab.** |
| **Stale `/m` bundle** | `/m` looks correct | It **fails by agreeing with you** — figures come from the backend either way. **Grep the bundle for a string only your change could produce**, and confirm the page serves the file you grepped. |
| **Estate cache** | Your fix appears to do nothing — or to work | `EstateAgent::analyze()` stores under a key the invalidator never forgets (**W-0381**). **Clear `estate_analysis_{id}` by hand before every estate reading.** |
| **Persona password** | `password` fails | **`Password1!`** for both. **One attempt** — shared lockout counter. |
| **Test databases** | 71 failed / 8 passed with 23 assertions | Two processes on one database. **One `laravel_testing_*` per agent**; re-run in isolation before believing a red. |

**~~`workforce/registry/` does not exist`~~ — WRONG, corrected 2026-08-23 10:55.** The registry exists in full at **`workforce/core/registry/`** (all nine files plus `sources.md`; `capabilities.md` last updated 2026-08-21). Two agents missed it because every agent definition except `cartographer.md` cited the bare relative `registry/capabilities.md`, which resolves to nothing from the repo root. Paths made absolute; **W-0415 closed invalid.** No prior-art check was ever short a source.

**The event log knows ~13 of 69 items** — agents report in messages and do not emit. **Require log events at dispatch**; their absence is a defect, not a preference.

---

## Tech debt deferred

Not run as a separate pass — the session *was* a defect-fixing run, and everything found is on the board rather than in a scratch list. The debt worth naming:

- **Five dead code sites**, four carrying their own copy of a live rule — the shape that gets the wrong rule copied. Includes a byte-identical share-rule copy with **zero callers**, kept correct by hand through every ownership change.
- **`formatAssetType` has eleven copies** across four directories (W-0443).
- **Four phantom columns** read this cycle — `db_pensions.transfer_value` (twice), `mortgages.end_date` (it is `maturity_date`), `properties.property_use`. A collection read returns null silently; a query-builder read throws.
- **`rent` and `utilities` never persist** from the expenditure form — posted, absent from both `validate()` lists.
- **`months_remaining` is a phantom key** — the goals plan quietly runs on 12 months.

---

## From the vault sync (post-handover)

**Four Current State docs are stale for modules this session rewrote.** Root `CLAUDE.md` says to read the relevant vault doc **before working on any module** — these will actively mislead:

| Doc | Age |
|---|---|
| `Current State/EstatePlanning.md` | **110 days** |
| `Current State/Investment.md` | **110 days** |
| `Current State/Protection.md` | **108 days** |
| `Current State/Savings.md` | **108 days** |

Estate, investment, protection and savings all changed materially overnight — new estate figures, a consolidated charitable position, life-cover reach, projection fixes, the emergency-fund definition. **Refresh these before the tester restarts, or it will read a four-month-old description of code that changed last night.**

**Codebase metrics drifted since 2026-08-13** and were refreshed in the vault: PHP services **462 → 519**, controllers **134 → 147**, models **138 → 151**, Vue components **679 → 690**. Worth confirming root `CLAUDE.md`'s table matches.

**`MEMORY.md` indexes 111 of 112 memory files**, but roughly 80 older files are on disk without an index line — non-critical (they remain discoverable), zero dead references.

## Branch and deploy state

- **Branch:** `wip/persona-cycle4-snapshot` (created this session; `dev` + one wip commit). **Stay on it.**
- **Unpushed commits:** none — but **368 uncommitted files**, containing all of cycle 4.
- **Deploy status: nowhere.** Not on csjones, not on prod. No PR opened.
- `public/build/` and `public/m-build/` were rebuilt locally and are current.
