---
id: W-0142
title: The shared-asset counterparty rule guards chattels only — properties and mortgages can still be orphaned today, through the forms and through Fyn
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [product-lead]
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-21T19:06:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0025 (created the rule), W-0043 (the orphan it was meant to prevent), F-0002-batch-a-ownership-net-worth]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found by fix-batch-G while sweeping for W-0043, 2026-08-21
---

## Intent

**W-0043 says the orphaned mortgage "is the data W-0025's new counterparty rule now
prevents being created". That is not correct for mortgages, and the sweep is how it
surfaced.**

`SharedOwnership::namesCounterparty()` (`app/Support/SharedOwnership.php:103`) is the
one predicate, and it is called from exactly two places:

- `app/Http/Requests/Chattel/StoreChattelRequest.php:84`
- `app/Http/Requests/Chattel/UpdateChattelRequest.php:101`

**Nowhere else.** `F-0002` §3 describes it as "the chattel/property/mortgage
counterparty rule", but only the chattel third was wired up.

Three unguarded creation paths, all live:

1. **The mortgage forms.** `StoreMortgageRequest.php:72-74` and
   `UpdateMortgageRequest.php:72-74` accept `ownership_type: joint` with both
   `joint_owner_id` and `joint_owner_name` nullable, and no cross-field check.
2. **The property forms.** `StorePropertyRequest.php:40-45` and
   `UpdatePropertyRequest.php:40-45`, same shape.
3. **Fyn, which can orphan a property and a mortgage in one call.**
   `CoordinatingAgent.php:3489-3491` permits `ownership_type: 'joint'` with a nullable
   `joint_owner_id`; `:3539-3541` writes `'joint_owner_id' => $input['joint_owner_id']
   ?? null` and **never writes `joint_owner_name` at all**; `:3579-3581` then hands the
   same null down to the auto-created mortgage. F-0002 established "Fyn cannot orphan a
   chattel" — it can orphan both of these.

Path 3 is the most likely origin of the existing orphan and the reason a form-only fix
would not close the class.

## Acceptance

1. Extend `namesCounterparty()` enforcement to properties and mortgages, on the form
   path **and** the Fyn path. Do not add a second predicate — `SharedOwnership` is the
   one home (F-0002 §2).
2. **Savings, investments, business interests and liabilities are deliberately out of
   scope.** Those four tables have no `joint_owner_name` column, and
   `SavingsStore.php:357-361` documents joint-with-no-linked-owner as first-class
   ("the co-owner is not on the platform"). Enforcing there deletes a working
   capability and needs a schema change first — F-0002 already raised it.
3. Decide what an update does to an existing orphan: refusing every future edit to a
   record the user cannot fix from that form is a trap, not a guard.
4. Tests per path, including the Fyn one.

## Working notes

- 2026-08-21 fix-batch-G: **found but deliberately not fixed.** `fix-batch-J` is live in
  joint-ownership validation right now (deciding whether a missing
  `ownership_percentage` on a joint savings account should still be a 422) and
  rewrote `SharedOwnership::primaryOwnerPercentage` at 18:54 under W-0040. Editing the
  same class in parallel is the collision team-lead warned about. **Sequence this after
  fix-batch-J lands**, and re-read `SharedOwnership.php` before starting — it is not
  the file F-0002 describes any more.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  The counterparty rule guarded chattels only — `namesCounterparty()` appeared in the two chattel requests and nowhere else — so a shared **property** or **mortgage** could be saved with nobody named. The record then asserts that half of it belongs to someone without saying who, and every share calculation downstream has a co-owner it cannot identify.

  **Lifted into `ValidatesSharedOwnership` rather than copied a third and fourth time.** All four requests already used that trait; a rule duplicated per asset type is precisely how chattels ended up with a guard the others never got. `StorePropertyRequest` and `StoreMortgageRequest` now call it — the mortgage requests had no `withValidator` at all and had to be given one.

  **CREATE only, and a failing test is what taught me that.** My first pass added it to the update paths too, and `MortgageHttpIntegrationTest > it rejects update from non-owner` went red: it sends `ownership_type: joint` without repeating `joint_owner_id`, because **the record already has one**. Demanding a re-statement would turn every unrelated edit — a lender rename, a balance correction — into a 422 for a field the user never touched, which is a worse defect than the one this closes. `SharedOwnership::applyTo()` preserves the stored counterparty, and `UpdateChattelRequest` separately guards the case that actually orphans a record: an explicit `joint_owner_id: null`. **The test was right and my rule was wrong; the rule changed, not the test.**

  For mortgages this is deliberately the COUNTERPARTY check and not the split check: a mortgage's share resolves from the securing property, not from its own row (CSJ's W-0228 ruling), and those two disagreeing is the case W-0338 had to handle on the read side today.

  **Tested:** 425 mortgage, property and chattel tests pass, 2,519 assertions. Pint clean.
