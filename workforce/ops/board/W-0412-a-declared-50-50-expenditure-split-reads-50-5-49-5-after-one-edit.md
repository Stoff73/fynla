---
id: W-0412
title: A declared 50/50 expenditure split reads 50.5 / 49.5 after one edit, because the spouse's half was left to a second HTTP request
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0029-cycle4-goals-and-expenditure-split.md
owner: build-lead (fix-cycle4-goals-expenditure)
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T02:00:00Z
claimed: 2026-08-23T02:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0190, W-0202, W-0011, W-0413, SharedExpenditure, OnboardingService::processExpenditureInfo]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Live, `peak_earners`, immediately after a save and still there after a full reload:

> **Entry Mode: Detailed Breakdown · Joint (50/50) expenditure**
> Essential Living — David **£400** · Sarah **£375** · Household **£775**
> Manual Expenditure Total — David **£1,250** · Sarah **£1,225** · Household **£2,475**

Three faults on one screen: a declared 50/50 reading **50.5 / 49.5**; Essential Living
household **£775** where the categories give **£800**; household **£2,475** where it is
**£2,500**.

### What is actually stored — the framing in the defect report needs two corrections

The 22 category columns are on **`users`**, not `expenditure_profiles`. And Sarah's
£1,225 is **not derived** — she has a full row, and it is stale:

```
u16 David : healthcare_medical 50.00   monthly 1250.00  annual 15000.00
u17 Sarah : healthcare_medical 25.00   monthly 1225.00  annual 14700.00
```

`healthcare_medical` is the **only** divergence between the two rows, and it is exactly
the £400/£375/£775 and £1,250/£1,225/£2,475 on the screen.

### What happened, from `audit_logs`

```
#593  2026-08-21 08:47  actor 16 -> u16   healthcare 0 -> 50,   monthly 50 -> 2450
#1332 2026-08-22 08:04  actor --  -> u16   healthcare 50 -> 25,  monthly 2450 -> 1225   (W-0190 remediation script)
#1334 2026-08-22 08:04  actor --  -> u17   healthcare 0 -> 25,   monthly NULL -> 1225   (same script, Sarah created)
#1376 2026-08-22 20:24  actor 16 -> u16   healthcare 25 -> 50,  monthly 1225 -> 1250   <- the edit
```

**There is no #1377 for user 17.** The edit wrote one row. Not Fyn — zero `ai_messages`
for user 16 in the 20:00–21:00 window, and the halving is visible (household 100 stored
as 50), which only the profile path did.

### Root cause

`UserProfileController::updateExpenditure` halved the acting user's row and **stopped**.
The spouse's half was written by a **separate second HTTP request** the frontend was
trusted to send (`ExpenditureOverview.vue:96-102` → `PUT /api/users/{id}/expenditure`).
The backend never required it, never verified it, and could not compensate when it did
not arrive.

**The household was then computed as *David's half + Sarah's half*, so when the halves
disagreed the household total inherited the error instead of being the source of truth.**

**This is W-0190's residual.** That fix made the split 50/50 at rest and was correct; it
did not give the path anywhere to put the other half. The architecture guarantees this
failure mode — the specific lost request is incidental.

### Three further paths found by enumerating (Rule 20)

| Path | Behaviour before |
|---|---|
| `CoordinatingAgent::handleSetExpenditure` (Fyn, all surfaces) | wrote **whole**, no division, no spouse — **this is W-0202, parked; NOT fixed here** |
| `CoordinatingAgent` `update_profile` `section: expenditure` (simple total) | same shape — **also left as it is, same reason** |
| `ExpenditureForm.handleSave` joint branch → `OnboardingService::processExpenditureInfo` | emitted `{userData, spouseData}` for **joint** as well as separate; that service routes on those keys and took its **separate** branch, writing the full household figure whole to **both** accounts — the double count W-0190 ended on the profile path |

## Acceptance

1. Editing any category in Joint (50/50) leaves David's half **equal to** Sarah's half,
   and the household equal to the sum of the categories — **£2,500, not £2,475**.
   Essential Living household reads **£800**.
2. **One request is sufficient.** The household total must be correct after the owner's
   save alone, with no second call.
3. Separate mode still stores each person's own figures whole, untouched by the other.
4. The test starts the two rows **out of step** (`tests/CLAUDE.md` §4, Collision) — under
   a correct 50/50 the halves are the same number, so a symmetric fixture cannot
   distinguish a mirrored write from no write at all.
5. Verified in the live browser on web; `/m` inherits through the shared API
   (`resources/mobile/views/Expenditure.vue` is read-only, no mobile edit surface).

## Notes

**Baseline:** the fifteen categories sum to **£2,500**, `annual_expenditure` is 30000,
each half is **£1,250**. The £2,450 in older artefacts was a transcription error in
`PASS-PLAYBOOK.md` (Healthcare & Medical recorded as £50 against the persona's £100),
corrected 2026-08-22.

**A dependent figure moves with this:** Sarah's emergency-fund runway reads 25.3 months
because it divides by her stale £1,225 (fund ≈ £30,993). It should read ≈ **24.8** once
her stored row moves to £1,250, on `/risk-profile`, `/dashboard` and `/m` together. One
surface lagging is a regression to chase.

**Reads are unchanged** — this is a write-side fix. A pre-existing mismatch heals on the
next save, not on read. No backfill was run.

---

## Correction — 2026-08-23, build-lead (`fix-cycle4-goals-expenditure`)

**I routed Fyn's two expenditure writes through the new writer, then found `W-0202` and
reverted them.** Recording it here rather than quietly.

W-0202 governs exactly that routing, carries a **team-lead decision**, and its acceptance
criterion 1 — *make the unanswered sharing state expressible, or disclose the default* —
**must be settled first**. It is marked "NOT to be built this cycle". The prerequisite is
disclosure, not arithmetic: `expenditure_sharing_mode` is `NOT NULL DEFAULT 'joint'`, so a
household that has never been asked reads identically to one that chose, and the form can
halve on that default only because it says "Joint (50/50) expenditure" on screen while the
user types. Fyn says nothing.

**The profile-path fix in this item stands** — W-0202 itself rules it defensible on exactly
that disclosure grounds. `HouseholdExpenditureWriter` is now the mechanism W-0202's
criterion 2 asks for, so that work is one call per path once its criterion 1 lands.

**My prior-art check had not swept `workforce/ops/board/`.** The board is prior art; a
queued item is a decision already taken, not an absent one.

---

## Outcome — 2026-08-23, build-lead (`fix-cycle4-goals-expenditure`)

**FIXED and browser-verified. One request now leaves both accounts correct.**

`app/Services/Expenditure/HouseholdExpenditureWriter` is the one home for the write;
`SharedExpenditure` remains the one home for the rule. Both profile endpoints route through
it. The frontend's joint-mode `{userData, spouseData}` emit is deleted — which also stops
`OnboardingService::processExpenditureInfo` taking its **separate** branch for a joint
household and writing the full figure whole to both accounts.

```
                          David    Sarah    Household
BEFORE  Essential Living   £400     £375     £775
        Manual Total     £1,250   £1,225   £2,475
AFTER   Essential Living   £400     £400     £800
        Manual Total     £1,250   £1,250   £2,500
```

**Criterion 1 — met.** Halves equal, household equals the sum of the categories (£2,500),
Essential Living £800.
**Criterion 2 — met, and this is the proof.** Network: `221. [PUT]
/api/user/profile/expenditure => [200]` and **no `PUT /api/users/17/expenditure`.** Audit:
**`#1465 actor=16 target=17`** — one row, for the spouse, and none for David because his row
was already correct. The exact mirror of `#1376`, which wrote 16 and nothing for 17.
**Criterion 3 — met**, covered by a case asserting the spouse's own figures survive under
separate mode.
**Criterion 4 — met.** Every new case starts the two rows **out of step**, because under a
correct 50/50 the halves are the same number and a symmetric fixture cannot tell a mirrored
write from no write at all.
**Criterion 5 — met.** Runway moved on **all three surfaces together**: `/dashboard` 24.8,
`/risk-profile` 24.8, `/m` 24.8 (was 25.3); `/m` expenditure £2,485/£29,820 (was
£2,460/£29,520). **David's 79.80 control did not move.**

**A second fault closed on the way:** Sarah had **no `ExpenditureProfile` row**, so she
resolved her outgoings from `users.monthly_expenditure` while David resolved from his
profile row. Both now carry 1250.00.

**No backfill needed** — the household healed on the save, and every category now agrees
between the two rows (`categories where the halves disagree: NONE`).

Tests: `JointExpenditureSplitsByDeclaredModeTest.php` +7 (17). **M4: writer stops mirroring
→ 6 red, separate-mode and deleted-spouse cases correctly green.** Branch doc §3, §4, §6.3.
