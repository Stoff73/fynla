---
id: W-0226
title: The net worth liabilities breakdown charges the primary owner 100% of every shared debt — and its docblock describes a reciprocal-records model the application does not use
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T04:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0187, W-0203, W-0015]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `cycle2-ownership` after fixing **W-0187**. The fifth mechanism answering
"what does this user owe", outside the four items of that batch and **not fixed** —
scope discipline.

### The defect

`app/Services/NetWorth/NetWorthService.php:132`:

```php
$liabilities = Liability::where('user_id', $userId)->get();
```

Scoped to `user_id` alone, and every balance summed at **100%**. So a joint loan is
charged wholly to whoever recorded it and the co-owner is shown none of it — the same
failure W-0187 fixed in the profile summary and both protection paths.

`liabilities` carries `ownership_type`, `ownership_percentage` and `joint_owner_id`
like every other shared record, so both the reach and the fraction are available and
neither is used.

### The part that is worse than the missing share

**The method's own docblock states a data model the application does not have:**

> *"Each user has their own liability records. For joint liabilities, reciprocal
> records exist with each owner's share stored in `current_balance`."*

There are no reciprocal records. **Rule 6** is explicit — a joint asset is a SINGLE
record with `joint_owner_id` and `ownership_percentage`, never duplicated per owner —
and `liabilities` has all three columns. The reciprocal-per-owner pattern is precisely
what **W-0015 / F-0002** removed across nine mechanisms.

So this is not simply a missing call. **The code is correct for the model in its
docblock and wrong for the model in the database**, which is why it reads as
deliberate: at 100% with no share applied, it is exactly what you would write if
reciprocal records really existed. A reviewer checking the code against its own
documentation would pass it.

**Two things follow.** First, the fix is not only to apply the share — **the docblock
must go**, or the next person restores the bug from it. Second, this is worth a sweep:
if one file was written against the abandoned model, others may have been.

### Impact

Net worth is a headline figure on the dashboard, `/net-worth`, `/m` and native. A
household with a joint loan sees the whole of it against one spouse's net worth and
none against the other's — the two accounts disagree about the household's position,
which is the disease **W-0154** was filed for.

Direction: the recording spouse's net worth is **understated** and the co-owner's
**overstated**.

**Note it does NOT have W-0203's double count.** `:163` skips
`liability_type = 'mortgage'` deliberately, with a comment saying mortgages are tracked
via property mortgages. That part is right.

### Repro

1. A linked couple; a joint personal loan of £20,000 recorded by one spouse with
   `ownership_type = 'joint'`, `ownership_percentage = 50`, `joint_owner_id` set.
2. `/net-worth` as the recorder → `liabilities_breakdown.loans` = **£20,000**.
3. `/net-worth` as the co-owner → **£0**.
4. Expected £10,000 each.

**Not reproducible on the peak_earners persona**, which holds zero `liabilities` rows —
the same blind spot that hid W-0203. Found by reading, not by running.

### Acceptance

1. The breakdown uses the reach-complete, share-correct home:
   `CrossModuleAssetAggregator::calculateLiabilityTotals()` already exists and already
   does both (built under W-0187). **Route to it — do not add a sixth implementation.**
   Note it returns `mortgages` / `other` and this method needs `loans` /
   `credit_cards` / `other`, so either the aggregator grows a typed breakdown or this
   method composes per-item shares from the same helper. **The first is preferable** —
   one home for the categorisation as well as the share.
2. **The docblock is deleted or corrected.** It is the load-bearing part: it documents
   the wrong model and would restore the defect.
3. A third party's share is charged to nobody.
4. A sweep for other consumers written against the reciprocal-records model.
5. Verified in a browser on both accounts of a household with a joint non-mortgage
   liability — **a case that must be constructed; no persona covers it.**

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev` — and the docblock beside it now
  asserts something the schema contradicts, which is why nobody has looked.**
  `NetWorthService::calculateLiabilitiesBreakdown():138` is still
  `Liability::where('user_id', $userId)->get()`, and the loop below still takes
  `$liability->current_balance` at **face value** — no reach, no share.
  **The comment at :132-133 claims "For joint liabilities, reciprocal records exist with each
  owner's share stored in current_balance".** That is not how this application models joint
  records: `App\Models\Estate\Liability` is fillable on `ownership_type`,
  `ownership_percentage` and `joint_owner_id` (:21-23) and has a `joint_owner_id` relation (:63) —
  one record with a share, per Rule 6. A reader checking whether this was dealt with finds a
  comment saying it was, and stops.
  **The correct mechanism already exists**, six lines of it:
  `CrossModuleAssetAggregator::calculateLiabilityTotals()` (:404) reaches through
  `Liability::forUserOrJoint()` and applies `calculateUserShare()`. This is the same
  consolidation W-0187, W-0206 and W-0173 each completed for their own surface; net worth is the
  one that was left.
