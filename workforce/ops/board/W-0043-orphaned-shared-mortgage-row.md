---
id: W-0043
title: One shared mortgage names no counterparty — half a real liability belongs to nobody
mission: M-0002-persona-fidelity
owner: build-lead
status: queued
claimed: null
claimed_by: null
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: medium
surfaces: [web]
source: found by fix-batch-A's orphan sweep during W-0025, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

The orphan sweep required by W-0025's acceptance found **one genuine orphan**:
`mortgages.id = 7`, `user_id = 14` — marked shared, with **no `joint_owner_id` and no
`joint_owner_name`**, at 50%. Half that liability is attributed to nobody, so the
user's net worth is wrong by half a mortgage.

This is the data W-0025's new counterparty rule now prevents being created. The rule
stops new orphans; it does not repair existing ones.

## Not the persona household

`user_id 14` is not David (16) or Sarah (17). **fix-batch-A correctly did not touch
another user's data** and reported instead.

Checked and legitimate, for contrast: properties 4 and 8 and mortgage 4 all carry
`joint_owner_name` values ("Mike Jones", "wife") — real off-platform co-owners, not
orphans.

## Acceptance

1. Decide what happens to a pre-existing orphaned share: repair, flag to the user for
   completion, or convert to individual ownership. **This is a data decision with a
   user-visible consequence — their net worth changes either way — so it is CSJ's
   call, not a silent migration.**
2. Whatever is decided, sweep for the same shape across every shared-asset table, not
   just mortgages. One orphan found means the guard was absent everywhere until W-0025.
3. If a repair migration is written, it must state how many rows it touched and their
   before/after values, per the standard set in W-0030.

- 2026-08-21 team-lead: **reassigned `fix-batch-G` → `fix-batch-F`.** Not a comment on
  batch G, which is carrying W-0101, the W-0102/W-0103 pair, W-0151, W-0044+W-0110 and
  W-0033 — that is more than one agent should hold. This item belongs with the shared
  ownership boundary work (W-0040's mechanism half and W-0042) rather than with the
  estate documents, and batch F now owns that boundary as a unit. **Do not add a fourth
  ownership mechanism**: `fix-batch-A` consolidated write logic onto `SharedOwnership`
  and display logic onto `ownership.js` today, replacing nine divergent implementations.

## Working notes

- 2026-08-21 fix-batch-G (build-lead): **acceptance 2 is complete. The code is
  `fix-batch-F`'s; this is the investigation, handed over.** Recorded per team-lead's
  instruction after the reassignment crossed the work. Full detail:
  `workforce/branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md` §3.

  **The sweep — every shared-asset table, read-only, nothing written.** Orphan defined as
  a shared `ownership_type` with no `joint_owner_id` and no `joint_owner_name`,
  soft-deletes excluded.

  | Table | Result |
  |---|---|
  | `mortgages` | **1 orphan** — `id=7, user_id=14, joint, 50.00`. The known one. |
  | `properties` | 0 |
  | `chattels` | 0 |
  | `savings_accounts` | 5 shared, **0** without a linked owner |
  | `investment_accounts` | 2 shared, **0** without a linked owner |
  | `business_interests`, `liabilities` | 0 shared rows at all |

  The last four tables have **no `joint_owner_name` column**, so they are counted
  separately rather than folded into the orphan total — `F-0002` §2 records
  joint-with-no-linked-owner as deliberately first-class there
  (`SavingsStore.php:357-361`, "the co-owner is not on the platform"). As it happens
  every shared row in all four already has a linked owner, so there is no population
  hiding behind that distinction either. **One orphan exists in this database, and it is
  the one already reported.**

  **Correction to this item's premise — the more useful half.** The Intent says the
  orphan "is the data W-0025's new counterparty rule now prevents being created".
  **It does not.** `SharedOwnership::namesCounterparty()` is called from exactly two
  places — `StoreChattelRequest.php:84` and `UpdateChattelRequest.php:101`. Nowhere else.
  `F-0002` §3 describes it as "the chattel/property/mortgage counterparty rule", but only
  the chattel third was ever wired up. Three creation paths remain open:

  1. `StoreMortgageRequest.php:72-74` / `UpdateMortgageRequest.php:72-74` — accepts
     `ownership_type: joint` with both counterparty fields nullable and no cross-field
     check.
  2. `StorePropertyRequest.php:40-45` / `UpdatePropertyRequest.php:40-45` — same shape.
  3. **Fyn can orphan a property and a mortgage in one call.**
     `CoordinatingAgent.php:3489-3491` permits `joint` with a nullable `joint_owner_id`;
     `:3539-3541` writes `'joint_owner_id' => $input['joint_owner_id'] ?? null` and
     **never writes `joint_owner_name` at all**; `:3579-3581` hands the same null to the
     auto-created mortgage. `F-0002` §4 established "Fyn cannot orphan a chattel" — it can
     orphan both of these, and this is the likeliest origin of `mortgages.id = 7`.

  So the class is **open, not closed**: the sweep is clean today and a new orphan can be
  created tomorrow, on the same table. Raised as **W-0142** and deliberately not fixed —
  `fix-batch-F` owns `SharedOwnership` and editing it in parallel is a collision.

  **Read this before deciding acceptance 1.** `F-0002` §3 recorded that *"W-0015's
  'preserve a deliberate 100/0' is NOT implemented"*. **W-0040 has now reversed exactly
  that** — `SharedOwnership::primaryOwnerPercentage` was rewritten 2026-08-21 18:54:58
  under CSJ's ruling that a stated 100/0 **is** individual ownership and a stated share
  must never be silently altered. Converting `mortgages.id = 7` to individual at 100 is
  therefore a coherent option in a way `F-0002` said it was not. **Decide on the current
  ruling, not on `F-0002`'s line** — that branch document was true when written and is
  false now.

  **Not done, and not mine:** acceptance 1 (the repair/flag/convert decision, CSJ's) and
  acceptance 3 (any repair migration). No data was read beyond the counts above and no
  row was written.
