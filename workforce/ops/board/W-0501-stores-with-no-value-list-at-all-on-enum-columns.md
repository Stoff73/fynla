---
id: W-0501
title: The seventh axis — eighteen enum columns have no accepted-value list in their Store at all, and two Stores validate nothing whatsoever
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0329, W-0326, W-0263, W-0324]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

W-0329 swept the Stores' accepted-value lists against their columns and their
requests, and closed every divergence it found. **It could only ever check lists
that exist.** A Store with no rule for an enum column has nothing to diverge from,
so it passes both directions of the guard silently.

Found while completing W-0329, by asking the complementary question: not *"do the
lists agree?"* but *"which enum columns have no list at all?"*

**Eighteen enum columns across five Stores have no `in:` rule in the Store that
writes them.** Thirteen of those are bounded in full by the matching form request,
which is the W-0329 asymmetry exactly — the web form validates and returns a clean
422, and the Fyn capture path does not check at all.

| Store | Table | Enum columns with no Store rule | Request bounds them? |
|---|---|---|---|
| `GoalStore` | `goals` | `goal_type`, `assigned_module`, `priority`, `status`, `contribution_frequency`, `ownership_type`, `property_type` | **Yes, all seven** |
| `LifeEventStore` | `life_events` | `event_type`, `impact_type`, `certainty`, `ownership_type`, `status` | **Yes, all five** |
| `SavingsStore` | `savings_accounts` | `access_type` | **Yes** |
| `InvestmentAccountStore` | `investment_accounts` | `contribution_frequency`, `platform_fee_type`, `platform_fee_frequency`, `risk_preference` | No — unbounded on both layers |
| `PensionStore` (DC ruleset) | `dc_pensions` | `risk_preference` | Not probed |

## The sharper half — two Stores validate nothing at all

`GoalStore` and `LifeEventStore` contain **zero** `Validator::make` calls. The
missing enum rules are not an oversight within a ruleset; there is no ruleset.

`GoalStore` is 44 lines end to end. `create` checks the tier cap and then:

```php
$attributes = array_merge($canonical, ['user_id' => $user->id]);

return AuditLog::withContext(
    ['ingest_source' => $source->value],
    fn () => DB::transaction(fn () => Goal::create($attributes)),
);
```

`$canonical` reaches `Goal::create` unexamined. `CoordinatingAgent` — Fyn — is one
of its three callers, alongside `GoalsController` and `LifeEventService`.

So a goal created through the web form is validated against seven enum lists by
`StoreGoalRequest`, and the same goal created through Fyn is validated against
none of them.

## What actually happens, and why the severity is medium rather than high

`sql_mode` on this database includes **`STRICT_TRANS_TABLES`**, so a value outside
an enum is rejected by MySQL (error 1265, *Data truncated*) rather than silently
coerced to `''`.

**That matters, and it is worth being precise about**: this is not silent data
corruption. The failure mode is a hard error at the write — which on the web form
is a tidy 422 with a field message, and through Fyn is a 500 from the database.

The consequences are still real, in three ways:

1. **The two surfaces disagree about what a user may say.** A goal priority of
   `urgent` is a validation message on the web and a server error through Fyn.
2. **The error arrives with no field attribution**, so nothing upstream can tell
   the user which value was wrong — the W-0326 shape, where the calling component
   swallowed the failure and the modal closed as though it had worked.
3. **It depends on a database setting to be safe at all.** `STRICT_TRANS_TABLES`
   is what stands between this and a silently truncated column. That is not a
   guarantee the application makes; it is one it currently benefits from.

**This has not been reproduced live.** The reasoning above is from the code and the
schema, and the item should not be written up as user-visible until someone has
driven a bad enum through Fyn and watched what the user sees.

## Why W-0329 did not fix it

Scope. W-0329's remit is lists that disagree with their column or their request;
this is the absence of a list, which is a different question with a different
answer — the fix is to write a ruleset, not to reconcile one.

Folding thirteen new rules and two new `validateCanonical` methods into that item
would also have put a substantial behaviour change behind a guard that was written
to catch drift, not to introduce validation where there was none. **Adding
validation to a path that currently accepts anything can break callers that have
been sending something sloppy and getting away with it** — which needs its own
verification, not a footnote in another item's evidence pack.

## Acceptance

1. Every enum column in the table above either gains a rule in its Store or is
   recorded as deliberately unvalidated there, naming what validates it instead.
2. `GoalStore` and `LifeEventStore` gain a `validateCanonical` in the shape the
   other Stores use, or a recorded reason they do not need one.
3. Before any rule is added, the existing callers are checked for values they
   currently send that a strict rule would newly reject — `CoordinatingAgent`'s
   goal and life-event capture handlers especially, since they are the paths with
   no request layer in front of them.
4. The guard extended so that an enum column with **no** Store rule is itself a
   failure, with an exception list for the recorded cases. Without that, this
   whole class stays invisible to the tests, which is how it survived W-0329.
5. Verified through Fyn on `/m` as well as the web form, per Rule 19.

## Working notes

- 2026-08-26: raised while completing W-0329. The counts above are from the
  schema and the Store sources on branch `Bug-fixes-2`; the live behaviour is
  reasoned, not observed. `dc_pensions.risk_preference` was not probed against its
  request.
