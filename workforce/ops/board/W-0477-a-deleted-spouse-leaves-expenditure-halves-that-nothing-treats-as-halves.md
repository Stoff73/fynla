---
id: W-0477
title: A deleted spouse leaves both accounts' expenditure stored as halves, and every reader then treats those halves as whole household figures
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [quality-lead]
status: gated
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-24T10:40:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0190, W-0202, W-0278]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: quality-lead re-certification, 2026-08-24 — recorded as an out-of-scope observation on W-0202 and filed rather than left in a report
---

## Intent

Under a `joint` household, expenditure is stored as **each account's half**: the row
IS the share (`SharedExpenditure`). Every writer divides on the way in, and every
reader trusts what is stored.

Both the writer and the Fyn agent decide whether to divide using `liveSpouse()`,
which returns null once the partner's account is deleted. **The stored halves do
not change when that happens.** So the surviving account holds £600 of groceries
that means £1,200 of household spending, and from that moment every reader — the
affordability statements, the cash-flow projection, `/m`'s expenditure screen —
treats it as the whole.

**Direction: UNDERSTATES the household's spending by half, and therefore
OVERSTATES disposable income** — which is what every affordability statement rests
on. The same shape as W-0190's original defect, arriving by a different route.

Same family as **W-0278** (`LifeCoverReach` reading a deleted partner's policies):
a value that was correct while two accounts existed and silently changes meaning
when one goes.

## Acceptance

1. Decide what a deleted spouse means for stored shares — restore the household
   figure onto the survivor, or record that the stored value is a share of a
   household that no longer exists.
2. Whichever is chosen, a household whose partner is deleted shows the same
   disposable income before and after, or a stated reason why it changed.
3. Before/after measured on a real linked pair, not reasoned.
4. `/m` matches (Rule 19) — its expenditure screen reads the same rows.

## Working notes

- 2026-08-24 — Raised by `quality-lead` while attacking W-0202's arithmetic, and
  explicitly held out of it: *"Not W-0202's to fix; worth an item."* Filed here so it
  does not stay in a handoff report.
- 2026-08-24 — Note the retention decision that makes this reachable is deliberate
  (CSJ D1/D2, 2026-08-19: retain the rows, ignore them at read time). This is not an
  argument to reverse it — it is the consequence needing its own answer.

## Resolution — 2026-08-24

**Acceptance 1, the decision: restore the household figure onto the survivor** — the
first of the two options the item offered, not the marker column.

**Why not the marker.** The obvious read-side signal, "mode `joint` with no live
spouse", is ambiguous: `SharedExpenditure::DEFAULT_MODE` IS joint, so a user who has
never had a spouse carries it too and their figures were never divided. Doubling those
would invent spending. A marker column could disambiguate it; correcting the data at
the one moment its meaning changes is cheaper and leaves every reader untouched.

**`SharedExpenditure::householdOf()`** is the inverse of `shareOf()` and sits beside it
deliberately — the moment those two rules live in two files, one of them gets edited
alone. `HouseholdExpenditureWriter::promoteSharesToHousehold()` applies it, guarded so
it only fires for an account still declaring the shared mode with no live spouse, and
clearing that mode as it goes, so **a second call is a no-op rather than a second
doubling** (pinned by a test).

### Four severance paths, three mechanisms, because they do not share a signature

| Path | How it severs | Wired |
|---|---|---|
| account deleted (admin, self-service, anything future) | `spouse_id` stays set, `liveSpouse()` goes null | `SurvivingSpouseExpenditureObserver` |
| household unlinked (`DELETE /api/user/family-members/{id}`) | nulls `spouse_id` on **both** rows | the endpoint, for **both** survivors |
| GDPR erase / retention purge | query builder, **fires no model events** | `RetentionPurgeService` explicitly |
| `ResetPreviewData` | preview personas only | not wired — reseeded data, not a household |

**An observer rather than a line per endpoint.** This codebase's recurring failure is
the hand-maintained list — a rule applied at the call sites somebody remembered and
skipped by the next one added (`app/Http/CLAUDE.md` on enumerated mappings; W-0471,
W-0473, W-0479 for what it costs). A deletion is a deletion however it is issued. The
one path an observer cannot see is the purge, which is why that one is explicit and
says so at the line.

### Verification

`tests/Feature/UserProfile/SurvivingSpouseExpenditureTest.php` — 4 tests: the survivor
of a deletion, idempotency, a never-shared household left alone, and the unlink putting
**both** accounts back. **Mutation-checked**: removing the observer's promotion turns
the deletion case red. 148 tests green across UserProfile, the three spouse suites and
the profile services; 31 green across purge, erase, account and tier lifecycle. Pint
clean.

### NOT done

- **Acceptance 3 — before/after on a real linked pair — is NOT met.** Verified by test,
  not measured on live data. The dev database has no linked pair whose expenditure is
  stored as halves to measure against without creating one.
- **Acceptance 4 — `/m` not re-verified in a browser.** `/m`'s expenditure screen reads
  the same rows through the same API, so it follows by architecture, but nobody has
  looked at it since the change.
- The unlink case has a defensible alternative reading: two people who unlink may each
  now carry their own smaller costs rather than the whole former household's. Promotion
  errs toward MORE recorded spending, which understates disposable income — the safe
  direction for advice, and the opposite of the defect. **Flagged for CSJ rather than
  settled quietly.**
