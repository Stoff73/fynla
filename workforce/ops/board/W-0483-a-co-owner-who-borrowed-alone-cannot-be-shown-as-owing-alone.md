---
id: W-0483
title: A co-owner who borrowed alone cannot be shown as owing alone, and only CSJ can change that
mission: M-0002-persona-fidelity
owner: null
status: blocked
severity: medium
surfaces: [web, m, ios]
created: 2026-08-25T12:00:00Z
claimed: null
blocked_by: [csj-decision]
gate: null
handoff_to: null
prior_art_checked: 2026-08-25
prior_art_found: [W-0228 (CSJ ruling, the thing this asks to reopen), W-0162 (established this is not fixable there), app/Traits/CalculatesOwnershipShare.php:226-253]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
source: raised by Brett while deciding W-0162, 2026-08-25
---

## Intent

**This item exists to ask CSJ a question, not to propose a fix.** W-0228 is recorded
as *"not open for re-litigation by any agent, plan, PR or sub-agent (Rule 18)"*, and
its consequence 5 says explicitly: *"Do not add a borrower-split field to work around
it, and do not raise it as a defect."* **It is therefore not raised as a defect.** It
is raised because a person asked for the capability, and only CSJ can grant it.

### The question

**Can a tenants-in-common property have a mortgage allocated 100% to one owner?**

In law, yes, though it is uncommon. A legal charge over the whole property needs every
legal owner to join in — the legal estate is always held on a joint tenancy, since
tenancy in common exists only in the beneficial interest. But a tenant in common **can**
charge their own beneficial share, creating an equitable charge enforceable via a
TOLATA 1996 s14 order for sale. Separately and much more commonly, one co-owner may
simply have taken the borrowing on alone.

**Fynla cannot express either.** A 40/60 tenants-in-common property splits its mortgage
40/60, always.

### Why it cannot be answered anywhere but here

`CalculatesOwnershipShare:226-253`, verbatim:

> **The property is authoritative.** … **Accepted limitation (CSJ, knowingly):** this
> cannot express a mortgage in one spouse's sole name against a jointly-owned property.
> Do not add a borrower-split field to work around it, and do not raise it as a defect.

And `:156-164` **throws** if any caller tries to derive a mortgage share from the
mortgage row. The rule is enforced mechanically, not by convention — which is the right
way to hold a ruling, and also why no smaller change can move it.

**W-0162 was checked first and cannot deliver this.** Widening
`mortgages.ownership_type` to accept `tenants_in_common` would not help: since W-0228
the share resolves from the property, so the type on the mortgage row is a borrower
label no calculation reads. Worse, `tenants_in_common` means *shared*, whereas this
scenario is one owner bearing the whole debt — the opposite. W-0162 is decided NO on
its own merits and closed.

### What W-0228 bought, and what it cost

CSJ ruled after two mechanisms disagreed and showed **one household two figures for one
debt, four inches apart** — property detail read *"Your Mortgage Share (40%) £48,000"*
while the Mortgage tab read *"Your mortgage liability £60,000"*. The ruling ended that
class of contradiction by making the property the single authority.

The cost is this item: a household where one person genuinely owes the whole mortgage
is shown as sharing it, and the person who did not borrow is shown carrying debt they
are not liable for. On a 40/60 split of a £120,000 mortgage that is £48,000 attributed
to someone who owes nothing.

## Acceptance

1. **CSJ decides**, and nobody else. Three shapes are possible; there may be others:
   - **Leave it.** The trade-off stands as ruled. If so, this item closes and the
     limitation should be surfaced in the UI rather than only in a docblock — a user
     looking at a figure that is wrong for their household currently gets no hint why.
   - **Allow an explicit override**, narrower than the borrower-split field that was
     forbidden — e.g. a stated mortgage liability share that beats the inherited one,
     the same "supplied beats inherited" shape W-0040 already established for
     `ownership_percentage`. This keeps the property authoritative by default.
   - **Reopen W-0228 fully.** Highest cost; reintroduces the two-mechanism risk the
     ruling closed.
2. If anything changes, `CalculatesOwnershipShare` is the ONE home — the exception at
   `:156-164` and the resolver at `:253` must move together, and no second reader may
   appear (Rule 20, and the explicit consolidation instruction in W-0228 §3).
3. Whatever is decided, the outcome is recorded on W-0228 itself, not only here, so the
   next reader of the ruling sees the amendment.

## Working notes

- 2026-08-25 Brett: raised while deciding W-0162. **Deliberately not implemented and
  deliberately not filed as a defect** — W-0228 forbids both, and that ruling is CSJ's
  to amend. Flagged as `blocked_by: [csj-decision]` so it surfaces at the top of the
  next session rather than sitting in the queue where an agent might "fix" it.
