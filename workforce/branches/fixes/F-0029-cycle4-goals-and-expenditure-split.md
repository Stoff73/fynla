---
id: F-0029
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/05-perimeter.md, core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-23T02:30:00Z
status: active
---

# F-0029 — Cycle 4: an overdue goal that says "On track", and a 50/50 split that reads 50.5 / 49.5

**Agent:** build-lead (`fix-cycle4-goals-expenditure`) · **Working tree:** shared, on
`wip/persona-cycle4-snapshot` (= `dev` + `d5fe9f9f7`, flagged to team-lead — I did not
switch it)
**Board items:** W-0411, W-0412 (fixed) · W-0413, W-0414, W-0415 (raised) · **ID block:**
W-0411 – W-0420
**F number:** taken after checking `fixes/` — F-0028 was the highest, so **F-0029**.
Team-lead was told the number taken rather than the agent choosing silently
(`FORMATS.md` §"Branch-document numbers").

**Predecessors, read before touching anything here:** `W-0029` made past-dated goals
creatable — W-0411 is its residual. `W-0190` made the expenditure split 50/50 at rest —
W-0412 is its residual, and §8 states the amendment its acceptance needs.

**Prior-art check.** Sources 2–6 run (code · custom artisan commands · open PRs and
in-flight branches · vault · `.claude/skills` + `.claude/agents`). Source 1,
`registry/capabilities.md`, **was unavailable — `workforce/registry/` does not exist at
all**, raised as W-0415. Open PRs are #713 (marketing emails) and #249 (parked);
`fyn/m-view-link-and-expenditure-gate` is already merged into `dev`.

**`workforce/ops/board/` was NOT swept in the first pass, and it should have been.** It
holds **W-0202**, which owns a third of what was built here and carries a team-lead
decision parking it — see §4.4 for what that cost and what was reverted. Swept properly
afterwards: for the expenditure side, W-0190 (predecessor), W-0202 (parked, reverted out),
W-0011 (the Premium gate this must not re-trip), W-0413 (raised here). For the goals side,
W-0029 (predecessor) and nothing else — no board item governs the on-track judgement.
W-0271 and W-0274 concern *which mechanism answers* the emergency-fund figure, not the
expenditure input, so they sit beside the runway movement in §6.1 without overlapping it.

Outcome for both defects: **extend** — the mechanisms existed and were inadequate.

---

## 1. Two defects, one shape

Both are a **residual**: an earlier fix established the right rule in the right place and
did not carry it to every path the rule has to reach. In each case a second mechanism went
on answering the same question by its own arithmetic, and the surface believed whichever
one it happened to read.

| | W-0411 | W-0412 |
|---|---|---|
| Earlier fix | W-0029 — past-dated goals became creatable | W-0190 — each account stores its **share** |
| What it did not carry | the on-track maths was never updated for them | there was nowhere to put the **other** half |
| Second mechanism | `GoalProgressService` decided `is_on_track` again | Fyn, `update_profile` and onboarding each wrote expenditure their own way |
| Symptom | a goal 4½ months past its date says **"On track"** | a declared 50/50 reads **50.5 / 49.5** |

---

## 2. W-0411 — the guard that was there for exactly this, and never fired

`GoalCalculationService.php:56-81`, before:

```php
$totalDays = $goal->start_date->diffInDays($goal->target_date);
if ($totalDays <= 0) {                                   // ← the guard
    return $this->calculateProgressPercentage($goal) >= 100;
}
$daysElapsed = $goal->start_date->diffInDays(now());
$expectedProgress = min(($daysElapsed / $totalDays) * 100, 100);
return $this->calculateProgressPercentage($goal) >= ($expectedProgress - 10);
```

Three facts compose the defect:

1. **`start_date` is stamped with today at creation**, so for a past-dated goal it lands
   *after* `target_date`. The stored span runs backwards.
2. **Carbon is 2.73.0, where `diffInDays()` is absolute.** Measured in this repo, not
   inherited from a note:
   ```
   Carbon::parse('2026-08-22')->diffInDays(Carbon::parse('2026-08-01'))        => int(21)
   Carbon::parse('2026-08-22')->diffInDays(Carbon::parse('2026-08-01'), false) => int(-21)
   ```
3. So the inverted range came back **positive**, and **the guard that exists precisely to
   catch a non-positive span never fired.** Elapsed read as ~1 day of a 21-day span,
   expected progress as ~5%, and any progress at all cleared the 10% margin.

Live rows at the moment of diagnosis:

```
#59 u16  start 2026-08-21  target 2026-04-05  75%  is_on_track = TRUE
#60 u16  start 2026-08-22  target 2026-08-01  80%  is_on_track = TRUE
```

The page summary is `on_track_count === total_goals` (`GoalsOverview.vue:109`), fed by the
same accessor through `GoalsAgent::getDashboardOverview` (`:357`). One wrong boolean per
goal became **"All goals on track! Keep up the great progress."**

### 2.1 The second mechanism

`GoalProgressService.php:43` decided the same boolean a second time, by its own rule
(`$currentAmount > 0 && $progressDelta >= -10`) over its own absolute-diff span
(`:28-32`). Its `expected_progress` therefore sat at **0** for an overdue goal — so a
missed goal read as **ahead of schedule**, not merely on track. Consumed by
`GoalsController::show`, `GoalStrategyService`, `GoalPlanService` and `SavingsAgent`.

Under Rule 20, collapsing it is **part of** the fix. Fixing one of two mechanisms is what
produced the class of defect in the first place.

### 2.2 What an overdue goal now says — the decision

A goal whose target date has passed **is not on track, funded or not.** The two cases are
different answers and needed different words:

| State | Label |
|---|---|
| overdue, ≥100% | **Achieved late** |
| overdue, <100% | **Overdue** |
| not overdue, ≥100% | Goal achieved |
| nothing saved | Not started |
| on track | On track |
| behind, date still ahead | Behind schedule |
| status completed / paused / abandoned | Completed / Paused / Abandoned |

One home: `GoalCalculationService::calculateStatusLabel()`, appended to the model as
`status_label` beside a new `is_overdue`, carried into `GoalResource`, all three
`GoalsAgent` payloads, and Fyn's goal snapshot in `CoordinatingAgent`. Every surface reads
the same string rather than composing its own from a boolean that has only two values.

**Colours were left alone.** An overdue goal keeps the existing violet treatment. Whether
"missed" should be raspberry is a design decision under Rules 8 and 12 and is not the
build lead's to take.

### 2.3 A third fault in the same file

`calculateDaysRemaining()` floors at zero. `GoalCard.vue`'s `timeRemaining()` then tests
`days < 0` — **unreachable** — before `days === 0 → 'Today'`. A goal three weeks past its
date displayed **"Today"** as its time remaining.

Fixed by consulting `is_overdue` explicitly rather than by letting `days_remaining` go
negative, because several consumers (`calculateRequiredMonthlyContribution`, `SavingsAgent`)
divide by it and rely on the non-negative contract.

---

## 3. W-0412 — the household inherited the error instead of being the source of truth

### 3.1 Two corrections to the reported framing

The defect report said `expenditure_profiles` holds one row and Sarah's figure is derived.
Neither is where the fault is:

- **The 22 category columns are on `users`**, not `expenditure_profiles` (which carries
  only a monthly total).
- **Sarah's £1,225 is a stored value, and it is stale.** She has a full row:

```
u16 David : healthcare_medical 50.00   monthly 1250.00  annual 15000.00
u17 Sarah : healthcare_medical 25.00   monthly 1225.00  annual 14700.00
```

`healthcare_medical` is the **only** column that differs between the two rows — and that
single £25 is exactly the £400/£375/£775 Essential Living line and the
£1,250/£1,225/£2,475 total on the screen.

### 3.2 What happened, from `audit_logs`

```
#593  2026-08-21 08:47  actor 16 -> u16   healthcare 0 -> 50,   monthly 50 -> 2450
#1332 2026-08-22 08:04  actor --  -> u16   healthcare 50 -> 25,  monthly 2450 -> 1225   (W-0190 remediation script)
#1334 2026-08-22 08:04  actor --  -> u17   healthcare 0 -> 25,   monthly NULL -> 1225   (same script, Sarah created)
#1376 2026-08-22 20:24  actor 16 -> u16   healthcare 25 -> 50,  monthly 1225 -> 1250   <- the edit
```

**There is no #1377 for user 17.** The edit wrote one row.

Ruled out, not assumed: **not Fyn** — zero `ai_messages` for user 16 in the 20:00–21:00
window; the write carried a Chrome UA and updated `ExpenditureProfile#1`; and the halving
is visible in it (household 100 stored as 50), which only the profile path performed.

### 3.3 Root cause

`UserProfileController::updateExpenditure` halved the acting user's row and **stopped**.
The spouse's half was written by a **separate, independent second HTTP request** the
frontend was trusted to send (`ExpenditureOverview.vue:96-102` →
`PUT /api/users/{id}/expenditure`). The backend never required it, never verified it, and
could not compensate when it did not arrive.

The household was then computed as *David's half + Sarah's half* — so **when the halves
disagreed, the household total inherited the error instead of being the source of truth.**

**The architecture guarantees this failure mode.** Which request went missing on the night
is incidental; two halves kept in step by two separate requests will come apart.

### 3.4 Three more paths, found by enumerating rather than by predicting

| Path | Before |
|---|---|
| `CoordinatingAgent::handleSetExpenditure` (Fyn, every surface) | wrote **whole** — no division, no spouse. Telling Fyn "food is £450" under joint mode stored 450 on one row, and the household then read £675. **Still does — this is W-0202 and it is parked. See §4.4.** |
| `CoordinatingAgent` `update_profile`, `section: expenditure`, simple total | same shape, found while reading the first. **Also left as it is, same reason.** |
| `ExpenditureForm.handleSave` joint branch → `OnboardingService::processExpenditureInfo` | emitted `{userData, spouseData}` for **joint** as well as separate; that service routes on the presence of those keys and took its **SEPARATE** branch, writing the full household figure whole to **both** accounts — the exact double count W-0190 ended on the profile path, still live in onboarding |

**Which page you told your spending to decided what the household came to.**

---

## 4. The fix

### 4.1 One home for the write

New: `app/Services/Expenditure/HouseholdExpenditureWriter`.

One household payload in. Both accounts' halves derived from that one figure, in one
transaction, with both `ExpenditureProfile` rows synced and both caches invalidated.
`SharedExpenditure` remains the one home for the **rule**; this is the one home for the
**write**.

Routed through it: `updateExpenditure` · `updateSpouseExpenditure` (joint). The
frontend's joint-mode `spouseData` emit is deleted — one payload, one request.
**Fyn's two write paths are deliberately NOT routed — §4.4.**

Three details that are not incidental:

- **The spouse row receives only the fields `SharedExpenditure` actually divides.**
  `shareOf()` passes undivided keys through untouched, which is right for the account
  doing the entering and wrong for the one beside it: mirroring an undivided money field
  puts the whole of it on **both** rows. `charitable_donations` is deliberately outside
  `SHARED_FIELDS` (a Gift Aid input — halving it moves a tax relief figure) and
  `rent`/`utilities` are outside it too. **This also removes a live double count:** the
  old second request mirrored `charitable_donations` whole, so the household column showed
  2× what was entered.
- **`liveSpouse()` replaces `spouse_id !== null`.** `spouse_id` survives the spouse's
  deletion and the record is retained, so a household with nobody left to share with had
  its spending halved into a row nobody can read. Covered by a case.

### 4.4 Fyn is NOT routed through it — and this is a correction

**I built the Fyn routing first, then found the board item that governs it, and reverted
it.** Recording that plainly because the sequence is the lesson.

`W-0202` — *"Fyn's expenditure capture writes one account at 100% regardless of the
household's declared sharing mode"* — was raised on 2026-08-22 by `cycle2-ownership` while
fixing W-0190. It carries a **team-lead decision**, and its **acceptance criterion 1 says
the prerequisite must be settled first**:

> *"The unanswered state is made expressible, or the default is disclosed. **This must be
> settled first**; branch three of the decision is unbuildable until it is."*
> — and: **"NOT to be built this cycle."**

**The prerequisite is real, and it is not arithmetic — it is disclosure.**
`users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT 'joint'`, so
**joint-by-declaration and joint-by-never-having-been-asked are indistinguishable**. W-0202
measured it: **19 users on the dev database, every one `joint`, not one `separate`.** Every
value is the default.

The expenditure form can halve on that default because **it discloses**: the subheading
reads "Joint (50/50) expenditure" and the toggle is visible and set at the moment of entry.
**Fyn has no equivalent disclosure**, so routing it would silently resolve a question the
user was never asked — which is the exact thing W-0202's decision exists to prevent.

W-0202 itself makes this distinction and rules the profile path defensible:

> *"Note this also touches the shipped W-0190 fix, in the form's favour… That is defensible
> where Fyn's would not be, because the form discloses it. The difference between the two
> surfaces is disclosure, not arithmetic."*

So the W-0412 work stands and the Fyn work does not. Both `CoordinatingAgent` write paths
are back to their prior behaviour, with the reason recorded at each site.
`HouseholdExpenditureWriter` **is** the mechanism W-0202's criterion 2 asks for — when the
decision lands, each path becomes one call to it.

**What this cost, and the rule it produces.** My prior-art check ran code, artisan commands,
open PRs and in-flight branches, the vault, and `.claude/skills` + `.claude/agents`. **It
did not sweep `workforce/ops/board/`** — 234 items, one of which owned a third of what I
was about to build, with a decision already taken on it.

> **The board is prior art. A queued item is not an absent item — it is a decision someone
> has already made, and building past it is not initiative.**

### 4.2 One home for the on-track judgement

`GoalCalculationService` gains `isOverdue()` and `calculateStatusLabel()`; its spans are
signed and `daysElapsed` is clamped. `GoalProgressService` asks it instead of deciding
again, and now spends the whole period once the date has gone (`expected_progress` 100,
delta −20, status `behind`) rather than none of it.

### 4.3 Files

```
app/Services/Goals/GoalCalculationService.php        isOverdue, calculateStatusLabel, signed spans
app/Services/Goals/GoalProgressService.php           delegates; signed spans; overdue-aware expected progress
app/Services/Expenditure/HouseholdExpenditureWriter.php   NEW — the one write
app/Models/Goal.php                                  appends is_overdue, status_label
app/Http/Resources/GoalResource.php                  +2 fields
app/Http/Controllers/Api/UserProfileController.php   both expenditure endpoints routed
app/Agents/GoalsAgent.php                            +2 fields on three payloads
app/Agents/CoordinatingAgent.php                     two expenditure writes routed; +2 fields on the goal snapshot
resources/js/components/Goals/{GoalsOverview,GoalCard,GoalDetailInline,GoalsByModule}.vue
resources/js/components/UserProfile/ExpenditureForm.vue   joint spouseData emit removed
resources/js/components/Dashboard/GoalsOverviewCard.vue   second "All goals on track" banner; dot tooltip
resources/mobile/views/modules/Goals.vue             reads status_label   <-- needs public/m-build/
```

---

## 5. Tests, and what each one is guarding against

Written against `tests/CLAUDE.md` §4, all five variants, before any test was written.

**`tests/Unit/Services/Goals/GoalOverdueIsNotOnTrackTest.php` — NEW, 18 cases.**
The **Fixture** variant is the one that mattered: a suite whose goals are all future-dated
never enters the inverted-range branch, which is how this survived. The fixture holds an
overdue goal **and** an overdue-but-fully-funded one, because those are different answers,
plus healthy future-dated goals so the assertions can distinguish "the rule fires" from
"everything is false now". One case pins the Carbon behaviour itself, so a Carbon 3 upgrade
reddens here rather than silently changing the maths.

**`tests/Feature/Goals/PastDatedRecordsTest.php` — extended, +3 cases (9 total).**
This is **W-0029's own file**, and the gap was in its acceptance: it created a past-dated
goal, asserted the row landed, and never asked what the app then *said* about it. The new
cases drive `POST /api/goals` then `GET /api/goals` and `GET /api/goals/dashboard-overview`
and assert `is_on_track: false`, `status_label: "Overdue"` / `"Achieved late"`, and
`on_track_count` 1 of 2. The guard now sits where the original acceptance stopped.

**`tests/Feature/UserProfile/JointExpenditureSplitsByDeclaredModeTest.php` — extended,
+7 cases (17 total).**
The **Collision** variant is the one that mattered: under a correct 50/50 the two halves
are the same number, so a fixture that starts both rows equal cannot tell a mirrored write
from no write at all. Every new case therefore **starts the two rows out of step**, exactly
as the live pair is, and asserts the **non-editing spouse's stored row MOVES** — 25 is what
an unwritten row reads, 50 is the household's £100 divided.

**`tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php` — extended, +1 case
(8 total).** Every pre-existing case in that file uses a user with **no spouse**, so the
whole of the sharing behaviour was invisible to the file that covers the method — another
Fixture-variant gap, in the suite rather than in the code. The added case **pins the
current, undivided behaviour** (450 on the acting row, 0 on the spouse) so that when
W-0202 is built the change arrives as a deliberate red test rather than a silent diff. It
is pinned, not endorsed, and the docblock says so.

**`ExpenditureSimpleEntry.spec.js` +2, `/m` `Goals.spec.js` +1.** Same gap on the frontend:
every existing case mounted an unmarried user, and the `/m` goals fixture holds one healthy
future-dated goal.

**Decoy check** run: every case named after a class or method resolves and calls it —
`GoalCalculationService`, `GoalProgressService`, `GoalsAgent::getDashboardOverview`,
`handleSetExpenditure` and the two HTTP endpoints are all invoked, not reproduced inline.

**Side-effect assertions, not status codes:** every expenditure case asserts stored rows
and `ExpenditureProfile` rows, never `assertOk()` alone.

### 5.1 Mutation results — each bug restored, only the right tests reddened

| Bug restored | Reddened | Stayed green |
|---|---|---|
| Overdue guard removed, spans back to absolute | **5**, incl. `on_track_count` 4 vs 2 — the banner's own condition | **40 pre-existing goal tests passed WITH the bug in place**, which is the finding, not the footnote |
| Writer stops mirroring the spouse | **6** | separate-mode and deleted-spouse cases — correctly, they do not depend on mirroring |
| Joint `spouseData` emit restored | **1** | the other 6 |
| `/m` label falls back to its own composition | **1** | the other 6 |

### 5.2 Runs

```
Feature/UserProfile + Unit/Services/Goals + Goals/Savings agents + Fyn expenditure
  + gamification + risk + tiers + profile-completeness      168 passed   535 assertions
Feature/Goals/PastDatedRecordsTest (W-0029's file, extended)   9 passed    29 assertions
Onboarding + Savings + Plans (emit-shape and GoalProgressService blast radius)
                                                            939 passed 3,303 assertions
--testsuite=Architecture              149 passed, 28 deprecated, 1 skipped, 0 failed
Vitest: resources/js/components/__tests__ (all)              183 passed
Vitest: /m goals                                              7 passed
```

Own database throughout: `DB_DATABASE=laravel_testing_r`. `phpunit.xml` and `Pest.php`
untouched. Pint clean; the two new `use` imports in `CoordinatingAgent` were verified to
survive the formatter (`tests/CLAUDE.md` §"green suite goes red", cause 2).

---

## 6. What the browser can and cannot settle

Stated in advance rather than discovered afterwards.

**It cannot settle W-0412 on its own.** Under a correct 50/50 the two halves are the same
number, so no screen can distinguish *"both rows were written"* from *"one row was written
and the other already matched"*. The only way a screen proves it is if the rows start out
of step — and the live pair currently is (David healthcare £50, Sarah £25). **That makes
the peak_earners data the right fixture for exactly one run: after the first save the
evidence is spent.** Sarah's stored row is captured before and after.

**It can settle W-0411 outright.** Goals #59 and #60 are overdue and were reading "On
track"; both the card labels and the page banner are visible facts.

### 6.1 Baseline captured BEFORE any write — the evidence that step one spends

Read from the live local database and the live engine, read-only, before touching the
browser. Recorded here because **the divergence disappears the moment the fix is
exercised**, and after that no run can reproduce it.

```
u16 David : users.monthly_expenditure 1250.00  healthcare_medical 50.00  ExpenditureProfile 1250.00
u17 Sarah : users.monthly_expenditure 1225.00  healthcare_medical 25.00  ExpenditureProfile NONE
```

**A second fault visible only in that last column.** Sarah has **no `ExpenditureProfile`
row at all**, so `ResolvesExpenditure` falls through to `users.monthly_expenditure` for
her while David is served from his profile row. **Two accounts of one household resolving
their monthly outgoings from two different sources.** The writer now creates and syncs
both, which is why the parity assertion is in the suite.

`SavingsAgent::analyze()`, live, before:

```
u16 David : runway_months = 79.80      <- CONTROL. His row is already 1250; this must NOT move.
u17 Sarah : runway_months = 25.33      <- divides by the stale 1225
```

Sarah's fund is therefore ≈ **£31,029**. Once her stored row moves to £1,250 the runway
should read **≈ 24.82** — which is the ≈24.8 the defect report predicted, arrived at
independently here rather than taken on trust. **It must move on `/risk-profile`,
`/dashboard` and `/m` together; one surface lagging is a regression to chase.** David's
79.80 is the control that proves the change is the expenditure figure and not something
else moving underneath.

### 6.2 The sequence, written before it is run

Recorded here so it is executed rather than improvised, and so a replacement agent can run
it unaided.

1. **Establish identity from the server, per surface, before believing any screen.**
   `GET /api/auth/user` with the token actually in use — `sessionStorage.auth_token` for
   desktop, `localStorage.m_scaffold_token` for `/m`, **checked separately, because the two
   stores can hold different users on one origin.** Never `fynla-state.auth.user`, which
   goes stale. **Never confirm identity by recognising a figure — the figures are what is
   under test.**
2. Capture Sarah's stored row and both runways again — §6.1 above is that capture.
3. Sign in as **David (16)**, `Password1!`, MFA read from `email_verification_codes`
   directly. **One attempt.** A failed password burns into the shared lockout counter.
4. `/goals` — the two overdue goals and the page banner. Then `/dashboard` for the second
   copy of the banner in `GoalsOverviewCard`.
5. `/valuable-info` → Expenditure → Edit → save. **This is the step that spends the
   evidence.**
6. Re-read both stored rows and the three-column table: halves equal, Essential Living
   household £800, total £2,500.
7. Sign in as **Sarah (17)** on her own token: runway on `/risk-profile` and `/dashboard`
   moves 25.33 → ≈24.8. **David's 79.80 must not move.**
8. `/m` — goals labels need `public/m-build/` rebuilt first; `/m` expenditure does not.

### 6.3 VERIFIED IN THE BROWSER — 2026-08-23 02:52–03:01

**Identity established from the server on every surface, per token.** The tab was
authenticated as **nobody** when handed over (`sessionStorage.auth_token` absent,
`localStorage.m_scaffold_token` absent) — the state that has been misreported three times
tonight, confirmed rather than relayed. Thereafter, on each surface, against
`GET /api/auth/user` with the token actually in use:

```
desktop  200 id=16 david.jones@example.com   live_spouse_id=17  mode=joint
/m       200 id=16 david.jones@example.com   (m_scaffold_token, checked separately)
desktop  200 id=17 sarah.jones@example.com
/m       200 id=17 sarah.jones@example.com
```

**No figure was used to identify anyone.** One password attempt per account; MFA read from
`email_verification_codes` directly.

**The HMR trap did not bite**, because every fill-and-submit was atomic inside one
`browser_evaluate`, setting the value through the native setter and dispatching `input` so
Vue's `v-model` updated in the same tick.

#### W-0411, web `/goals` — GREEN

```
Max Pension Contributions    Retirement   Overdue    75% complete
Charlotte's Gap Year Fund    Savings      Overdue    80% complete
Early Retirement Fund        Retirement   On track   48% complete
ISA Wealth Building          Investment   On track   37% complete
William's House Deposit Help Property     On track   70% complete

"2 goals are behind schedule"   <- in place of the congratulation
```

**"All goals on track! Keep up the great progress" is gone.** The three healthy goals still
read *On track*, which is the discriminating evidence: the rule fires on overdue, not on
everything. Screenshot `W-0411-goals-overdue-web.png`.

#### W-0411, `/m` — GREEN, and it confirms its own bundle

The page served `m-build/assets/main-CFX4VVV3.js` — **the exact file grepped beforehand**,
which contains `statusLabel(t){return t.status_label?t.status_label:...`, unmistakably the
change and present nowhere else in `resources/mobile`.

```
Goals on track   3 of 5           <- was 5 of 5
Max Pension Contributions    OVERDUE   Target date passed
Charlotte's Gap Year Fund    OVERDUE   Target date passed
(three others)               ON TRACK
```

Zero occurrences of "Behind" — the local fallback is not firing; the label is the server's.
Screenshot `W-0411-goals-overdue-m.png`. On Sarah's own account, her single future-dated
goal reads **ON TRACK, "1 of 1"** — the control holds on the second account too.

#### W-0412 — GREEN, from ONE request

Before (`W-0412-BEFORE-50.5-49.5-split.png`), after (`W-0412-AFTER-50-50-split.png`):

```
                          David    Sarah    Household
BEFORE  Essential Living   £400     £375     £775      (categories give £800)
        Manual Total     £1,250   £1,225   £2,475      (categories give £2,500)

AFTER   Essential Living   £400     £400     £800
        Manual Total     £1,250   £1,250   £2,500
```

Edit mode lifted `healthcare_medical` to **£75** — David's 50 plus Sarah's 25, the
out-of-step sum — and it was set to the persona's £100 and saved.

**The network tab is the proof:**

```
221. [PUT] /api/user/profile/expenditure => [200] OK
```

**One request. No `PUT /api/users/17/expenditure`.** Under the previous design that same
single request left Sarah at £1,225 and the household at £2,475.

**And the audit trail is the exact mirror of the defect:**

```
#1465 actor=16 target=17  02:56:18  {"annual_expenditure":15000,"healthcare_medical":50,"monthly_expenditure":1250}
```

One row, for **user 17**, and none for user 16 — because David's row was already correct
and nothing was dirty. On 2026-08-22 there was a row for 16 and none for 17.

Stored state afterwards, both accounts:

```
u16  monthly 1250.00  healthcare 50.00  annual 15000.00  ExpenditureProfile 1250.00
u17  monthly 1250.00  healthcare 50.00  annual 15000.00  ExpenditureProfile 1250.00
household sum of the 20 categories = 2500
categories where the halves disagree  = NONE
```

Sarah now **has** an `ExpenditureProfile` row (§6.1: she had none), so both accounts resolve
their outgoings from the same source.

#### The dependent figure moved on all three surfaces together

```
/dashboard      24.8 / 6 months        (was 25.3)
/risk-profile   Emergency Fund  24.8 months
/m dashboard    24.8 / 6 months
/m expenditure  £2,485 / £29,820 a year   (was £2,460 / £29,520)
```

**The control held: David's runway 79.80 before, 79.80 after — it did not move.** So what
changed is the expenditure figure and not something underneath it.

#### One surface I COULD NOT TEST

**`GoalsOverviewCard.vue` on `/dashboard`.** The Goals card renders as **"Locked"** for
David — the module is tier-gated on that dashboard — so its second copy of the "All goals
on track" banner never rendered and I did not see it either succeed or fail. Its condition
is `onTrackCount === totalGoals` over the same corrected `on_track_count`, and the unit
suite covers that count, but **the rendered card is untested and I am not calling it
verified.**

(`£1,489,500` on `/dashboard` is labelled **NET WORTH**, not estate — not the estate figure
flagged as having moved to £989,500. Sarah's £739,280 net worth is unchanged, as expected.)

**Confirming run after the browser pass: 142 passed, 436 assertions.**

---

## 7. `/m` — this batch is not backend-only

The expenditure half is: `resources/mobile/views/Expenditure.vue` is read-only, there is
no mobile edit surface, and `/m` inherits the fix through the shared API.

The goals half is **not**. `resources/mobile/views/modules/Goals.vue` composed its own
label from `is_on_track`, so an overdue goal read "Behind" and an overdue-but-funded one
read **"Complete"**. It now reads the server's `status_label`. **That is a
`resources/mobile/` change and it is invisible on `/m` until `public/m-build/` is rebuilt.**
Build artefacts belong to team-lead; requested, not taken.

### 7.1 iOS — named, not assumed

**Partially fixed, and I am not claiming more.** The native app reads `is_on_track` and
`on_track_count` from the same API, so `GoalsView.swift:80` — *"Goals on track — X of Y"* —
is correct now with no Swift change and no build. **The false claim is gone from iOS.**

What is not fixed there: the native app composes its own label from the boolean, in **two**
places (`GoalModels.swift:85`, `GoalsView.swift:231`), so an overdue goal reads **"Behind"**
and an overdue-but-funded one reads "Behind" too. That is the Rule 20 shape the web had
before this batch. It needs a Swift change and an Xcode build, which is a different
verification loop — raised as **W-0416** rather than folded in and claimed.

### 7.2 One more web surface, found by enumerating

`resources/js/components/Dashboard/GoalsOverviewCard.vue` carries a **second copy** of the
congratulation — *"All goals on track"*, gated on `onTrackCount === totalGoals` at `:116`.
The boolean fix closes it, the same way it closes the one on `/goals`. Its per-goal status
dot now takes its tooltip from `status_label` rather than composing a third vocabulary.

---

## 8. W-0190's acceptance needs amending — the wording

**Its fix was incomplete, not wrong.** The rule it introduced is right and stays. What its
acceptance never stated is *where the other half gets written*.

> **Proposed amendment.** A household's spending is written from one place, and **one
> request is sufficient to leave both accounts correct**. It is not acceptance that the two
> halves agree when both endpoints are called; it is acceptance that the household total is
> correct after the owner's save alone.

The test that encoded the gap has already been amended here.
`JointExpenditureSplitsByDeclaredModeTest.php:66` — *"leaves nothing uncharged — the two
halves are the household figure"* — **fired both endpoints itself, back to back**, and then
asserted the halves added up. That proved the two requests agreed with each other; it could
not see that the household total *depended* on both arriving, and it stayed green through
the night the second one did not. It now fires one, and the reasoning is left in the file
so nobody restores the two-request form.

---

## 9. Raised, not fixed

- **W-0413** — `rent` and `utilities` never persist from the expenditure form. Both
  endpoints accept them in the payload; neither `validate()` list includes them, so they
  are dropped silently. Masked for `peak_earners` because a main-residence owner has both
  fields filtered out of the grid; **a renting persona would lose their largest category.**
- **W-0414** — `GoalPlanService:170` and `:279` read `$progress['months_remaining']`, a key
  `GoalProgressService::calculateProgress()` has never returned. `:279`'s `?? 12` means
  every goal is planned on a twelve-month horizon regardless of its date.
- **W-0202 is unblocked on its criterion 2** — `HouseholdExpenditureWriter` now exists, so
  routing Fyn is one call per path once criterion 1 (expressible unanswered state, or
  disclosure of the default) is settled. Criterion 3's concern is also resolved by the
  writer: it mirrors only the fields `SharedExpenditure` divides, so `rent`, `utilities`
  and `charitable_donations` stay whole on the acting row and the field lists need not be
  reconciled to route the path.
- **W-0416** — iOS native carries two copies of the goal status vocabulary and cannot
  express Overdue or Achieved late. Backend fix already removes the false "On track"; the
  finer vocabulary needs a Swift change and a build. Its fixture holds two goals, both
  `is_on_track: true`, so the native suite cannot enter the branch either.
- **W-0415** — `workforce/registry/` does not exist, though `core/index.md` routes to it
  and the build-lead definition makes `capabilities.md` the first prior-art source. Every
  prior-art check under the current definitions is running on five of six sources.

---

## 10. The rule this batch produces

**When a fix establishes a rule, the acceptance must name every path the rule has to reach
— not just the path the defect was found on.**

W-0190 divided the household correctly and left the second half to a request nobody owned.
W-0029 made past-dated goals creatable and left the maths that reads them alone. In both
cases the fix was right and the acceptance was scoped to the symptom. The follow-on defect
was not a regression; it was the part that was never in scope.

**A corollary for tests, from the mutation results above:** forty goal tests passed with
the bug restored. Not one of them was wrong. Every fixture was simply future-dated, because
that is what a goal normally is. **When a suite cannot fail, the fixtures are the place to
look, not the assertions.**
