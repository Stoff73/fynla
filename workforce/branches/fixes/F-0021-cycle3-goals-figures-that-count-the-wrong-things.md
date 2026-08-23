---
id: F-0021
type: fix
parent: core/constitution/07-quality-bar.md
applies: [core/constitution/08-process.md]
surfaces: [web, m, ios]
consistency_checked: 2026-08-22T00:00:00Z
status: active
---

# F-0021 — Cycle 3: figures that count the wrong things

**Agent:** build-lead (`cycle3-goals`) · **Branch:** `dev` (shared working tree)
**Board items:** W-0206, W-0210, W-0207 · **ID block:** W-0231 – W-0235
**Number and ID block issued by team-lead.**

**Predecessors, read before touching anything here:**
`F-0019-cycle2-ownership-applied-one-side-only.md` (the reach/fraction vocabulary,
and `CrossModuleAssetAggregator::calculateLiabilityTotals`) ·
`F-0002-batch-a-ownership-net-worth.md` (the single-record write rule) ·
**W-0029**'s test file `tests/Feature/Goals/PastDatedRecordsTest.php`, which
deliberately made past-dated events creatable and created the exact record
W-0207 is about.

---

## 1. The principle

**A figure that names what it is counting must count that.**

All three defects are one shape. Each is a number with a label — "Current Net
Worth", "cash outflow events", "Expected Income" — and in each case the label was
right, the arithmetic was competent, and the *set being summed* was the wrong set.

| Item | Label | What it actually summed |
|---|---|---|
| W-0206 | this user's net worth | every mortgage in the household, plus a non-user's share — or, for the other spouse, nothing at all |
| W-0210 | life events | life events **and goals** |
| W-0207 | money still to come | every event ever recorded, including one from 2020 |

**None of the three needed new arithmetic. All three needed the question asked of
the right set.** That is why they read as plausible: nothing on screen was
mis-added, and a reviewer checking the sums would have passed all three.

### What ties W-0206 to F-0019

F-0019 named two failures — **reach** (the derived set omits a party) and
**fraction** (the share is not applied). W-0206 is the first instance of **both in
one method**, which is exactly why it produced two errors running in opposite
directions on one household from a single line:

- the borrower's set was reach-complete and fraction-blind → **too much debt**;
- his spouse's set was reach-*empty*, so the fraction never ran → **no debt at all**.

An audit that had found only one of those would have "fixed" the household in one
direction and left it broken in the other.

### What this is NOT

**No new share implementation, and no new "is it upcoming" implementation.** Every
count of mechanisms goes down:

| Question | Before | After |
|---|---|---|
| What does this user owe on mortgages | 4 | **3** (the remaining one is W-0226, F-0019's, untouched) |
| What life-event money is still to come | 4 | **1** |
| Has this event already happened | 0 asked it; 1 clamp destroyed the evidence | **1** |

---

## 2. Prior art

Checked 2026-08-22 across `registry/capabilities.md`, the code, custom artisan
commands, open PRs and in-flight branches, the vault, and `.claude/skills|agents`.

| Instance | Prior art found | Outcome |
|---|---|---|
| W-0206 mortgage reach + fraction | `CrossModuleAssetAggregator::getMortgages()` and `CalculatesOwnershipShare::calculateUserMortgageShare()` — F-0019's two homes, already third-party-safe | **route** |
| W-0210 goals counted as events | `buildEventsArray()` already stamps `type` alongside `impact`; the distinction existed and was not used | **route** |
| W-0207 upcoming totals | `LifeEventIntegrationService::getModuleImpactSummary()` — the only site whose *fields were already named* `upcoming_*`, but it filtered by date not at all, so the naming was aspiration rather than behaviour | **extend** — extract to one home, route all four consumers |
| W-0207 the predicate | none — no mechanism anywhere asked whether an event had happened | **build**, one home (`LifeEvent::hasOccurred()`) |
| shared frontend helper location | `resources/mobile/utils/fynText.js`, imported cross-tree by `resources/js/components/Fyn/FynQuickReplies.vue` | **route** — same directory, same import style; a new `resources/shared/` would have been a second convention |

---

## 3. Constraints honoured

- **A third-party counterparty is not a user.** Mike Barrett's share is charged to
  nobody, and does not fall through to the spouse.

  > **CORRECTED 2026-08-22 by `cycle4-dashboard` (F-0022, W-0228).** This bullet
  > originally read *"David £182,500 + Sarah £122,500 = £305,000, not £365,000"*
  > and signed those figures off as correct. **They are wrong**, and the fix here
  > was not what made them wrong — it inherited them.
  >
  > The Manchester property is held `tenants_in_common` at **40%** while the
  > mortgage secured on it was stored `joint` at **50%**, and every consumer read
  > the mortgage's own pair. CSJ has since ruled that **a debt is shared exactly as
  > the asset securing it is shared**, which makes the property authoritative:
  >
  > | | Signed off here | Correct under the ruling |
  > |---|---|---|
  > | David's share of the Manchester mortgage | £60,000 | **£48,000** |
  > | David's mortgages | £182,500 | **£170,500** |
  > | Sarah's mortgages | £122,500 | £122,500 (unchanged — she is not a party to it) |
  > | Household debt | £305,000 | **£293,000** |
  >
  > **The £12,000 difference is a third party's debt**, which is precisely what
  > this bullet claims to have excluded. The exclusion of Mike Barrett's share was
  > real and remains correct; the *size* of his share was taken from the wrong
  > record. Verified against the live local database after the fix:
  > `calculateMortgageTotal(16) = 170,500.00`, `(17) = 122,500.00`.
  >
  > **Anything in this document resting on £305,000 or £182,500 inherits the
  > error** — the mortgage figures in the W-0206 evidence, and the projection note
  > in §5 that contrasts "£122,500 flat" against "£182,500 amortising" (the
  > order-dependence it describes is real and unaffected; only the number moved).
- **Users 16 and 17 were never written to.** Every test uses factories. The only
  writes anywhere near them were `Cache::forget` on projection keys, cleared again
  afterwards.
- **No migration was needed** and none was written.
- **Rule 20** one home; **Rule 19** `/m` and native named individually per item and
  established by grep; **Rules 9/12/15** no acronyms, no scores, no icons added.

---

## 4. Status — ALL THREE DONE

| Item | Outcome | One home |
|---|---|---|
| **W-0206** goals net worth wrong on both accounts, opposite directions | **DONE** · `handoff` → quality-lead | `CrossModuleAssetAggregator::getMortgages()` + `CalculatesOwnershipShare::calculateUserMortgageShare()` |
| **W-0210** a goal counted as a life event | **DONE** · `handoff` → quality-lead | `LifeEventService::summariseUpcoming()` |
| **W-0207** a past event counted as future income | **DONE** · `handoff` → quality-lead | `LifeEvent::hasOccurred()` + `LifeEventService::summariseUpcoming()` + `resources/mobile/utils/lifeEvents.js` |

Full per-item detail, file:line and measured figures live in the board files. This
is the index.

### Measured, live database, both accounts

| | Dashboard | `/goals` before | `/goals` now |
|---|---|---|---|
| David net worth | £1,477,500 | £1,295,000 | **£1,477,500** |
| Sarah net worth | £739,280 | £861,780 | **£739,280** |
| David cash outflow events | — | 9 / £1.1M | **6 / £355,000** |
| Sarah cash outflow events | — | 1 / £400K | **0 / £0** |
| David expected income | — | £595,000 / 3 | **£550,000 / 2** |
| Estate panel "next event" | — | Previous Inheritance (2020) | **Kitchen & Extension (2027)** |

---

## 5. In flight

**Nothing.** Every edit is applied, formatted and covered.

---

## 6. What the receiver needs, and would not otherwise know

1. **`/m` and native have no counterpart for W-0206 or W-0210, and this was
   established by grep rather than assumed.** `grep -rn "goals/projection"
   resources/mobile` returns nothing; the /m goals screen reads `/api/goals`,
   `/api/goals/dashboard-overview` and `/api/life-events` only.
   `GoalsClient.swift` calls those same three and nothing else, and grepping all of
   `ios-native` for `life_event|LifeEvent|has_occurred|years_until|goals/projection`
   returns **zero hits**. **W-0206's Acceptance 4 asks for `/m` verification of a
   surface that does not exist** — that is not a skipped step, and quality-lead
   should not go looking for it. W-0207 *did* reach `/m` and is fixed there.

2. **The storage semantic of nothing changed, but the API shape did.**
   `GET /api/life-events` now returns `data.summary`, and every serialized
   `LifeEvent` now carries `has_occurred`. Both are additive. The two frontends read
   the flag and derive their own totals from it rather than rendering
   `data.summary` directly — **deliberately**, because the Vuex store mutates the
   list locally on create/update/delete without refetching, so a served total would
   have gone stale the moment a user added an event. The *predicate* has one home;
   the arithmetic on top of it is one shared helper both trees import.

3. **`years_until_event` can now be negative.** Three web components render it and
   all three were updated to branch on `has_occurred` first. Nothing else consumes
   it — verified by grep across `resources/`, `app/`, `tests/` and `ios-native/`.

4. **The `/m` spec was asserting the bug and had to be corrected, not just
   extended.** It held a fixture event named `"Previous Inheritance (David's Aunt)"`,
   `status: 'completed'`, dated 2020-03-15, and asserted `'£395,000 expected in'` —
   the £350,000 plus that inheritance. A reviewer diffing the test file will see an
   assertion changed; that is the point, not a regression.

5. **The projection arithmetic still applies goals as outflows, on purpose.** Only
   the labelled count changed. There is a test pinning it so the fix is not reversed
   by someone concluding goals were dropped from the projection.

6. **Assumptions made, stated plainly:**
   - An event dated **today** has not happened yet. The day is not out, and W-0029's
     own test creates a "Bonus Paid Today" as a legitimate future record.
   - `status = 'completed'` implies occurred **even where the date is in the
     future** — the user has said so explicitly, and that outranks the calendar.
   - A past event belongs in the **list** and out of the **totals**. The alternative
     leaves a user able to see a stale figure's effects and unable to reach the row
     causing them.

---

## 7. Raised while working — three, none folded in

All three are outside the three items and none was silently fixed.

| Item | Why it is not folded in |
|---|---|
| **The projection picks the mortgage type by "last record wins"** | `$primaryType` is reassigned every iteration, so whichever mortgage iterates last decides whether the **whole** household balance amortises or stays flat. Pre-existing; W-0206 makes it visible because the spouse's set is no longer empty and ends on the interest-only record — her £122,500 now stays flat while her husband's £182,500 amortises. It does not touch year zero, so it is provably independent of W-0206's figure. A balance-weighted majority would change nothing on this persona and remove the order dependence, but it changes behaviour for other users. **A decision, not a tidy-up.** |
| **`$user->spouse_user_id` does not exist** | The column is `spouse_id` and there is no accessor, so Eloquent returns `null`. The household branch in `GoalsProjectionService::getGoalsForProjection()` **and** in `LifeEventService::getEvents()` is therefore dead code: `?household=true` silently returns individual data with no error and no visible difference. **It reads as working.** A genuine user-facing defect, not a cleanup, and larger than anything in this batch. |
| **A mortgage recorded as a `liabilities` row is valued at zero by net worth** | `NetWorthService::calculateLiabilitiesBreakdown()` skips `case 'mortgage'` because property mortgages come from the mortgages table; `getMortgages()` reads the mortgages table. **Neither counts it**, so it is £0 on the dashboard and £0 on `/goals`. `calculateLiabilityTotals()` disagrees with both and counts the user's share — which is what protection uses. **Protection charges the debt and net worth omits it**, a £25,000-per-spouse disagreement on the probe fixture. Not W-0226 and not previously raised; the mirror image of W-0203, which was a double count of the same shape. **Needs an ID.** |
| **Three different phrasings of one timing string** | `LifeEventCard` renders "In / N years", `LifeEventDetailInline` "In N years", `ModuleLifeEvents` "in N months" / "in N.N years" via its own `formatTimeUntil`. **Deliberately not consolidated.** `years_until_event` is measured from the start of the current calendar year and then ceilinged, so an event eight months away already reports 1 and one dated *today* reports 1. Unifying the phrasing without first fixing that would spread a wrong number consistently rather than fix it; fixing the arithmetic changes every future timing string in the application. Also worth noting: `ModuleLifeEvents::formatTimeUntil`'s sub-year "months" branch is **unreachable** given an integer input that is ceilinged to at least 1 — it only ever fired for past events, rendering them "in 0 months". |

---

## 7a. The non-mortgage acceptance constraint, checked after the fact

Team-lead issued it after this batch was built, so it was checked rather than
designed to. **It is met**, and checking it earned two findings and a tripwire —
full detail in W-0206's addendum.

- **The fix was never mortgage-only.** Non-mortgage liabilities reach the
  projection through `$otherLiabilities`, a path that pre-dates this work. Covered
  from the first version of the suite by a £12,000 credit-card test asserting both
  agreement with the dashboard and that the figure *moves* by exactly £12,000.
  Now strengthened to the shape the tester enters next cycle: a **joint hire
  purchase beside the third-party mortgage**, asserted on both accounts.

- **One correction to the constraint as written.** Routing `getMortgageParameters()`
  to `calculateLiabilityTotals()` would have been the wrong turn, not the fix.
  That method returns three floats; this one must produce
  `{original_balance, annual_rate, remaining_years, mortgage_type}` for a
  forty-year amortisation. **The projection has two liability paths by design** —
  mortgages amortise on a schedule, everything else decays as a bucket — and the
  other half was already correct. The right routing was to the aggregator's
  **reach** primitive plus the trait's **fraction**, which is what was done.

- **What the tester will find next cycle, measured now.** With a joint hire
  purchase present, `/goals` equals the dashboard on **both** accounts — the
  contract holds — but the £24,000 is charged **wholly to the borrower and not at
  all to the joint owner**, where the aggregator says £12,000 each. **That is
  W-0226**, identical on both surfaces, already filed. The suite therefore asserts
  *agreement* and deliberately does **not** pin the split, because pinning today's
  answer would bake W-0226's bug into a goals test and turn its eventual fix red.

- **W-0226 was read from, never written to.** `liabilities_breakdown` is consumed
  exactly as before and `calculateLiabilitiesBreakdown()` is untouched. Nothing here
  absorbs any part of it.

- **A divergence hazard, now tripwired.** `/goals` derives its mortgage figure from
  `getMortgages()` and **never reads `liabilities_breakdown['mortgages']`** — the
  dead assignment that appeared to was removed here. So the day anybody teaches
  `NetWorthService` to count mortgage-typed liability rows, the dashboard moves and
  `/goals` does not: **W-0206 reintroduced by a fix to something else.** A test now
  goes red at that moment rather than a persona run finding it. It passes today
  because both sides are zero, and its docblock says plainly that it is a tripwire
  and not a claim that zero is right.

---

## 8. Files this batch owns

**New:** `resources/mobile/utils/lifeEvents.js` ·
`tests/Feature/Goals/GoalsNetWorthMatchesDashboardTest.php` ·
`tests/Feature/Goals/LifeEventTotalsCountWhatTheySayTest.php`

**Modified — backend:** `app/Models/LifeEvent.php` ·
`app/Services/Goals/GoalsProjectionService.php` ·
`app/Services/Goals/LifeEventService.php` ·
`app/Services/Goals/LifeEventIntegrationService.php` ·
`app/Http/Controllers/Api/LifeEventController.php`

**Modified — frontend (web):** `resources/js/components/Goals/EventsTab.vue` ·
`resources/js/components/Goals/LifeEventCard.vue` ·
`resources/js/components/Goals/LifeEventDetailInline.vue`

**Modified — `/m`:** `resources/mobile/views/modules/Goals.vue`

**Modified — tests:** `resources/mobile/views/modules/__tests__/Goals.spec.js`

---

## 9. Test evidence

| Run | Result |
|---|---|
| `GoalsNetWorthMatchesDashboardTest` (new) | **7 passing** (13 assertions) |
| `LifeEventTotalsCountWhatTheySayTest` (new) | **9 passing** |
| Goals, LifeEvents, tier caps, composite plan, AI direct-write | **45 passing** (141 assertions) |
| Protection, Savings, Estate, Unit/Services/Goals, Unit/Models | **238 passing** (788 assertions) |
| Unit/Agents, Unit/Services/Shared, Architecture | **290 passing**, 1 skipped (the known cassette-provenance skip) |
| Full vitest | **1,136 passing**, 111 files |
| `./vendor/bin/pint` on touched paths | passed; imports verified intact after the formatter hook |

**No browser verification of this batch's own work, by instruction.** Quality
certifies.

---

## 10. The fixture blind spot, one cycle on

F-0019 recorded the fixture variant of *a test that shares the code's misconception
cannot fail* and warned that `peak_earners` holds **no liabilities and no business
interests**, so any fixture built from it is blind exactly where W-0206 lives. That
warning was acted on: this batch's fixture holds a non-mortgage liability and a
mortgage co-owned with a non-user, and two of its tests assert the answer **moves**
when the input moves rather than asserting equality with a value the test itself
supplied.

**Two further instances of the same family turned up in one batch, which is worth
recording because both were invisible in the way the guidance predicts.**

**The clamp, in my own new test.** `expect(ending_net_worth)->toBeLessThan($without)`
passed against an asset-less fixture because cash is floored at zero — *"Failed
asserting that 0.0 is less than 0.0."* The fixture variant and the clamp variant in
a single assertion, written by someone who had read §4 that morning. The fix is not
a better assertion, it is a fixture with £900,000 in it plus a guard that the
baseline is non-zero, so the probe cannot silently sink onto the floor later.

**The clamp, in production, as the defect itself.** W-0207's `max(0, …)` is the
first case where a clamp did not merely make a *test* unfalsifiable — it made the
**application** unable to distinguish two states. The information was destroyed at
the accessor, so every one of four downstream surfaces was blameless and wrong.
**A clamp on a signed quantity discards the sign, and the sign is often the whole
fact.** Worth carrying into the next audit: when a figure cannot be wrong in one
direction, ask what happened to the other direction.
