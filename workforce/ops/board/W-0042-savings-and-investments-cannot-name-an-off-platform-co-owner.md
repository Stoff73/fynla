---
id: W-0042
title: Savings and investment accounts have no joint_owner_name column, so a shared account cannot name an off-platform co-owner
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [product-lead]
status: review
claimed: 2026-08-26
claimed_by: null
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: medium
surfaces: [web, m, ios]
source: raised by fix-batch-A during W-0025, 2026-08-21 — the boundary of where the counterparty rule could be applied
prior_art_checked: 2026-08-21
prior_art_outcome: extend
prior_art_found: [app/Support/SharedOwnership.php, app/Services/Stores/SavingsStore.php, database/migrations (joint_owner_name on properties/mortgages/chattels), app/Http/Requests/Chattel/StoreChattelRequest.php]
---

## Intent — a schema and product decision, not a bug

W-0025 established the house rule for shared assets: **a shared asset must name its
counterparty** — either a linked `joint_owner_id` or a free-text `joint_owner_name`.
`SharedOwnership::namesCounterparty()` is the single predicate, enforced server-side.

It is applied to **chattels, properties and mortgages**, which have a
`joint_owner_name` column. It is **not** applied to `savings_accounts` or
`investment_accounts`, which do not.

fix-batch-A was right not to enforce it there. On those two tables, joint + NULL is
**deliberately first-class** — `SavingsStore.php:357-361` documents it as "the
co-owner is not on the platform", and `CoordinatingAgentJointOwnerTest` exercises it
through Fyn. Enforcing the rule without the column would have **deleted a working
capability**, not fixed a bug.

## The question for CSJ

Should a shared savings or investment account be able to **name** an off-platform
co-owner, as a shared property, mortgage or chattel already can?

If yes: add `joint_owner_name` to both tables, plus their forms, their `/m`
counterparts, Fyn's catalogue, and the resources that expose it — then the counterparty
rule becomes universal and the "unnamed shared asset" state stops existing anywhere.

If no: the inconsistency is deliberate and should be recorded as such, so the next
person to notice it does not re-raise it.

## Working notes

Not blocking the persona: its joint savings and investment accounts are all shared
with Sarah, who is a linked user. This is about off-platform co-owners — the Mike
Barrett case, which the persona only exercises on a property.

---

## Decided and built 2026-08-26 — CSJ: yes, all four tables

The question was put with both answers costed. **CSJ chose all four**, not the two
this item names.

**The premise was wider than recorded.** `business_interests` and `liabilities` also
lack `joint_owner_name`. Neither carries a shared row today, so adding the column now
is cheap and closes the schema inconsistency rather than leaving two thirds of it for
someone to rediscover.

### What was built

| Layer | Savings | Investments | Business | Liabilities |
|---|---|---|---|---|
| Column (nullable) | ✔ | ✔ | ✔ | ✔ |
| Model `$fillable` | ✔ | ✔ | ✔ | ✔ |
| Store/Update request rules | ✔ | ✔ | ✔ | — (no request) |
| Store `validateCanonical` | ✔ | ✔ | — | ✔ |
| Normaliser (form + Fyn) | ✔ | ✔ | — | — |
| Resource | ✔ | ✔ | — | — |
| Web form | ✔ | ✔ | — | — |
| Fyn tool schema (`.md` + `.xai.md`) | ✔ | ✔ | — | — |

Business interests and liabilities get the column and the model; they have no
ownership form and no shared rows, so wiring surfaces they do not have would be
building for a hypothetical.

**`/m` (Rule 19):** the pathway carries no `joint_owner_id` anywhere — verified by
grep — so it has no ownership form to extend. It writes through Fyn, and the tool
schemas now carry the field, so the capability reaches `/m` and native the moment
Fyn is asked. Pinned by a test asserting the field is in the live catalogue, because
without it the capability would not exist on those surfaces at all.

**A round-trip defect avoided.** Both web forms now hold the *selection* separately
from `joint_owner_id` and reconstruct it when an account is reopened. Binding the
select straight to the id cannot express "someone not on the platform", and without
the reconstruction a shared account reopens with the select blank and saving it drops
the stored co-owner — which is exactly the W-0368 defect on property, one table over.

### Deliberately NOT done: enforcement

This item's "if yes" branch says the counterparty rule then "becomes universal and
the unnamed shared asset state stops existing anywhere". **That is a second decision
and was not taken.** `SavingsStore:357-361` documents joint-with-no-linked-owner as
first-class and `CoordinatingAgentJointOwnerTest` exercises it through Fyn; requiring
a name now would delete a working state under cover of adding a capability.

Enforcement belongs with **W-0142**, which is where `namesCounterparty()` gets
extended past chattels. The column existing is its precondition, and that is what
this delivered.
