---
id: W-0242
title: LifeStageService throws a SQL error on a column that does not exist — a live 500 for a mid-career user over 48 without independent children
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0241]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238**, outside its scope and **not fixed**. Same phantom
column as **W-0241**, different consequence, which is why it is filed separately —
the W-0203 precedent.

### The defect

`app/Services/LifeStage/LifeStageService.php:211`:

```php
$dbTotal = $user->dbPensions()->sum('transfer_value');
```

This is a **query builder** sum, not a collection sum. It emits
`select sum(`transfer_value`) from `db_pensions` …` and MySQL rejects it:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'transfer_value' in 'field list'
```

Reproduced against the live local database on user 17.

The identical mistake one line above it (`dcPensions()->sum('current_fund_value')`)
is fine, because that column exists — which is exactly why the pair reads as
correct.

### The reachable path

`:119`, inside `suggestStageProgression`:

```php
if ($currentStage === 'mid_career' && $age > 48) {
    if ($this->hasIndependentChildren($user) || $this->hasPensionValueAbove($user, …)) {
```

`||` short-circuits, so the throw is reached by a user who is:

- `life_stage = 'mid_career'`, **and**
- older than 48, **and**
- does **not** have all children aged 18 or over (including having no children at
  all — `every()` over an empty set is true, so childless users are safe; a user
  with one child under 18 is not).

Every such user 500s wherever stage progression is evaluated. It is unguarded — no
try/catch on the path.

### Why nobody hit it on this persona

David (16) is 49 and mid-career with children, but the run reached the dashboard,
not stage progression. **A narrow live 500, not a theoretical one.**

## Acceptance

1. The reader is removed or the column exists — resolve with **W-0241**, which owns
   the product decision.
2. A test covers the mid_career-over-48-with-a-minor-child path, which is the only
   one that reaches it.
3. A sweep for other query-builder aggregates over columns that do not exist: a
   collection sum degrades to zero and a query builder sum throws, so the same
   mistake is invisible in one place and fatal in another.
