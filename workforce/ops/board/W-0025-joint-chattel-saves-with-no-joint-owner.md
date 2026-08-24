---
id: W-0025
title: A joint chattel saves with no joint owner and no error — 50% of the asset belongs to nobody
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: gated
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T09:40:00Z
claimed: 2026-08-21T11:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: ['App\\Support\\SharedOwnership (built for W-0015; chattels were the missing fourth reader)', 'ChattelController::store:81 fifth copy of the joint 50% rule', 'chattels/properties/mortgages joint_owner_name column', 'SavingsStore:357-361 — off-platform co-owner documented as first-class']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **`/m` discovery sweep** (entry phase), local
`localhost:8000`, premium. Account **David Jones (16)**.

**Surface:** desktop web, `/net-worth/chattels` → Add Valuable → Ownership Type "Joint".

**Not in Batch A.** Batch A covers savings (W-0013), investments (W-0014), share
computation (W-0015), the property card label (W-0016). Chattel ownership validation
is untouched by it.

### Expected

Selecting Ownership Type = **Joint** should require a joint owner before saving —
either by validation, or by defaulting to the linked spouse. A shared asset must have
someone to share it with.

Compare **savings**, which takes the opposite (also wrong) extreme: it *hard-rejects*
a joint account because the form never sends `ownership_percentage` (W-0013). Between
them these two modules bracket the correct behaviour without either hitting it.

### Actual

I saved three joint chattels — Contemporary Art Collection £35,000, Georgian Writing
Desk £8,500, BMW X5 £42,000 — by choosing "Joint" and not touching the Joint Owner
select. All three saved with **200/201 and no error**:

```
chattels.id 14  Contemporary Art Collection  ownership_type=joint  pct=50.00  joint_owner_id=NULL
chattels.id 16  Georgian Writing Desk        ownership_type=joint  pct=50.00  joint_owner_id=NULL
chattels.id 18  BMW X5 xDrive40i             ownership_type=joint  pct=50.00  joint_owner_id=NULL
```

The result is an asset marked 50% owned by David with the **other 50% attributed to
no one**. Because every joint read uses `WHERE user_id = ? OR joint_owner_id = ?`, the
spouse cannot see it at all, and the missing half never appears in any household
total.

To be explicit about attribution: **the NULLs were my omission, not the app forcing
them.** The form does have a working Joint Owner picker
("Select joint owner / Sarah Jones (Spouse - Linked Account) / Other (Enter Name)")
and a "Your Ownership Share (%)" field defaulting to 50/50. I re-edited all three and
set Sarah correctly. The defect is that **nothing stopped me**, and a real user who
misses that select gets a silently orphaned asset.

### Evidence

After correcting them, ownership reads correctly and the spouse sees the right set:

```
Sarah (user_id=17 OR joint_owner_id=17) sees:
  Sarah's Engagement Ring (individual, hers)
  Contemporary Art Collection (joint)
  Georgian Writing Desk (joint)
  BMW X5 xDrive40i (joint)
and correctly does NOT see David's Jaguar E-Type or First Edition Books (individual).
```

Report: `reports/R-07-m-sweep.md`.

### Repro

1. Premium account with a linked spouse.
2. `/net-worth/chattels` → Add Valuable → any type, name and value.
3. Ownership Type = **Joint**. Leave "Joint Owner" on "Select joint owner".
4. Save. It succeeds.
5. `chattels.joint_owner_id` is NULL while `ownership_type` is `joint`.
6. Log in as the spouse — the asset is invisible to them.

## Acceptance

- [ ] A chattel with `ownership_type` joint cannot be saved without a
      `joint_owner_id` **or** a free-text `joint_owner_name` — validated server-side,
      not only in the form.
- [ ] Decide and apply ONE house rule for "joint selected but no owner given" across
      chattels, savings, investments and property, rather than the current three
      different behaviours (chattels save orphaned, savings hard-reject, investments
      save at 100%). Rule 20 — one answer, one place. Coordinate with W-0013/W-0014.
- [ ] Any existing rows with `ownership_type='joint'` and `joint_owner_id IS NULL` are
      identified and reported — they are invisible to half their owners today.
- [ ] `/m` and iOS chattel entry checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found while entering the persona's six chattels for the
  `/m` sweep. Not fixed by me — routed to build-lead.
- The chattel form is otherwise **good**: it has the joint-owner picker, an explicit
  "Your Share / Joint Owner's Share" 50/50 display, and an adjustable percentage —
  more complete than either the savings or investment ownership UI.
- Persona note for whoever verifies: the BMW X5 is deliberately recorded at £42,000
  current value against a £65,000 purchase price (`peak_earners.md:480`). That loss is
  correct, not a data error — do not "fix" it.

- 2026-08-21 build-lead: **FIXED — folded into the W-0015 consolidation, not patched separately.**

  You were right that this is the fourth instance. `ChattelController::store:81`
  carried a **fifth** copy of the joint-share rule (and it handled `'joint'` but
  not `'tenants_in_common'`). Chattels now read the same
  `App\Support\SharedOwnership` that property, investment, savings and mortgages
  read — `ChattelController:83` (store) and `:152-158` (update). W-0015 was
  reopened for this rather than bolting a fourth copy on the side.

  **The house rule (acceptance bullet 2), stated once:** *a shared asset must
  name its counterparty — a linked `joint_owner_id` OR a free-text
  `joint_owner_name`.* One predicate, `SharedOwnership::namesCounterparty()`,
  enforced server-side in `StoreChattelRequest` and `UpdateChattelRequest`. The
  update guard resolves the merged state against the stored record, so sending
  only `joint_owner_id: null` cannot orphan an already-joint chattel.

  **Where the rule is NOT yet applied, and why — this needs a decision.**
  `savings_accounts` and `investment_accounts` have **no `joint_owner_name`
  column** (chattels, properties and mortgages do). For those two, an
  off-platform co-owner can only be expressed as joint + NULL, and
  `SavingsStore.php:357-361` documents that state as deliberately **first-class**
  ("the co-owner is not on the platform"). Enforcing the predicate there would
  delete a working capability rather than fix a bug. So the rule is one
  predicate in one place, applied to the three modules whose schema can satisfy
  it. Making it universal needs `joint_owner_name` added to those two tables plus
  their forms and `/m` — a schema change, which is its own work item with its own
  prior-art record. **Raised, not silently skipped.**

  **Existing orphan rows (acceptance bullet 3) — the full sweep:**

  | Table | id | user | share | joint_owner_name | verdict |
  |---|---|---|---|---|---|
  | mortgages | 7 | 14 | 50.00 | NULL | **ORPHAN — half the liability belongs to nobody** |
  | properties | 4 | 4 | 40.00 | Mike Jones | legitimate off-platform co-owner |
  | properties | 8 | 14 | 50.00 | wife | legitimate off-platform co-owner |
  | mortgages | 4 | 4 | 40.00 | Mike Jones | legitimate off-platform co-owner |
  | chattels | — | — | — | — | none (the tester had already corrected all three) |
  | savings_accounts | — | — | — | — | none (no rows without an id) |
  | investment_accounts | — | — | — | — | none |

  **One genuine orphan exists: `mortgages.id = 7` (user 14, not the persona
  household).** I have not modified it — repairing other users' data is not mine
  to decide. It is reported here per the acceptance bullet.

  **Verified through the real HTTP stack** (`POST/PUT /api/chattels` with a
  Sanctum token, full middleware + FormRequest, as Sarah Jones (17)):

  | Case | Before | After |
  |---|---|---|
  | Joint, no counterparty | 201, orphaned row | **422** `joint_owner_id: "Choose who this is owned with, or enter their name."` |
  | Joint + linked spouse | — | **201**, `pct 50.00`, `joint_owner_id 16`, `user_share 17500` of `full_value 35000` |
  | Joint + off-platform name only | — | **201**, `joint_owner_name "Mike Barrett"`, `pct 50.00`, `user_share 4250` of `8500` |
  | Joint + stale `ownership_percentage: 100` | 100/0 | **50/50** |
  | PUT stripping the counterparty | would orphan | **422**, record unchanged |

  All probe rows were deleted and the temporary token revoked; the persona
  household is back to 6 chattels, unchanged.

  **Also fixed while here:** `ChattelResource` did not expose `joint_owner_name`,
  so the card could not name an off-platform co-owner — it fell back to the
  (null) `jointOwner` relation and rendered nothing. Now exposed, which makes
  W-0016's `coOwnerName()` work for chattels. Its `is_shared` also carried its
  own inline `in_array(...)` copy; it now calls the trait.

  **Fyn is not affected:** `CoordinatingAgent::handleCreateChattel:4954-4956`
  always writes `ownership_type: 'individual'`, `ownership_percentage: 100`, so
  no AI path can produce an orphan and none is blocked by the new guard.

  Tests: `tests/Feature/Chattels/JointChattelCounterpartyTest.php` (7 cases) and
  3 new cases in `tests/Unit/Support/SharedOwnershipTest.php`.

  **GAPS:**
  - **I COULD NOT CLICK THIS THROUGH THE FORM.** Another agent was mid-session in
    the shared browser as a user it had just created (Adam Hall, id 19, created
    11:23 today); hijacking that login would have broken their run. Verified at
    the HTTP layer instead — which is where the guard lives — plus feature tests.
    The form click-through is routed to Quality / the persona re-run.
  - `/m` and iOS chattel entry not checked (`/m` posts to the same endpoint and is
    therefore covered server-side; the client-side error rendering is not).
  - The BMW X5 £42,000-against-£65,000 loss noted in the item was left alone.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
