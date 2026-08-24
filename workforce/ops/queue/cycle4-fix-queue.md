# Cycle 4 fix queue — dispatch as slots free (MAX 3 RUNNING)

**Hard rule (CSJ 2026-08-22): never more than three agents at once.** These briefs are
written and ready; dispatching one costs a single Agent call. Do not re-derive them.

## RUNNING (3/3 — full)
| Agent | Scope | ID block |
|---|---|---|
| `fix-cycle4-dashboard` | W-0228 mortgage/property share (CSJ ruling), W-0236 enterable share, W-0237 £365k total, + queued `userShareFraction` guard | W-0241–W-0250 |
| `fix-cycle4-projections` | **D-20 CRITICAL** £172,500 renders as £4,650, D-21 risk change doesn't move projection, W-0217 | W-0251–W-0260 |
| `fix-cycle4-validation` | **D-16 CRITICAL** "(Optional)" field NOT NULL leaking raw SQL, D-22 silent field drop, W-0242 live 500 | W-0261–W-0270 |

## PRIORITY INSERT — W-0263: validation rules permit values their column cannot store

**Found 2026-08-22 while browser-verifying W-0261. A third defect class, invisible to
both earlier sweeps.** Nullability is correct, the field is validated, the field is
fillable — every layer looks right in isolation. It only appears when you compare the
rule's **RANGE** to the column's **PRECISION**. *"The rule and the column disagree" has
at least three shapes, and sweeping for one says nothing about the other two.*

**18 rules across 11 requests.** Full table and ranking in W-0263. The top three:

1. **`mortgages.fixed_interest_rate` / `variable_interest_rate`** — `decimal(5,4)`
   (physical max **9.9999**), rule says `max:100`. **Any mortgage rate of 10% or more
   500s today**, on a core field of a core module. That is most of British history, and
   plenty of current adverse-credit and buy-to-let products. **This is a live crash, not
   a latent one.**
2. **`savings_accounts.interest_rate`** — rule advertises 20, column holds < 10.
3. **`investment_accounts.current_ownership_percent`** — a **percentage** field on
   `decimal(5,4)`. That looks like a plainly wrong column type; check whether it stores
   a fraction before assuming.

**Only 2 of the 18 were fixed** (the two in the fixing agent's own scope), deliberately.
Its reasoning, which should be honoured: **capping a mortgage rate at 9.9999 turns a
crash into "you may not enter a 12% mortgage" — a wrong answer delivered politely.**
For most of these **the column is what is wrong**, so the fix is a migration plus a
per-field product call, not a rule edit. Do not blanket-cap.

**Dispatch this ahead of the batches below** — a core field that crashes on an ordinary
input outranks a wrong figure on a detail page.

---

## PRIORITY — Sarah's estate plan assumes she has no life cover (MEASURED)

`EstateAssetAggregatorService::getExistingLifeCover():277`

Policy 7 is David's, `joint_life = true`, **£500,000**. `getExistingLifeCover(Sarah)`
returns **£0**, while `LifeCoverReach` correctly reports her covered for £500,000.

**Sarah's entire estate plan is built on the premise that she has no life cover — on the
one product whose purpose is covering both of them.** Measured against the live persona,
not read off the code.

**Pair with W-0278** in the same batch — `LifeCoverReach` reads a **deleted** partner's
policies. Same module, and the second is a disclosure bug. W-0278 was found by reading
`LifeCoverReach` as the *model* for a correct implementation, not by hunting for a bug.

**Prior art:** `LifeCoverReach` is the correct reader and already exists — it finds the
second life through `users.spouse_id` because `life_insurance_policies` has no
`joint_owner_id`. **Route to it; do not build a second.** Six consolidations this cycle
have each found the right reader already existed.

---

## CLOSED — the "IHT double count" does NOT exist. Do not re-dispatch it.

**Two agents independently proved it cannot occur.** `where('user_id', $user->id)` never
matches `joint_owner_id`, so the user's query and the spouse's are **disjoint** and no row
can match both. Measured both ways: **£305,000 either way**, identical to the aggregator's
reach-and-share answer. **The household figure was already right.**

**W-0280 §1's "£190,000 of a £95,000 record" is withdrawn.** The agent that raised it
retracted it unprompted after measuring, and named why it got through: *the right answer
and the wrong answer were the same number at the level it looked at* — the **Collision**
variant applied to an analysis rather than a test.

**The rule that came out of it, which applies to the whole census:**
**a claim derived from reading code is a HYPOTHESIS until it is measured.** Six lines of
tinker settle most of them. `W-0280` and `F-0024` §10 are being re-labelled line-by-line
as `measured` or `unverified`; the `InvestmentAccount`-first ordering stands as **a place
to look, not 59 known defects.**

**The real defect underneath, already authorised and in flight:** `projectProperties()`
sums primary-owned property at 100%, pulling in **£177,000** of a third party's
tenants-in-common share. Headline estate £1,393,000 vs projection base £1,570,000 — two
answers in one response. **Projected Inheritance Tax liability falls £205,198.10.**
Tax-compliance review attached.

**Note for whoever reads `5278a2457`:** routing to the aggregator does **not** reverse it.
That commit restored `where('user_id')` to stop joint property double-counting across the
spouse pair — which it does — but achieved it by taking primary rows at 100%, which is
where the third party gets in. The aggregator prevents both. **It completes that decision.**

---

## QUEUED — dispatch in this order

### 1. Risk engine reach/fraction — `W-0271–W-0280`
**Scope (exclusive):** `app/Services/Risk/AutoRiskCalculator.php` + tests.
- **D-07 (HIGH)** Two mechanisms answer "how much emergency fund?" and contradict absolutely. `SavingsAgent` → David 81.4 months "Excellent"; `AutoRiskCalculator` → **0 months**, "Less than 3 months". `AutoRiskCalculator.php:368-370` counts only `is_emergency_fund`-flagged accounts; **all six household accounts have the flag at 0**, so it sees £0 against £130,780. The losing definition **feeds the risk level that drives every projection**. Route to `CrossModuleAssetAggregator::calculateCashTotal()`; do not build a third.
- **D-08 (HIGH)** Sarah's risk profile reads **"Dependants · 0 — No dependants means you can afford more risk"**. She is the mother of the two dependent children. `AutoRiskCalculator.php:277-279` is `where('user_id',…)`-only. Reuse the `LifeCoverReach` spouse traversal (W-0186), don't write a second.
- **D-10 (MED)** `risk_profiles.factor_breakdown` holds the pre-W-0238 wrong totals (David 220000, Sarah 85000) and `pensions_total 0` for a Defined Benefit holder. Confirm it now reads corrected figures; decide recompute vs expire.
- **Test warning:** persona has `is_emergency_fund = 0` on every account — a fixture built from it exercises only the zero branch (`tests/CLAUDE.md` §4).

### 2. Wills & estate figures — `W-0281–W-0290`
**Scope (exclusive):** `WillPlanning.vue`, `WillController.php` + tests.
- **D-15 (HIGH)** Both wills show "100% to spouse (**£1,716,780**)" = household net worth minus David's pensions. Actual: David £1,477,500, Sarah £739,280. **One figure shown to two people, wrong for both** (Sarah's by 2.3×). `WillPlanning.vue:512-514` ← `iht_summary.current.net_estate` at `:627`. Mobile dashboard returns 739280 for the same user — **two net-estate mechanisms, £977,500 apart.** Delete one.
- **D-13 (HIGH)** Sarah named as her own executor (`wills.id=12`). W-0024's exact symptom, W-0024 is `handoff`. **Generate a fresh mirror will to determine residue vs unlanded** — do not infer. Part of W-0024 did land (her charity is correct).
- **D-14 (MED)** "Specific Gifts: £10,000 to" — no recipient. Both charities stored `beneficiary_type=individual`, likely the cause. Both accounts.
- Persona's four children's percentage bequests missing — determine unfinished entry vs a save path dropping percentage bequests.
- **Test warning:** assert David's and Sarah's figures **DIFFER** and each equals its own owner's estate.

### 3. Goals & expenditure split — `W-0291–W-0300`
**Scope (exclusive):** `GoalCalculationService.php`, expenditure services/components + tests.
- **D-19 (HIGH)** Every overdue goal reads "On track"; page says "All goals on track!". `GoalCalculationService.php:56-81`: `start_date` auto-set to today lands **after** a past `target_date`, and `Carbon::diffInDays()` returns an **absolute** value (verified: 21, not −21), so the `$totalDays <= 0` guard at `:72` never fires. **Direct regression from W-0029's fix** — fix the maths, do NOT re-block past-dated goals.
- **D-26 (HIGH)** Joint (50/50) reads 50.5/49.5 after an edit; household £2,475 vs £2,500. `expenditure_profiles` holds **one row** (David's); Sarah's half is derived and doesn't recompute, and the household is computed as the sum of two halves so it **inherits the error instead of being the source of truth**. W-0190's residual. Structural fix: household is the source, halves derive from it.
- **BASELINE:** persona expenditure is **£2,500 / £1,250**, annual **30000**. The £2,450 in older artefacts was a playbook transcription error (Healthcare & Medical £50 vs persona £100) — corrected 2026-08-22.

### 4. Letter to Loved Ones & income labels — `W-0301–W-0310`
**Scope (exclusive):** `LetterToSpouse.vue`, income/tax page components + tests.
- **D-24 (HIGH)** The Letter is a **fifth un-shared mechanism** and survived W-0238: savings £102,000 (vs £99,750), investments £220,000 (vs £172,500), properties £1,570,000 (vs £755,500), liabilities £365,000 (vs £170,500). `LetterToSpouse.vue:981-986` — six client-side `reduce()` calls at 100%; per-item too (`:338`, `:486`). Stale cache already ruled out. **Worse than the same arithmetic elsewhere:** addressed to the bereaved spouse, **hands Mike Barrett's £177,000 to the estate**, and exports to PDF (`buildFinancialHtml():1503-1506`) where wrong figures outlive the fix. Delete the client-side arithmetic, read the shared aggregator — same move as F-0022.
- **D-27 (MED)** "Net Income (after tax, pension contributions and tax credits): £102,496" does **not** deduct the £11,600 pension despite the label. Take-home is £90,896; **Disposable Income overstated by £11,600**, and it drives affordability judgements. Trace consumers.
- **D-28 (LOW)** "Earned Income £159,290 · National Insurance Applies" includes rental profit. Computation beneath is correct; the header is a factual mis-statement.
- **DO NOT TOUCH:** the £780 Section 24 credit — tax-rules question for tax-compliance-reviewer. Note `mortgages.monthly_interest_portion` is null on all three rows.
- **Preserve:** this page's explanatory copy is the best in the app; fix labels without flattening it.

### 5. Retirement UI — blocked on `RetirementController` (validation agent holds it)
- **D-17 (HIGH)** Defined Contribution pension holdings cannot be entered — no Holdings tab, no route. Schema supports it (`holdables`), and `RetirementController::updateDCPension():449-450` **already accepts `$data['holdings']`** — only the client control is missing. Blocks 3 of the persona's 10 holdings; a £320,000 SIPP reports 0.00% fees.
- **D-18 (MED, attach to W-0196)** `PensionDetailInline.vue:483-485` renders the **user's** target age under a label reading as **this pension's**, and falls back to a hardcoded **67** where the row says 60. **An eighth default W-0196's inventory of seven does not list.**
- **D-12 (MED)** Holdings table never shows Units, Purchase Price, Current Price or Purchase Date — captured and stored, displayed nowhere. The display half of W-0039.

### 6. Blocked on `MobileDashboardAggregator` (ownership agent holds it)
- **W-0241** — CSJ ruling recorded: **exclude Defined Benefit from net worth and SAY so on screen.** Delete every `transfer_value` reader; add no column, no multiple; **no user's number moves.**
- **W-0244** — CSJ ruling recorded: **fix properly now.** `RetirementAgent` returns the pension facts with a null projection; **delete the W-0238 card-level workaround**; fix module page, plans and Fyn retirement context.

### 7. Low / unassigned
- **D-06 (LOW)** A mortgage can be deleted but never edited — belongs with the ownership agent's `PropertyForm.vue` work (**W-0240**).
- **D-25 (LOW)** `annual_allowance_used_gbp` holds a percentage (38.67). `PensionDerivedColumnCalculator.php:68-79`. Latent, nothing reads it. Same family as W-0221.

---

## Carried to the persona tester's restart pass — named checks, do not lose

**1. David (16) — `/m` liabilities screen must render £170,500.**
Transferred from the W-0228 ownership batch, which could not verify it: David's `/m`
logins bounced to the desktop SPA because his desktop session was live in the same
browser (the known bridge behaviour in `reference_m_verification_path`). The agent
recorded **"I COULD NOT VERIFY THIS"** rather than inferring it, and that stands.

What IS proven: the figure at source (`NetWorthService::getCachedNetWorth(16)
.total_liabilities = 170500`), and that Sarah's `/m` liabilities screen renders the
backend figure faithfully with components that sum (£32,500 + £90,000 = £122,500).
**Unproven: only that David's `/m` screen renders it.** Log in inside `/m` at
`/m/app/login` with no desktop session in the same browser.

**2. Do NOT re-verify £182,500 against W-0187.** Its verification text reads
*"£182,500 — exactly the figure the item expected"*, which was true when measured and
is now superseded by CSJ's ruling (correct figure **£170,500**). W-0187 is deliberately
left as a historical observation. Only `F-0021` was corrected, because only it signed a
figure off as *correct* rather than recording what it saw. The same applies to W-0206,
W-0136, W-0138 and W-0172.

**3. Expect these as the new correct figures**, not as regressions:
David's mortgage share **£48,000** · David's mortgages **£170,500** · household debt
**£293,000** · David's net worth **£1,489,500** (was £1,477,500) · Manchester row
labelled **"Tenants in Common (40% yours)"**, not "Joint".

**4. Mortgage 16 still stores `joint 50%` on purpose.** The reader resolves through the
property, so the stored contradiction changes no figure; new and edited mortgages mirror
their property so it stops being created. **Repairing that row will move nothing** —
do not conclude the fix is broken from the raw row.

---

## For QUALITY — baselines invalidated by cycle 4, do NOT read movement as regression

**`F-0018`'s `projected_investments` = £2,603,695 is an INVALID baseline.** That figure
was Sarah's inflated projection (built on a fabricated £1,667/month ISA contribution
nobody entered — W-0254) plus David's stale cached one (W-0251). F-0018 reconciled the
whole projected estate to it. **Re-derive it; do not treat the movement as a regression.**

**Sarah's Pensions card moves £805,000 → £0 on web, `/m` and native.** That is CSJ's
W-0241 ruling taking effect — a live `×20` capitalisation (the option CSJ rejected) is
being deleted. **Not a regression.**

**Household debt is £293,000, not £305,000**, and David's mortgage share £48,000, not
£60,000 — CSJ's W-0228 ruling. `F-0021` is corrected; W-0187, W-0206, W-0136, W-0138 and
W-0172 are deliberately left as historical observations. **Do not re-verify £182,500
against W-0187.**

**Projection figures all move** (D-20 was a cache serving £47,500-based simulations
against a £172,500 portfolio). David 5y £4,650 → £86,944 · 10y £217,451 → £303,947 ·
20y £528,482 → £598,168 · 30y £767,649 → £858,733. Sarah 36y p20 £1,577,731 → £261,740.

**Expenditure baseline is £2,500 / £1,250**, not £2,450 / £1,225 — a `PASS-PLAYBOOK.md`
transcription error, corrected 2026-08-22.

---

## FOR CSJ — W-0258 / W-0259: an acceptance criterion that cannot be built

**W-0217 acceptance 2 says "higher risk → higher return at EVERY percentile". That is
not a property a correct Monte Carlo has**, and building it would mean breaking the
model. Median and upside rise monotonically with risk; **p20 is hump-shaped** — it peaks
at Medium over 10 years and Upper-Medium over 30. Measured table in W-0259.

**The product consequence is real, not academic:** the projection card's one headline
number **is** the 20th percentile — precisely the band where taking more risk makes the
headline fall. That is what the tester saw and reported as an inversion, and after the
genuine defects are fixed the remaining hump is correct behaviour being shown badly.

**The decision is which number the card shows** — the median, a range, or the 20th
percentile with an explanation. Not an implementation task; do not dispatch an agent.

---

## DO NOT RE-DISPATCH — already done, would duplicate

**W-0264 investment side is COMPLETE.** Do not queue an agent for it.
On disk: `RiskPreferenceService::getProductRiskOverride()` / `resolveProductRiskLevel()`
as the one home; all three inert readers routed to it (`PortfolioPresentationService:204`,
`InvestmentController:1091`, `AccountRebalancingController:220`); plus four inline copies
of the same chain inside `InvestmentProjectionService` — **seven expressions of one rule
became one**. Guarded by `tests/Feature/Investment/AccountRiskOverrideIsHonouredTest.php`
(6 tests), whose first case is a permanent inverted guard: **setting `has_custom_risk` by
hand must change nothing.** Browser-verified: David's ISA at High now recommends 90%
equities where it recommended 50%.

**Pension readers deliberately untouched** — that is W-0262's live work.
**The remaining follow-up is collapsing `has_custom_risk` entirely**, since
`risk_preference IS NOT NULL` already encodes the same fact. Two columns for one fact is
what let them drift. Written up in W-0264 and `F-0024` §5b.

**Also complete, do not re-dispatch:** D-20, D-21, W-0217 (all one root cause — a cache
key that named neither capital nor risk); W-0254 (a fabricated £1,667/month ISA
contribution nobody entered); W-0256 (a projection excluding the user's own joint
account); W-0228/W-0236/W-0237; W-0238/W-0239; W-0261/W-0262/W-0242.

**Still open from the projections batch:** W-0257 (allocation form silently refuses to
save — now in the `fix-cycle4-columns` batch), W-0258/W-0259 (**CSJ decision**, see above).

---

## TRAP — `/m` verification gives a FALSE PASS against a stale bundle

**Found 2026-08-22 by an agent that checked before running its `/m` leg, not after.**

`/m` serves `public/m-build/` and **never** Vite — `mobile-app.blade.php` picks the build
directory unconditionally (F-0022 §6.3). So a `resources/mobile/` change is invisible on
`/m` until the bundle is rebuilt.

**The danger is not that it fails — it is that most of it silently PASSES.** The backend
reaches `/m` through the same endpoints, so **the figures are right either way**. In the
case that caught it, even the disclosure *sentence* would have rendered correctly, because
the old bundle carried a **hardcoded** copy byte-identical to the new constant. The agent
would have looked at a correct-looking screen and signed off a consolidation that had
never shipped.

**Before believing any green `/m` screen, check the bundle's mtime against the source:**
```
ls -la public/m-build/assets/main-*.js
ls -la resources/mobile/views/<the file you changed>
```
If the source is newer, the screen is history. **Rebuild is the coordinator's:**
`npm run build:mobile`.

**Rebuilding is safe for other agents** — verified 2026-08-22. `vite.mobile.config.js`
deliberately omits `laravel-vite-plugin` (comment at the top of the file says why), so it
writes **no `public/hot`** and cannot couple to the web dev server. Blast radius is
`public/m-build/` alone. Confirmed after a rebuild: `public/hot` unchanged, Laravel still
200. **The one real hazard is swapping the bundle under someone mid-`/m`-session** — tell
them, do not surprise them.

**Corollary:** leaving a stale bundle in place does not protect anyone already testing
`/m` — it silently corrupts their readings too. Rebuild and announce.

**The STRONGER check — grep for the string the consolidation DELETED.**
An mtime tells you the bundle is fresh. It does not tell you the consolidation shipped.
**Grep the built bundle for the copy you removed:**
```
grep -c 'Accessible pension capital' public/m-build/assets/main-*.js   # must be 0
grep -c 'db_pension_disclosure'      public/m-build/assets/main-*.js   # must be present
```
**A consolidation is only verifiable once the old copy is provably absent.** Once the
hardcoded sentence is gone from the bundle, seeing it on screen becomes **positive proof
it came from the backend** — the bundle physically cannot supply it. The same observation
that would have been a false pass becomes a conclusive one. One command, and it turns an
ambiguous green screen into evidence.


---

## FOR QUALITY-LEAD — `status: handoff` is a moment-in-time claim, not a current state

**Raised 2026-08-23 by the agent that found it in its own item.** W-0263 read
`status: handoff` / `handoff_to: quality-lead` — set when it was first handed on — while
its headline acceptance criterion was still unmet. Every box on the list was ticked, and
**the one criterion that mattered had no box at all.**

> **Ticking boxes made the item look MORE finished than it was.**

That is precisely how something gets certified on an API-level proof. The item now carries
an explicit unchecked fifth criterion and a note addressed to quality-lead directly.

**Check the following before certifying any of them.** This is a crude grep for
"BROWSER VERIFIED" / "browser-verified" / "verified in the browser" across items at
`handoff` touched 2026-08-22→23 — **absence is a prompt to look, NOT a verdict.** Several
are legitimately backend-only; several belong to batches still queued for the tab.

**Carries browser evidence (19):** W-0132 · W-0134 · W-0189 · W-0228 · W-0237 · W-0238 ·
W-0241 · W-0242 · W-0244 · W-0252 · W-0257 · W-0261 · W-0262 · W-0264 · W-0271 · W-0272 ·
W-0273 · W-0274 · W-0322

**No browser-evidence marker found (22):** W-0137 · W-0173 · W-0186 · W-0187 · W-0188 ·
W-0190 · W-0203 · W-0206 · W-0207 · W-0210 · W-0217 · W-0236 · W-0239 · W-0251 · W-0254 ·
W-0255 · W-0256 · W-0263 · W-0326 · W-0331 · W-0332

**Known-and-expected among those:** W-0263 and W-0326 are waiting on the tab for one
journey (headline already green at the API level through the real request AND the real
Store); W-0331 and W-0332 are waiting on the tab for the savings pass.

**The general rule this produces, worth applying beyond today:** a status field records the
moment it was written. **An acceptance list can be entirely green while the criterion that
matters is absent from it** — so read the item's acceptance for what is *missing*, not only
for what is unticked.

---
---

# TESTER RESTART BRIEF — read this before re-entering the household

**Per CSJ's rule: fixers down, then the tester restarts FROM THE BEGINNING.** Not a resume.
Fixes changed earlier screens, so a resumed pass carries unverified assumptions forward.

## Environment traps that cost hours tonight — all avoidable

| Trap | What it looks like | What to do |
|---|---|---|
| **Session state relays** | A coordinator tells you who is signed in | **Ignore it. Six relays were wrong tonight** (nobody / Sarah / David / nobody / Sarah / nobody). Establish it yourself. |
| **`fynla-state.auth.user`** | Says Sarah; the token is David | **Never use it.** `GET /api/auth/user` **on the token in use**. It reported the wrong user tonight and nearly filed one account's figures as another's. |
| **Two token stores** | `/m` and desktop disagree | `sessionStorage.auth_token` (desktop) and `localStorage.m_scaffold_token` (`/m`) **can hold different users at once.** Check each on its own token. |
| **Identifying by figure** | "£99,750, so this is David" | **Circular** — the figures are what you are testing. |
| **HMR remount** | Field shows your text, Vue state empty, submit fires nothing | Another agent editing `resources/js/` remounts the component mid-interaction. **Make fill-and-click atomic in ONE `browser_evaluate`.** Tell: request count climbing with no navigation. |
| **Wedged CDP input** | `fill()` works, `click()`/`press()` produce **zero** events; even a plain `<a href>` will not navigate | **Tooling, not a defect. Open a new tab.** |
| **Stale `/m` bundle** | `/m` looks correct | `/m` serves `public/m-build/`, **never Vite**. It **fails by agreeing with you** — figures come from the backend either way. **Grep the bundle for a string that can only exist if the change shipped**, and confirm the page serves the file you grepped. Rebuilds are the coordinator's. |
| **Persona password** | `password` fails | **`Password1!`** for both. **One attempt** — the lockout counter is shared. MFA from `email_verification_codes`, fetched yourself. |

## Figures that MOVED — expect these, do not report them as regressions

| Figure | Old | New |
|---|---|---|
| Household debt | £305,000 | **£293,000** |
| David's share, mortgage 16 | £60,000 | **£48,000** |
| David's net worth | £1,477,500 | **£1,489,500** |
| David's ESTATE (dashboard, will page, `/m`) | £1,489,500 | **£989,500** |
| Sarah's Pensions card | £805,000 | **£0** (+ "£35,000 a year") |
| Sarah's asset percentages | 193% | **100%** |
| Sarah's life cover | £0 | **£500,000** |
| Sarah's `/m` protection total | £0 | **£500,000** |
| Sarah's savings (`/m`) | £33,280 | **£31,030** |
| Emergency runway, David / Sarah | 0 / 0 months | **79.8 / 24.8** |
| Sarah's monthly expenditure | £1,225 | **£1,250** |
| Household expenditure | £2,475 | **£2,500** |
| David's projection, 5y p20 | £4,650 | **£86,944** |
| Projected Inheritance Tax | £2,851,349.69 | **£2,646,151.58** |
| Charitable percentage | 0.6% | **0.81%** |
| SIPP fees | 0.00% / £0 | **0.31% / £976 a year** |

**Expenditure baseline is £2,500 / £1,250** — the £2,450 in older artefacts was a playbook
transcription error, corrected.

## Named checks carried forward

1. **David's `/m` liabilities screen must render £170,500** — never verified; the agent that
   owned it recorded "I COULD NOT VERIFY THIS" rather than inferring it.
2. **The dashboard's second "All goals on track" banner** — renders **Locked** on a
   tier-gated account, so it was seen neither to succeed nor fail. **Needs an account with
   the Goals module unlocked.**
3. **Sarah has no Defined Contribution pension** — nothing on her account exercises pension
   holdings. Her Defined Benefit card **does not open a detail panel** when clicked: raised,
   untouched.
4. **The investment holdings table is unreachable with data on this build** — three separate
   causes. Do not report it as a display bug; it is W-0442.
5. **Neither the unvalued-gift sentence nor the zero-bequest percentage branch renders on
   this household.** Tests only.
6. **iOS is untested throughout.** Not built, not launched, not claimed.

## Do NOT re-raise
- Mortgage 16 still stores `joint 50%` **deliberately** — the reader resolves through the
  property. Repairing the row moves nothing.
- The `/m` dashboard **Level wheel, "X of Y actions", and percentile** are a deliberate
  gamification layer (CLAUDE.md Rule 12 carve-out). Never strip or flag them.
- Grandfathered emoji and SVG (Rule 15 is forward-only).
- W-0187 records £182,500 as observed — **historical, do not re-verify against it.**
