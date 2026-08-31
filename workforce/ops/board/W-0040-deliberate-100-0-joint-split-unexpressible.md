---
id: W-0040
title: A deliberate 100/0 joint split is unexpressible, and three acceptance criteria contradict each other on whether it should be
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [product-lead]
status: done
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
claimed: 2026-08-21T19:05:00Z
claimed_by: fix-batch-F
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: medium
surfaces: [web, m, ios]
source: raised by fix-batch-A 2026-08-21 while implementing W-0015; flagged rather than deviated silently
prior_art_checked: 2026-08-21
prior_art_outcome: extend
prior_art_found: [app/Support/SharedOwnership.php, app/Support/HoldingValuation.php, app/Agents/CoordinatingAgent.php, app/Services/Stores/Normalisers/PropertyNormaliser.php, app/Services/Stores/LiabilityStore.php, app/Http/Requests/Savings/StoreSavingsAccountRequest.php]
---

## Intent — a decision, not a bug

Three existing acceptance criteria cannot all hold:

- **W-0015** says a deliberate 100/0 joint split is preserved rather than rewritten.
- **W-0014** says match `PropertyController.php:154-158`, which rewrites 100 → 50
  unconditionally.
- `StoreSavingsAccountRequest` independently **rejects** any shared share outside
  (0,100).

fix-batch-A implemented the board's own proven-correct reference — rewrite at the
input boundary, remove the read-side rewrite — which is what makes every surface agree
and what delivered the consolidation. It flagged the contradiction rather than
deviating silently. **That was correct.**

## The question for CSJ

**Should a joint asset be able to hold a deliberate 100/0 (or any non-50/50) split?**

Today no form in the app exposes a share input for joint ownership, so a "deliberate"
100 is unreachable by a user — the only 100s in the data are artefacts. Note this is
distinct from `tenants_in_common`, which *does* carry an explicit percentage and is
used by the persona (David 40% / Mike Barrett 60% on the Manchester property).

If the answer is yes, it needs:

1. A share input on the joint-ownership forms, on web, `/m` and Fyn.
2. A decision on `StoreSavingsAccountRequest`, which currently rejects the very values
   that would make it expressible.
3. A rule for what the *other* side sees when the split is not 50/50.

If no, the current behaviour is correct and W-0015's criterion should be amended to
match, so the contradiction does not resurface.

## Working notes

Do not treat this as blocking the persona re-run: the persona's joint assets are all
50/50 or tenants-in-common, both of which work. This is about whether the model should
be able to express something it currently cannot.

### 2026-08-21, fix-batch-J — point 2 above is now out of date, and the behaviour is asymmetric

`StoreSavingsAccountRequest` no longer "rejects the very values that would make it
expressible". W-0013's fix routes the share through
`SharedOwnership::primaryOwnerPercentage()` in `prepareForValidation()`, before the
rules run. Measured, not inferred:

| `ownership_type` | submitted share | resolved and stored | outcome |
|---|---|---|---|
| joint | *not sent* | 50 | 201 |
| tenants_in_common | *not sent* | 50 | 201 |
| joint | 70 | 70 | 201 |
| tenants_in_common | **0** | 0 | **422** |
| tenants_in_common | **100** | **50** | **201** |

So the two ends of the same question are now answered differently: a caller who states
**0** is refused, and a caller who states **100** is silently rewritten to 50 and told
it worked. Nobody decided that asymmetry — it falls out of
`primaryOwnerPercentage()` treating a submitted 100 as the individual default a form
never cleared (`SharedOwnership.php` `$given === self::INDIVIDUAL_PERCENTAGE`), while
0 has no such meaning and reaches the (0,100) guard.

The coercion is defensible *today* precisely because no surface exposes a share input:
a 100 on a shared asset can only be an uncleared form default, never a human claim.
**It stops being defensible the moment point 1 of this item is built.** If a share
input ships without resolving this, the first user to type 100 gets W-0121's defect in
a new place — a figure typed, validated, 200'd and discarded.

This is now pinned rather than latent: `tests/Feature/Onboarding/CaptureAccuracyGateTest.php`
gates all five rows of the table above (`it resolves the share of a shared savings
account through the one shared rule`, plus the 0 rejection in `it rejects foreign
ownership links through the direct savings API`). A change to the default or to the
100 coercion now fails a named test instead of passing unnoticed.

**Recommendation to CSJ, if the answer to this item is "yes, make it expressible":**
distinguish supplied from inherited at the ownership boundary the same way W-0121 did
at the valuation boundary — an absent share defaults, a stated share is honoured or
refused, and no stated share is ever quietly rewritten.

- 2026-08-21 team-lead: **RESOLVED against CSJ's existing ruling. Not re-escalated —
  CSJ has already answered this and Rule 18 says do not make them re-explain.**

  CSJ's ruling, recorded earlier today in the answered list at
  `tests/Persona/20-08-2026_run/COORDINATOR-HANDOVER.md` §4:
  **"W-0040 (100/0 split) is nonsensical — that is individual ownership."**

  `fix-batch-J` measured the current behaviour while fixing the accuracy gate and it
  contradicts that ruling in a way nobody chose: **savings refuses a stated share of `0`
  and silently rewrites a stated `100` to `50`, returning 201.** So an input meaning
  *"I own all of it"* is stored as *"I own half of it"*, with no error and no indication.
  That is the same silent-discard disease as W-0121 (a typed holding value overwritten by
  stored units) and W-0026 (a policy end date validated, accepted and dropped) — the third
  instance today, at a third boundary.

  **The resolution follows from the ruling without needing a new one.** If a 100/0 split
  *is* individual ownership, then a stated 100 must resolve to individual ownership or be
  refused — it must not become a joint 50/50 record. Silently halving someone's stated
  share is the one option the ruling excludes.

  **Where the fix goes matters more than what it does.** `fix-batch-J` was right not to
  change the coercion inside the savings path: making savings the single asset type that
  rejects a stated 100, while property, investments and chattels coerce it, would recreate
  exactly the divergence W-0015 was raised to cure — one joint share computed several ways.
  **So this is fixed once, at the shared ownership boundary that `fix-batch-A` consolidated
  (`SharedOwnership`), for every asset type**, or not at all. Rule 20.

  Adopting `fix-batch-J`'s recommendation for the mechanism: **distinguish a share
  *supplied in the payload* from one *inherited or defaulted*, the same way `HoldingValuation`
  now distinguishes them at the valuation boundary.** A defaulted 50 for a form with no
  share input (which is W-0013's fix and must keep working) is a different fact from a
  user typing 100, and the current code cannot tell them apart. That single distinction
  resolves this item, and it is the same one-mechanism answer as W-0121.

  **Severity note:** harmless only while no surface exposes a share input. The first user
  to type 100 after one ships gets the defect. Sequence this before any joint-share input
  reaches a form.

  Status left as-is; the mechanism work belongs with whoever takes the `SharedOwnership`
  boundary next, not with the red-suite repair at the final gate.

### 2026-08-21, fix-batch-F — the mechanism census, and the blocker the item does not name

**Claimed for the mechanism half only.** The product half (a share input on the joint
forms, web + `/m` + Fyn) is not in scope and is not built here.

#### Six implementations of "the primary owner's share", five outside the one home

`fix-batch-A` consolidated nine onto `SharedOwnership` and the class docblock says "do
not add a ninth copy". Five were **never** part of that consolidation, because they are
on the Fyn and Store paths rather than the controller/normaliser paths it swept:

| # | Where | What it does |
|---|---|---|
| 1 | `app/Support/SharedOwnership.php` | the one home — 11 call sites |
| 2 | `CoordinatingAgent::handleCreateProperty` (~`:3470`) | its own `match ($ownershipType) { 'joint','tenants_in_common' => 50.0, default => 100.0 }` |
| 3 | `PropertyNormaliser::fromFyn` (~`:160`) | `isset ? (float) : elseif individual\|trust → 100.00` — never calls `SharedOwnership` |
| 4 | `CoordinatingAgent::handleCreateInvestmentAccount` (`:3034`) | `isset ? (float) : 100.00` on a joint account |
| 5 | `CoordinatingAgent::handleCreateLiability` (`:4003`) | `isset ? (float) : 100.0` on a joint liability |
| 6 | `LiabilityStore::create` (`:43`) | `??= $type === 'joint' ? 50 : 100` — `tenants_in_common` missing, same shape as the chattel gap W-0025 closed |

#4 is currently rescued downstream: the payload goes through
`InvestmentAccountNormaliser::fromFyn` → `normalise()` → `SharedOwnership::applyTo`,
and the very 100→50 coercion this item is about turns it back into 50. **#5 is not
rescued.** `handleCreateLiability` hands its payload straight to `LiabilityStore::create`,
whose `??=` cannot fire because the key is already set — so **a joint liability created
through Fyn is stored at 100/0**, which is W-0014's defect still live on that path.
Raised separately as **W-0161**; it is a behaviour bug, not a refactor, and it should not
be buried inside this item's notes.

#### The blocker: "supplied" is currently a lie

The item's resolution says to tell a share **supplied in the payload** apart from one
**defaulted or inherited**. Measured, that distinction cannot be read off the payload
today, because several callers state `ownership_percentage: 100` without meaning it:

- `resources/js/components/NetWorth/Property/PropertyForm.vue:1159` (property) and
  `:1205` (mortgage) initialise `ownership_percentage: 100` in form data and send it
  with every submission, joint included. There is no share input in that UI.
- `resources/js/components/NetWorth/ChattelFormModal.vue:333` (create) and `:439`
  (`this.chattel.ownership_percentage || 100`, edit) do the same.
- `resources/js/components/UserProfile/ExpenditureForm.vue:2057` likewise.
- Mechanisms 2, 4 and 5 above inject their own default before the boundary sees it.

That is exactly why `SharedOwnership::primaryOwnerPercentage()` treats a submitted 100
as absent, and why removing the coercion on its own would 422 every joint property,
chattel and Fyn-created account in the app. **Making the boundary honest and making the
callers stop lying are one change, not two** — Part 1 alone is a landmine.

Savings is the exception that proves it: its modal sends nothing, which is why the
W-0040 table shows `joint | not sent | 50 | 201` and why savings is the only surface
where the distinction already works.

#### The rule being built

Mirrors `HoldingValuation` (W-0121) at the valuation boundary — supplied beats
inherited, and an inherited figure never overwrites a stated one:

| ownership type | share in payload | result |
|---|---|---|
| shared | **absent**, no stored record | **50** — W-0013's fix, unchanged |
| shared | **absent**, updating a stored record | **the stored share is kept**, never re-defaulted |
| shared | supplied, `0 < v < 100` | honoured as given — unchanged |
| shared | supplied, `0` or `100` | **refused** (422). Never rewritten |
| not shared | absent | 100 — unchanged |

Refusal rather than "resolve to individual ownership": the item sanctions either, and
refusal makes no silent transformation of any kind, is symmetric with the `0` rejection
that already exists, and does not force a decision about what happens to
`joint_owner_id`. CSJ's ruling — a 100/0 split **is** individual ownership — is honoured
by refusing to store it as a joint record, which is the one option the ruling excludes.

**Inert today, by design.** No surface exposes a share input, and once the callers above
stop injecting a default, nothing in any current journey can reach the refusal branch.
It arms itself the moment the product half ships, which is what "sequence this before
any joint-share input reaches a form" asks for.

**The clobber trap this also closes.** Once the forms stop sending an inherited share,
`applyTo()` would inject the 50 default on every *update* too, silently rewriting a
stored 70 to 50. So the boundary takes the existing record the same way
`HoldingValuation::reconcile($data, $existing)` does: absent + a stored share means keep
the stored share. Without that, fixing the lie would create a worse one.

### 2026-08-21, fix-batch-F — MECHANISM HALF DONE

**A correction to this item's premise, found by reading the forms rather than
trusting the note.** "No form in the app exposes a share input for joint
ownership" is **false on two surfaces**:

- `resources/js/components/NetWorth/Property/PropertyForm.vue:299-302` — "Your
  Ownership Share (%)", `min="1" max="99"`, shown for `tenants_in_common`.
- `resources/js/components/NetWorth/ChattelFormModal.vue:224-234` — the same
  control, shown for `joint`, with a live "Your Share / Joint Owner's Share"
  split display.

So the silent 100→50 rewrite was **not** unreachable: it sat one paste or one
API call away on two live forms, and a legacy shared record stored at 100 loads
straight back into those inputs. Both already constrain to 1–99 client-side,
which is exactly the server rule now enforced — the client was right and the
server was not.

Two further copies of the rule live in those forms' watchers
(`PropertyForm.vue:1360-1373`, `ChattelFormModal.vue:372-380`), which set the
share themselves on an ownership-type change. They drive the on-screen split
display, so they are left alone; the fix is at the payload boundary — what gets
**sent** — not in the display model.

#### What changed

**The boundary** — `app/Support/SharedOwnership.php`:
- `primaryOwnerPercentage()` no longer treats a submitted 100 as absent. A
  stated share is returned exactly as stated; only `null`/`''` defaults.
- `statedShare()` — the supplied-versus-inherited distinction, mirroring
  `HoldingValuation::supplied()`.
- `isValidSharedSplit()` — `0 < share < 100`. The refusal predicate.
- `applyTo()` takes the stored record. An update stating no share keeps the
  share already on it, **but only when that share is itself a valid shared
  split** — an individual record's 100 is not a split to inherit, so converting
  individual → joint still re-defaults to 50 rather than storing 100/0.

**The refusal** — `app/Http/Requests/Concerns/ValidatesSharedOwnership.php`, one
home, called from all eight asset form requests (savings, chattel, property and
investment, store and update). Says nothing when no share was stated, so a form
with no share input never sees it. The savings requests' own
`prepareForValidation()` share-merge is **deleted**: it was a second defaulting
site, and on an update it injected a 50 over a stored share the caller never
mentioned.

**The five copies outside the one home, converged:**
- `CoordinatingAgent.php:3535` (property), `:3095` (investment account),
  `:4070` (liability) — each had its own default; all three now call
  `SharedOwnership::primaryOwnerPercentage()`.
- `PropertyNormaliser::fromFyn` — its inline `individual|trust → 100` replaced.
- `LiabilityStore::create:43` — its `??=` replaced, which also closes a
  `tenants_in_common` gap it had. **That one was a live bug, raised as W-0161.**

**The callers that stated a share they did not mean:**
- `PropertyForm.vue` — sends `ownership_percentage` only for
  `tenants_in_common`; never for the mortgage block, which has no share input.
- `ChattelFormModal.vue` — sends it only for `joint`.
- `ExpenditureForm.vue:2057` was checked and is **not** a caller: it is a
  display-only transform of widowed commitments, not an asset payload.

**Update paths now pass the stored record** so an absent share cannot clobber:
`PropertyController::update`, `ChattelController::update`,
`InvestmentController::update`, and `SavingsController::updateAccount` →
`SavingsAccountNormaliser::fromForm(..., existing:)`.

#### The resulting rule, verified end to end

| ownership type | share in payload | result |
|---|---|---|
| shared | absent, create | 50 |
| shared | absent, update of a record stored at 70 | **70 kept** |
| shared | absent, converting individual → joint | 50 |
| shared | `0 < v < 100` | stored exactly as stated |
| shared | `0` or `100` | **422**, nothing stored |
| not shared | absent | 100 |

#### Tests — 427 passed, 0 failed, `DB_DATABASE=laravel_testing_b`

New: `tests/Feature/NetWorth/StatedOwnershipShareTest.php` (15) — the table above
across savings, chattels, property and investment accounts.
`tests/Unit/Services/Stores/LiabilityStoreOwnershipTest.php` (4) — W-0161.
Two new cases in `tests/frontend/components/NetWorth/Property/PropertyForm.test.js`
pinning the payload contract (share omitted on joint, stated on tenants in
common) — that half is what the server rule rests on, and it would regress
silently.

**Five tests pinned the defect and were rewritten, not deleted** — each now
asserts the ruled behaviour and says what it used to assert and why:
`SharedOwnershipTest`, `MortgageServiceOwnershipTest`,
`JointChattelCounterpartyTest`, `SavingsApiTest`, `PropertyNormaliserTest` and
`MortgageNormaliserTest`.

#### What is NOT done

- **The product half is not built, and per CSJ's ruling it should not be.** The
  ruling answers this item's question "should a 100/0 split be expressible?" with
  no. Uneven splits that *are* splits remain expressible, and the two existing
  inputs are now safe rather than silently corrected. Nothing further is needed
  unless CSJ wants a share input on the joint (non-TIC) property form, which is a
  new product decision, not this item.
- **Legacy rows stored at 100 on a shared type are not repaired.** A user editing
  one on a form that shows the share input will now be refused with a clear
  message and a visible field to correct — honest, and only for records that are
  already wrong. On the joint property form, which has no share input, the form
  states no share at all, so those records save unchanged rather than dead-ending.
  Bulk repair belongs with the W-0043 / W-0161 data sweep, which is CSJ's call.
- **Not browser-verified by me** — a persona-tester closes Rule 14's loop.
- `/m` has no asset-entry forms, so nothing there states a share; the rule is
  server-side and reaches every surface through the one boundary.

- 2026-08-31 build-lead: **VERIFIED ALREADY FIXED AND TESTED — closed. The item's three contradictory acceptance criteria were settled by CSJ's ruling, and the code now states it.**

  **The ruling: a 100/0 split IS individual ownership.** So the split is not "unexpressible" — it is expressible as `individual`, which is what it actually is.

  `ValidatesSharedOwnership::validateSharedOwnershipSplit()` refuses a stated 0 or 100 on a shared type and **names the route instead of just rejecting**: *"A shared asset is split between two owners, so your share must be above 0% and below 100%. If you own all of it, choose individual ownership."* A validation message that says what to do instead is the difference between a rule and a dead end.

  Two supporting decisions are in place and worth keeping visible:

  - **A stated share is never rewritten.** `SharedOwnership::primaryOwnerPercentage()` distinguishes a share the caller ASSERTED from one they said nothing about. A submitted 100 on a shared asset used to be read as "the individual default a form never cleared" and silently rewritten to 50 — so a caller saying *"I own all of it"* was told 201 and stored as *"I own half"*, while a caller saying 0 was refused. Nobody chose that asymmetry; it fell out of the coercion.
  - **A partial update keeps the stored share.** `SharedOwnership::applyTo()` takes the existing record, so an update from a form with no share input cannot rewrite a stored 70 to 50.

  **Tested:** 176 ownership tests pass, 480 assertions.
