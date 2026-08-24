---
id: W-0242
title: LifeStageService throws a SQL error on a column that does not exist — a live 500 for a mid-career user over 48 without independent children
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0023-cycle4-validation-and-silent-data-loss.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:35:00Z
claimed: 2026-08-22T20:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
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

## Working notes

- 2026-08-22 build-lead: **FIXED. The reader is removed, and the sweep is clean.**

  ### The fix, and what it deliberately does NOT decide

  `LifeStageService::hasPensionValueAbove()` no longer sums
  `db_pensions.transfer_value`. Acceptance §1 offered "removed or the column
  exists", and **CSJ has since ruled: removed, permanently.**

  **W-0241 ruling, 2026-08-22, option 3 — Defined Benefit schemes are EXCLUDED from
  net worth by decision**, with the surfaces stating so where the figure is shown.
  A `transfer_value` column, migration or form field is explicitly out of scope, as
  is any capitalisation multiple on `accrued_annual_pension`. The ruling names this
  reader as in scope for deletion, so this item **implements** the ruling rather
  than deferring to it.

  Zero is what every other reader in the app already contributes for a Defined
  Benefit pension — including the Collection twin at
  `MobileDashboardAggregator:427`, which silently sums to zero rather than throwing.
  So dropping the term makes this path **agree with the rest of the app instead of
  500ing**. The comment at the site now records the exclusion as settled, so the
  next reader does not mistake it for an oversight.

  **Comment corrected 2026-08-22.** Its first version told the next reader to add
  the term back "when W-0241 lands", written while that item was still open. With
  the ruling made, that pointed at the one change the ruling forbids. It now says
  do NOT restore. Caught by the pensions agent, which correctly declined to edit a
  file it does not own.

  **`MobileDashboardAggregator:427` is untouched** — another agent's in-flight file,
  and the ruling assigns its deletion to W-0241, not here.

  ### Tests — 4 cases, `tests/Feature/LifeStageTransitionTest.php`

  The fixture variant of `tests/CLAUDE.md` §4 governs this one hard. The throwing
  line sits behind a short-circuiting `||`, so **a fixture with no children never
  reaches it** — `every()` over an empty set is true — and a test built on one
  would pass without touching the bug. Every case states a child's age explicitly,
  because the child's age is the fixture property that decides whether the code
  under test runs at all.

  Four cases, not one: the reachable path does not throw; a pot over the £200,000
  threshold on that same path returns `'peak'`; a pot under it returns null; and the
  short-circuit path (all children 18+) still works. **The positive and negative
  pair is what stops "it did not throw" passing because the check was deleted.**

  ### The sweep — acceptance §3, and it is clean

  354 aggregate call sites inspected across `app/`, each classified by receiver
  (`->rel()->sum()` hits MySQL and throws; `->rel->sum()` runs over a Collection and
  silently returns zero) and checked against `information_schema`.

  **After this fix there are ZERO query-builder aggregates over a column that does
  not exist.** The only remaining aggregate over a name that is a column nowhere is
  `MobileDashboardAggregator:427` — the Collection twin, deliberately left. Every
  other hit in the list is a sum over an array key built in code
  (`weighted_amount`, `potential_gain`, `signed_amount`, `monthly_amount`), which is
  legitimate and not this bug.

  ### Browser-verified GREEN

  `GET /api/life-stage/progress` as David (16) — `mid_career`, 49, with a
  16-year-old child, which is the only shape that reaches the throwing line:
  **200**, `suggested_transition: "peak"`, no SQL in the body.

  The green is not a coincidence: the removed line still throws against the live
  database when run directly —
  `SQLSTATE[42S22]: Unknown column 'transfer_value' in 'field list'` — so the path
  was genuinely reached and genuinely fatal before this change.

  David's `life_stage` was set to `mid_career` through the app's own API
  (`POST /api/life-stage/set`, a real user action, not a DB edit) and **restored to
  `NULL` as found** afterwards.
