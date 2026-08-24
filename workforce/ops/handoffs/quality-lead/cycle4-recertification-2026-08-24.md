# Cycle 4 re-certification — quality-lead, 2026-08-24

Sibling to `cycle4-certification-2026-08-23.md`. Same standard: **CERTIFIED** ·
**REJECTED** (unmet criterion named) · **CANNOT CERTIFY** (what is missing).

Scope: the five rejections from the 2026-08-23 pass, plus the new work on
`26564407f`, `bc9156718`, `e4aa4cdc9`, `484197e14`, `a8fa14e21`.

**You asked me to assume there is a fourth defect. There are two, and one of them is
a live wrong figure on a screen.** Both are below, with the evidence.

---

## FOUND — 1. The enumeration oracle is not closed. It moved one endpoint over.

**This is the "did I simply move it" check you asked for, and the answer is: not where
you looked, but yes.** You closed the Fyn door correctly — `CoordinatingAgent` withholds
`spouse_user_id` for any pending invitation, and you were right that returning it "when
it exists" answers the same question. The door that is still open is
**`GET /api/spouse-permission/status`**, and it is structural rather than cosmetic.

**The mechanism.** Only one of the two invitation branches can create a
`SpousePermission` row, because an unregistered address has no user id to key one on:

- `SpouseLinkingService.php:267` — registered branch: `createPendingSpouseInvitation($currentUser->id, $lockedSpouse->id)`.
- `SpouseLinkingService.php:415-432` — `inviteUnregisteredSpouse()`: **no permission row**, cannot have one.

`SpousePermissionController::status()` then branches on exactly that row's existence:

| Caller invited… | `status` returns |
|---|---|
| a **registered** address | `awaiting_their_response: true`, `permission: {the row}` (`:88-117`) |
| an **unregistered** address | `requires_account_link: true`, `permission: null`, and a different `message` (`:180-195`) |

**Two requests distinguish any address**: POST it to `/api/user/family-members`, then GET
`/api/spouse-permission/status`. Different keys, different message, different shape.

**The irony worth recording**, because it shows how the fix passed its own review: the
comment at `:170-179` reasons carefully that *"this endpoint cannot tell them apart"* —
and it is right about the branch it is attached to. The branch **two above it** tells them
apart perfectly. The care went into not disclosing the account holder's *name* in the
outgoing branch; the tell is the existence of the branch, not its contents.

**Same root cause as W-0472, which you filed.** `family_members` has no email column, so
there is nowhere to record "invited, waiting" for an address with no account. **You found
the UX half of that gap and missed the security half.** They close together.

**Stated fairly, this is much weaker than what you fixed.** The original was one request,
unthrottled. This one needs two requests, is gated by the 5/hour per-user invite throttle
you kept (`FamilyMembersController.php:143-152`), and — the real mitigation —
**the probe is not silent: the address being probed receives an email.** 120 probes a day,
each one announcing itself to the person being probed.

**Not a reason to reject W-0349.** Its acceptance is about the family-members endpoint and
that endpoint is genuinely fixed. **Raise it as a new item.**

---

## FOUND — 2. `e4aa4cdc9` deleted a published key and left its consumer reading it

**This is the fourth defect, and it is inside the round-four fix.**

`app/Services/Plans/EstatePlanService.php` — the round-four commit removed this line from
the **current** block and did not re-add it:

```
-                'charitable_deduction' => $ihtCalc['charitable_deduction'] ?? 0,
```
`git show e4aa4cdc9 -- app/Services/Plans/EstatePlanService.php`

At HEAD, `grep -n charitable_deduction app/Services/Plans/EstatePlanService.php` returns
**one line — 511, the projected entry.** The current entry is gone.

The consumer still reads it:

```
205:          now: summary.current.charitable_deduction || 0,
206:          minus5: summary.current.charitable_deduction || 0,
207:          projected: summary.projected.charitable_deduction || 0,
```
`resources/js/components/Plans/Estate/EstateCurrentSituation.vue`

**So on `/plans/estate` the charitable-exemption row now reads £0 in its current and
minus-five columns while the projected column carries the real figure.** And
`IHTController.php:161` still publishes `charitable_deduction` in *its* current block, so
`/estate/inheritance-tax` shows it correctly.

**Two screens now disagree about one figure — which is W-0135 and W-0154's exact disease,
reintroduced by the commit fixing the last round of it.** The comment at
`EstatePlanService.php:444` still reads *"Build iht_summary matching IHTController response
shape"*, and the two producers no longer match.

**`e4aa4cdc9` contains no tests at all** — five code files, zero test files. Nothing pins
F2, F5, F6, F3 or F8, which is why this was free to happen.

---

## FOUND — 3. Rule 9: "AIM" survives in user-facing text, including the `(AIM)` form

`484197e14` correctly rewrote the caveat to "shares listed on the Alternative Investment
Market" and added a test asserting the absence of "AIM". Two live strings were not
touched, and **Rule 9 has no grandfather clause** (unlike Rule 15):

- **`resources/js/components/Estate/IHTCalculationTable.vue:118`** — rendered on the web
  Inheritance Tax table for the same cohort the caveat targets:
  *"Does not model Agricultural Property Relief or AIM shares."*
  **This is the same claim as the caveat, on the web surface — one home fixed, the other
  not. A Rule 20 second home, not merely a Rule 9 slip.**
- **`app/Services/Investment/Recommendation/TransferRecommendationService.php:866-872`** —
  *"Consider AIM shares for Inheritance Tax planning"*, and at `:868`
  **"the Alternative Investment Market (AIM)"** — precisely the parenthesised form your
  commit message says would be a Rule 9 amendment and CSJ's alone.

Also unchanged: `InsightsController.php:159` still emits *"Gifting strategies and trust
planning could help reduce this"* on `/m` — a second efficacy claim on an Inheritance Tax
figure, from a parallel mechanism to the one finding E corrected.

---

## THE FIVE REJECTIONS, RE-JUDGED

### W-0349 — CERTIFIED

All three criteria met, each checked rather than taken:

1. **Throttle survives the rewrite** — `FamilyMembersController.php:143-152`, per-user key
   `spouse-invite:{id}`, 5/hour, still off the shared per-IP bucket.
2. **Both branches are byte-identical**, and it is real rather than cosmetic because the
   behaviour behind them converged: `createAndLinkNewSpouse()` is gone, and **both paths
   call `upsertFamilyMemberRow($currentUser, null, $data)`** — `linked_user_id` is NULL in
   each (`SpouseLinkingService.php:273` and `:432`), so the `family_member` object in the
   response cannot differ either. I checked that specifically, because the payload is the
   obvious place for the oracle to survive.
3. **Invite-only per CSJ's decision** — no users row, no forged permissions, no temporary
   password for an account nobody asked for.

**The corrected test genuinely guards.** `array_keys($unregistered) === array_keys($registered)`
would have failed pre-fix (`created`, `email_sent`, `spouse_user` existed on one side only),
and comparing key *sets* rather than a hand-written list is the right instrument — a field
added to one branch re-opens the oracle and a list would not notice.

**Its blind spot, for the record:** it compares the family-members response only, so it
cannot see finding 1 above. Correct for its scope, blind to the door beside it.

### W-0190 / W-0202 — CANNOT CERTIFY. Acceptance 4 unmet.

**The maths is sound. I attacked all three cases you named and it holds.**

- **Three sequential partial edits.** Traced by hand: Groceries 400 → stored 200; then
  Utilities 200 → household 800 − 400 + 200 = **600** (= 400 + 200 ✓); then Groceries 500 →
  household 1200 − 1000 + 500 = **700** (= 500 + 200 ✓). No decay.
- **A `separate` household.** Symmetric, and this is the part that could easily have been
  wrong: the agent's `$isShared` (`CoordinatingAgent.php:5315`) and the writer's
  (`HouseholdExpenditureWriter.php:66-68`) are the same predicate — `liveSpouse() !== null`
  **and** `SharedExpenditure::isShared($mode)`. Neither doubles, neither divides.
- **A category outside `SHARED_FIELDS`.** Added undoubled in the reconstitution and
  subtracted undoubled in the replacement loop, and `shareOf()` does not divide it. Both
  sides agree because both ask `SHARED_FIELDS`.

**The case I hunted and did not find, which you should still guard.** The two predicates do
not read the same *source*: the agent reads `$user->expenditure_sharing_mode`; the writer
prefers `$household['expenditure_sharing_mode'] ?? $user->...`. If a turn ever carried a
mode change alongside categories, they would disagree — doubling on the old mode and
dividing on the new, halving or doubling the household in one turn. **It cannot fire today
because `handleSetExpenditure` never writes the mode into the payload** (`grep` returns only
reads, at `:5276` and `:5315`). It is one line away from firing and deserves a comment where
the writer picks its source.

**Why it cannot be certified.** Acceptance 4 requires verification **from Fyn, on web AND
`/m`, on both accounts of a linked household**, and the criterion says why: *"`/m` is the one
that matters: its expenditure screen is read-only, so Fyn is the only expenditure edit door
there."* The only browser evidence recorded is a **web profile-form save** stamping
`expenditure_sharing_mode_declared_at` — not a Fyn conversation, not `/m`, not a second
account. **The item does not declare this gap**, which is the part I would fix in the record
as well as in the work.

Also open: the item names a **fourth door** — `update_profile` with `section: expenditure` —
with "the identical shape", and the 2026-08-24 notes do not say it was routed.

Observation, out of scope: a **deleted spouse** leaves this account's rows holding halves
while `liveSpouse()` returns null, so both sides stop treating them as shares. Same family as
W-0278. Not W-0202's to fix; worth an item.

### W-0012 — CANNOT CERTIFY. The fix is real; the test is not what it says it is.

The derivation is genuinely better than the list it replaced, and recovering eight more
dropped fields is the sort of thing a rule does and a list never will. Browser-verified with
`mortgages.rate_fix_end_date = '2029-06-30'` read back.

**But the new test does not assert sender/receiver parity.** You told me it "asserts the
SENDER can express what the RECEIVER accepts". It does not. In
`PropertyWizardMortgageFieldParityTest`, the receiver's field list is computed and then
**never used again** — `$accepted` is dead after an `expect(...)->not->toBeEmpty()`. The
assertion carrying the weight is a **source-text match**:

```php
expect($wizard)->toContain("key.startsWith('mortgage_') ? key : `mortgage_\${key}`");
```

It catches a literal revert to the hand-copied list — which is worth having. It does **not**
catch a semantically identical rewrite (`'mortgage_' + key`), the derivation being moved
outside its guard and becoming dead, or a receiver field the sender cannot emit. **No
JavaScript executes.**

**This matters more than a normal weak test, because of why W-0012 was rejected.** It was
rejected for a test that passed while the bug was live by POSTing keys straight to the API —
taking a different door from the browser. The replacement takes a **third** door: it reads
the file as text. Neither test has ever exercised the sender.

Two further gaps, neither declared:

- **The edit path is untouched.** `PropertyList.vue:253` — `api.put('/properties/${id}', data.property)`
  sends only `data.property` and discards the emitted `data.mortgage`, while
  `PropertyForm.vue:1571-1590` populates the mortgage fields in edit mode. **A user editing
  mortgage details on an existing property still loses every one of them.** Same disease,
  sibling branch.
- **Rule 19.** `surfaces: [web, m, ios]`, but `/m` and native have no property form — their
  only create door is Fyn, and `CoordinatingAgent::handleCreateProperty` (`:3549`) accepts
  five mortgage fields, not the nine. The wizard fix does not reach them.

### W-0008 — REJECTED, unchanged.
Not done, as you say. The criterion stands: the fee is enterable and has never been shown to
reach the projection it is entered for.

### W-0138 — REJECTED, unchanged. And your re-scoping question, answered.

**You read it differently from me, and the difference is one string.**

W-0138's fault 3, verbatim:

> **No Inheritance Tax figure at all**, under a subtitle that promises "Inheritance tax
> exposure". The screen shows what the estate is worth and never what it would cost.

**It has two halves and W-0469 settles only one.** CSJ's decision legitimises the missing
figure — `/m` Premium is deliberately a summary that hands off, and the card and button are
built. That half is genuinely resolved, and you are right about it.

**The subtitle is untouched.** `resources/mobile/views/modules/Estate.vue:2`:

```
<MobileChrome title="Estate" subtitle="Inheritance tax exposure and planning" …>
```

W-0138's acceptance 3 is a **disjunction** — *"shows an Inheritance Tax liability, **or** its
subtitle stops promising one"*. The second limb is the escape hatch the item wrote for
exactly the outcome CSJ chose, **and it has not been taken. Neither limb is satisfied.**

So: **it moves fault 3, it does not resolve it — and what remains is a one-string fix**, not
a re-scope. Fault 2 (individual-versus-household basis) is still open on the item's own
account and unaddressed since 2026-08-21: `/m`'s headline reads `netWorth.net_worth`
(individual) labelled "Estimated estate value" with no basis stated, against a pooled
household figure on web.

**Recommendation: do not re-scope. Change the subtitle, decide fault 2's basis label, close
the item.** It is closer to done than it looks.

---

## TODAY'S OTHER WORK

**W-0325 and W-0327 were already certified** in the 2026-08-23 addendum — both CERTIFIED,
verified against HEAD at the time. No change.

**W-0465 · W-0466 · W-0467 · W-0469 — CANNOT CERTIFY, gates still open**, unchanged from
yesterday, but the work moved under them and the record should say how:

`e4aa4cdc9` carried the W-0466 caveat to three surfaces it had missed — `/plans/estate`
(`EstatePlanService.php:470-476`), `EstateAgent`'s summary (`:345-351`) and `/m` Insights
(`InsightsController.php:153-168`) — and fixed a **rendering bug that made it invisible**:
`.me-caveat` used `--violet-800`, which `resources/mobile/style.css` does not define
(it declares `--violet-400` and `--violet-500` only), so the caveat text fell back to the
browser default on `/m`. **A caveat nobody can read is not a caveat**, and that is a good
catch — it is the same class as W-0273's line-clamp check.

`484197e14` rewrote the copy per compliance, including the Rule 9 expansion of "AIM" and a
**new third branch** in `EstateIhtExposureDetector` for the unlinked-partner case. Gates:
W-0465 `tax-compliance-reviewer`; W-0466 and W-0467 `compliance-lead`.

**W-0470 — CANNOT CERTIFY. `gate: tax-compliance-reviewer`, and acceptance 1 is partial by
the item's own account:**

> REMAINING, and it is visible on screen: the per-liability detail rows still come from the
> breakdown, so the panel shows "Chris's Liabilities −£3,500" above a Total Liabilities of
> £0. The totals are right and the detail beneath them is not.

Criteria 2, 3 and 4 are met. Criterion 1 asks for **one** projected-liabilities mechanism;
there are still two, and the surviving one is now visibly contradicting the total above it.
Declared honestly, which is why this is a block and not a rejection.

---

## Attribution — which of these you introduced, and which you inherited

You asked for this implicitly by saying three of your fixes were found defective. The
round-four commit's own attribution checks out against the diffs:

| Defect | Origin |
|---|---|
| F2 — `/plans/estate` second copy of the projection formula | **Introduced by `8f09eaddc`** (it added relief to the service and controller, not to `EstatePlanService`, so the overwrite stopped agreeing) |
| F3 — caveat on only one of four surfaces | **Introduced by `88494e0fd`** — its own Rule 20 gap |
| F8 — `--violet-800` undefined on `/m` | **Introduced by `88494e0fd`** |
| F5, F6 — controller overwrites | **Pre-existing**, correctly filed as W-0470 rather than folded in |
| `charitable_deduction` current-column regression | **Introduced by `e4aa4cdc9`** — finding 2 above |

Three of four self-inflicted defects came from **the same shape: a figure that has to be
named in four places to reach a screen**, and W-0465's own working note said so at the time:
*"the result block ENUMERATES the projected keys … four places, the same shape as W-0134 and
W-0399."* You wrote down the mechanism that was about to bite you, and it bit you twice more.
That is the strongest argument on this board for a shape test over another careful edit.

---

## THE TEST RUN — what I have, and what I do not

**I do not have a full-suite result for this HEAD, and I am not going to imply one.**

My run started 08:36 and was **killed by the harness at roughly 64 minutes**, before
Pest printed its summary. The output file has **no `Tests:` line and no exit code** — it
is an incomplete run, not a failing one.

**It contained 209 `⨯` marks across 23 files, and I nearly reported them.** A naive
`grep -cE '^\s+(⨯|FAIL)'` returned **232** — the exact number from the 2026-08-22/23
phantom incident. That coincidence is what made me stop and check instead of relay.

Three things said it was contamination, not regression:

1. **Failures were interleaved with passes**, starting ~45% through and with passes
   resuming afterwards — not a clean break, which is what a dropped database mid-run
   looks like.
2. **The red files are nowhere near the five commits** — `GithubIssueServiceTest`,
   `AssetAllocationOptimizerTest`, `InsightTemplateServiceTest`, `InlineHoldingsTest`.
   Nothing in the spouse, expenditure, property or estate diffs touches them.
3. **All four passed in my clean 2026-08-23 run** and failed in this one.

**Then I re-ran them in isolation, which is what `tests/CLAUDE.md` says to do, and they
are green:**

```
   PASS  Tests\Unit\Services\Integrations\GithubIssueServiceTest
   PASS  Tests\Unit\Services\Insights\InsightTemplateServiceTest
  Tests:    13 passed (18 assertions)
```

**And a targeted pass over the four contaminated files plus the tests that actually guard
today's work:**

```
tests/Unit/Services/Investment/AssetAllocationOptimizerTest.php
tests/Unit/Services/Investment/InlineHoldingsTest.php
tests/Feature/Api/SpouseLinkConsentTest.php
tests/Unit/Agents/CoordinatingAgentHandleSetExpenditureTest.php
tests/Feature/Property/PropertyWizardMortgageFieldParityTest.php
tests/Unit/Services/Tiers/EstateIhtExposureDetectorTest.php

  Tests:    72 passed (240 assertions)
  Duration: 130.69s
```

**So: the four "failures" were not real, and the six files covering the new work are
green.** What I cannot tell you is whether the *other* ~7,800 tests are green at this
HEAD, because that run did not finish.

**This is a gap, not a result.** Re-running it is one command and about 35 minutes on a
quiet machine — worth doing before any merge, and worth doing when nothing else is
running, since this attempt took 64 minutes and still did not finish.

---

## CORRECTION to my 2026-08-23 record — W-0466's residual is closed

Yesterday I wrote of W-0466: *"the trigger is business interests only … a farmer holding
land and no company still sees nothing."* **That is no longer true and the record should
not stand.**

`IHTCalculationService.php:252` now reads
`($holdsBusinessInterest || $holdsAgriculturalAsset)`, on a CSJ decision dated 2026-08-24
recorded at `:232`. The agricultural test is a word-prefix match at a boundary
(`:71-78` — `farm`, `agricultur`, `arable`, `pasture`, `grazing`), and the tests pin both
directions: *"the caveat triggers on farmland, and not on everything else"*, four cases,
all green in my run above.

**One limitation, stated rather than raised as a defect** — it matches on asset *names*,
so farmland recorded as "Top Field" or "Manor Estate" will not trigger it. The docblock at
`:247` says as much and names the durable fix (an agricultural asset type). CSJ chose the
heuristic knowingly over a schema change; that is a decision, not an oversight.


---

## FOUND — 4. `484197e14` destroyed three board items

**Not a code defect. A records defect, and it takes two of the three compliance gates
with it.** Found while trying to stamp my verdict on W-0349 and discovering it had no
frontmatter to stamp.

```
SHRANK in 484197e14: W-0349-…-account-enumeration-oracle.md   228 ->  23
SHRANK in 484197e14: W-0466-…-agricultural-relief-nor-aim.md  121 ->  27
SHRANK in 484197e14: W-0467-…-household-figure-to-one-persons-estate.md  78 ->  29
```

All three lost **everything except the note that commit appended**: the YAML frontmatter
(`id`, `status`, `gate`, `severity`, `surfaces`, `reviewers`), the Intent, the Acceptance
criteria, every prior working note, and — for W-0466 and W-0467 — **my 2026-08-23
certification stamps.** The signature is an overwrite where an append was intended: only
the new text survives, and the file now opens on two blank lines and a mid-document
bullet.

**Why this is worse than losing three files.** W-0466 and W-0467 are two of the three
items **gated on `compliance-lead`**, and the gate is discharged *against the acceptance
criteria*. Those criteria no longer exist in the repo. A reviewer opening W-0466 today
finds a note about an unsubscribe route and no statement of what they are being asked to
approve. **W-0349 is the item I have just certified, and its record of what it was
certified against is gone.**

**A sweep of all 271 board files found no other truncation** — these three, one commit,
one operation.

**Fully recoverable, and I am not doing it**, because these are the coordinator's
documents and restoring them is an edit to work I am gating, not an act of gating. The
prior content is one command away:

```
git show 26564407f:workforce/ops/board/W-0349-family-members-endpoint-is-an-account-enumeration-oracle.md
git show 88494e0fd:workforce/ops/board/W-0466-the-estate-screen-does-not-say-it-models-neither-agricultural-relief-nor-aim.md
git show 88494e0fd:workforce/ops/board/W-0467-the-free-teaser-attributes-a-household-figure-to-one-persons-estate.md
```

Restore each, then re-append the compliance note that `484197e14` added — it is the only
content in the current files and it is worth keeping.

**Consequence for this pass:** I could not stamp W-0349, W-0466 or W-0467. Their verdicts
stand in this document — **W-0349 CERTIFIED, W-0466 and W-0467 CANNOT CERTIFY on
`compliance-lead`** — and the board will not show them until the files are restored.

**And the general point, which is yesterday's Finding 3 with the volume turned up.** I
wrote that `status: handoff` and the acceptance checkbox carry no information. This is the
next step along that road: the acceptance criteria themselves are now mutable by accident,
with no guard. Nothing in the repo would have caught this — no test, no hook, no sweep.
**A one-line check that every `board/W-*.md` begins with `---` and parses as frontmatter
would have caught it at commit time**, and is the cheapest guard on this board.

