---
id: W-0501
title: The seventh axis — nineteen enum columns have no accepted-value list in their Store at all, and two Stores validate nothing whatsoever
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: review
severity: low
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: 2026-08-26T00:00:00Z
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

## Correction — 2026-08-26, before implementing

Two claims in the Intent above were wrong, and the item is weaker than it was
filed. Both were found by the caller audit its own acceptance asked for.

**It is nineteen columns, not eighteen.** The original count regexed each Store
whole-file, so `dc_pensions.scheme_type` looked covered by the `scheme_type` rule
in `validateDbCanonical` — a different column on a different table with a disjoint
enum. Scoping the sweep per ruleset found it. A twentieth,
`actuarial_life_tables.gender`, is validated in its normaliser and is recorded as
an exception rather than fixed.

**"Validated against none of them" was wrong about Fyn.** `CoordinatingAgent`'s
two capture handlers validate their own tool input before building a payload:
`handleCreateGoal` bounds `goal_type` and `priority` with `Rule::in`, and
`handleCreateLifeEvent` bounds `event_type` and `certainty`. Neither sends the
other enum columns at all, so the database defaults apply.

**So nothing reachable today can put a bad enum in these columns.** Both Stores
are create-only — updates run `$goal->update()` / `$event->update()` behind
`UpdateGoalRequest` and `UpdateLifeEventRequest` — and all four create paths
validate upstream. The three consequences listed above are therefore **latent, not
live**, and severity drops medium → low.

What remains true, and is why this was still worth doing: **the guarantee rests
entirely on every caller remembering.** The Store is the one layer all four paths
share, and it was the one layer with no rule. That is a Rule 20 shape — a check
implemented in four places instead of one.

## Resolution — 2026-08-26

### Rules added

Every rule is **the column's own enum**, so nothing the table would have stored is
now refused. Mirroring the *request* instead would have been wrong: `life_events`
stores 21 event types and `StoreLifeEventRequest` allows 16, so a request-shaped
rule would have refused five values the column holds — W-0326 in miniature.

| Store | Columns |
|---|---|
| `GoalStore` | `goal_type`, `assigned_module`, `priority`, `status`, `contribution_frequency`, `ownership_type`, `property_type` |
| `LifeEventStore` | `event_type`, `impact_type`, `certainty`, `ownership_type`, `status` |
| `InvestmentAccountStore` | `contribution_frequency`, `platform_fee_type`, `platform_fee_frequency`, `risk_preference` |
| `PensionStore` (DC ruleset) | `scheme_type`, `risk_preference` |
| `SavingsStore` | `access_type` |

`GoalStore` and `LifeEventStore` gained a `validateCanonical` each, on the footing
`PensionStore::validateDcCanonical` already states — a canonical-shape sanity
check, **not** a stricter gate. Enum columns only. Types, bounds and requiredness
stay with the requests and with Fyn's tool rules, because widening these into a
mirror of the request would make the Store stricter than the callers were written
against, which is the risk acceptance criterion 3 was about.

**`LifeEventStore::event_type` composes from `LifeEvent::INCOME_EVENT_TYPES` and
`::EXPENSE_EVENT_TYPES` rather than retyping the list** (Rule 20). That vocabulary
is already retyped in `StoreLifeEventRequest:26` and in
`CoordinatingAgent::handleCreateLifeEvent`; a fourth copy is a fourth place to
miss. Those two existing copies are **reported, not fixed** — consolidating them
is a change to the request and tool layers, not to this item's Store layer.

### The five dead event types

`divorce`, `marriage`, `new_child`, `job_loss`, `income_change` are in the
`life_events.event_type` enum and are created by nothing: absent from the model
constants, the request and the Fyn tool. They survive in
`LifeEvent::eventTypeLabel()` (:161-165), which is what makes them look live.

Recorded in `DELIBERATELY_NARROWER` rather than admitted, because **accepting them
would be worse than refusing them**: `LifeEventService::createEvent` derives
`impact_type` by asking whether the type is in `INCOME_EVENT_TYPES`, so all five
would be filed as an **expense** — a divorce and a pay rise both booked as money
going out. Whether they should exist in the enum at all is a separate question and
is not answered here.

### Guard

`StoreEnumRulesMatchColumnsTest` goes from two checks to four:

3. **an enum column with no list at all is a failure** — the hole this item is
   about, with `UNVALIDATED_ENUM_COLUMNS` for recorded cases (currently only
   `ActuarialLifeTableStore::gender` → `ActuarialLifeTableNormaliser:39-40`).
4. **an `in:` rule the guard cannot read is a failure.**

**Check 4 exists because this item's own fix produced a false pass.** Written as
`'in:'.implode(',', $types)` behind a local, the composed rule matched the
literal-only regex as an `in:` rule with an empty list, so the guard **skipped it
and went green** while it refused five values the column stores. The guard now
resolves a composed list by reading the constant references out of the line, and
reports anything it still cannot read rather than passing over it. A
silently-skipped rule is indistinguishable from a checked one, which is the
`tests/CLAUDE.md` "test that cannot fail" family.

### Evidence

| Check | Result |
|---|---|
| Per-ruleset sweep re-run | 0 columns without a list |
| `StoreEnumRulesMatchColumnsTest` | 4 passed |
| `GoalAndLifeEventStoreValidationTest` (new) | 9 passed |

All four new/changed guard checks were mutation-tested rather than trusted for
going green:

- Dropping the `LifeEventStore::event_type` exception → *"refuses [divorce,
  marriage, new_child, job_loss, income_change], which life_events.event_type
  stores"*. This is the one that proves the **composed** list is genuinely
  resolved and compared, not merely non-null.
- Hiding the composed list behind a local again → *"LifeEventStore::event_type (in
  validateCanonical)"* from check 4, i.e. the original false pass reproduced and
  now caught.
- Deleting `GoalStore`'s `priority` rule → *"GoalStore::priority (goals.priority)"*
  from check 3.

**The four regression cases matter more than the rejection cases**, because the
risk in this change is what new validation might newly refuse. Each real caller is
driven end to end: both Fyn capture handlers through `CoordinatingAgent`, and both
form endpoints (`POST /api/goals`, `POST /api/life-events`). The goal endpoint
case asserts `assigned_module` specifically — it is set by `GoalAssignmentService`
rather than by the request, so it was the field most exposed to a new rule.

## Still open

1. **`/m` verification (acceptance 5) not done.** Rule 19 wants this seen on `/m`,
   not only in tests. The Fyn capture handlers are surface-agnostic and the
   Feature tests drive them directly, but that is not the same as watching it on
   the device.
2. **The `event_type` vocabulary still has three homes** — the model constants,
   `StoreLifeEventRequest:26`, and `CoordinatingAgent::handleCreateLifeEvent`. The
   Store now composes from the first; the other two still retype it. Worth its own
   item under Rule 20.
3. **Whether the five dead enum values should be dropped from the column** by
   migration, or wired up properly. Refusing them at the Store is the safe holding
   position, not an answer.
