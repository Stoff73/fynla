---
id: BUG-02
raised: 2026-08-17
surface: all — the £0 projection is a shared-endpoint 500; capture defects are backend
severity: blocker
status: root causes confirmed and reproduced — no fix applied
fixed_in: null
evidence: August/bugs/ios-17August/img1.png (Fyn chat), img2.png (Retirement screen)
---

# BUG-02 — pension captured wrongly, twice, and the projection reads £0

Three **separate** defects, one of which causes another. All reproduced locally
through the real HTTP path.

## Summary

| # | Defect | Root cause | Blast radius |
|---|---|---|---|
| 1 | `/api/retirement/projections` returns **HTTP 500** with 2+ funded pensions → native renders £0 | `AssumptionsService.php:303` lazy-loads `$pension->holdings` | **All surfaces**, local + staging only |
| 2 | Pension created **twice** | `checkForDuplicate` is an exact name match | Fyn capture, all modules using it |
| 3 | "Sip" recorded as **workplace** | `scheme_type` has no `enum`; normaliser silently defaults to `occupational` | `create_pension` only |

**The backend maths is correct.** Defect 1 is not a calculation error — it is an
unhandled exception. Given £45,000 and nothing else, the projection service
returns £111,646.54 at age 67 and **£5,247.39 a year**. That is right.

## Defect 1 — the £0 projection is a 500, not a zero

`GET /api/retirement/projections`, probed over HTTP with a real token, cache
flushed between each:

| Pensions | HTTP |
|---|---|
| 1 funded (£45,000) | **200** — `planning_total_at_target_age: 5247.39` |
| 2 funded (£45,000 + £20,000) | **500** |
| back to 1 funded | **200** (deterministic, not caching or ordering) |
| 1 pension, £0 | **200** |
| 2 pensions, both £0 | **200** |
| funded + £0 | **500** |
| £0 + funded (order swapped) | **500** |

Response body on failure:

```json
{"success":false,"message":"Attempted to lazy load [holdings] on model [App\\Models\\DCPension] but lazy loading is disabled."}
```

**Trigger:** two or more DC pensions where at least one has `current_fund_value > 0`.
Both-zero passes because of the `if ($value <= 0) { continue; }` guard at
`AssumptionsService.php:294`.

**Root cause** — first non-vendor frames:

```
app/Services/Settings/AssumptionsService.php:303   Model->__get
app/Services/Settings/AssumptionsService.php:95    calculateWeightedFees
app/Services/Retirement/RetirementProjectionContractService.php:35  getTypeAssumptions
```

`AssumptionsService.php:303`:

```php
$holdingsOcf = $this->calculateHoldingsWeightedOcf($pension->holdings, $value);
```

`$pension->holdings` is not eager-loaded. `User::dcPensions()` is a plain
`hasMany` with no `with()`, and `DCPension` declares no `$with`.

**Why it only bites on dev/staging** — `AppServiceProvider.php:208`:

```php
Model::preventLazyLoading(! app()->isProduction());
```

- **local and csjones staging** → guard ON → **throws → 500 → native shows £0**
- **fynla.org production** → guard OFF → lazy load silently succeeds, degrading
  to an N+1 query per pension

The TestFlight app reads **csjones**, which is why CSJ sees it. This also means
production has a latent N+1 on the same path, and that a fix must not be judged
by "it works on prod".

**Not verified:** why a *single* funded pension escapes. The loop at
`AssumptionsService.php:292` has no early return and no count branch, so on
reading it should throw for one pension too. It reproducibly does not. Stated as
an open question rather than guessed at; the eager-load fix resolves it for every
count either way.

**Blast radius:** `/api/retirement/projections` is a shared endpoint — web, `/m`
and native all read it. Any user on staging with two funded pensions loses their
retirement projection entirely. This has nothing to do with Fyn; Fyn merely
created the second pension that exposed it.

## Defect 2 — the duplicate pension

`CoordinatingAgent::checkForDuplicate`:

```php
$existing = $modelClass::where('user_id', $userId)
    ->whereRaw('LOWER('.$nameField.') = ?', [strtolower($nameValue)])
    ->first();
```

A case-insensitive **exact** string match. `handleCreatePension` calls it on
`scheme_name` against both `DCPension` and `DBPension`.

From img2 the two records are **"Aviva Personal Pension"** (£0) and **"Aviva
Pension"** (£45,000). Different strings, so no duplicate was detected and both
were written. The warning text it would have returned — *"A similar record … already
exists"* — overstates what it does: it catches identical names only, never similar
ones.

Consequence beyond the duplicate: the free tier caps pensions at 2, so one
mis-captured record consumed the user's entire allowance — img2 shows
**"2 of 2 pensions used UPGRADE"**.

**Not verified:** that both records came from this one conversation. The mechanism
is confirmed; the two-writes sequence is inferred from img1 plus the £0 record.
Proving it needs the `ai_messages` tool-call rows for that conversation on
csjones.

## Defect 3 — "Sip" became a workplace pension

img1: Fyn asks for the scheme type, the user answers **"Sip"**, Fyn replies
**"Recorded — Aviva workplace pension £45,000."**

Live schema as the model receives it (`XaiToolDefinitions`, provider `xai`):

```json
"scheme_type": {
  "type": ["string", "null"],
  "description": "DC: \"workplace\" (employer pension), \"sipp\" (Self-Invested Personal Pension), \"personal_pension\", \"stakeholder\". DB: \"final_salary\", \"career_average\"."
}
```

**No `enum`.** The permitted values exist only in prose. Compare
`pension_category` directly beneath it, which *does* carry
`"enum": ["dc","db"]` — so the pattern is established and this field simply
does not use it.

`PensionNormaliser::fromFynPension`:

```php
$pensionType = match ($toolParams['scheme_type'] ?? 'workplace') {
    'workplace', 'occupational'    => 'occupational',
    'sipp', 'self_invested'        => 'sipp',
    'personal', 'personal_pension' => 'personal',
    'stakeholder'                  => 'stakeholder',
    default                        => 'occupational',
};
```

The match is **case-sensitive**, and both the missing-value default *and* the
unknown-value default are `occupational`. So `"SIPP"`, `"Sipp"`, `"sip"` or any
free-text variant silently becomes a **workplace** pension. No error, no warning,
nothing logged.

`handleCreatePension` does not validate `scheme_type` at all — its rules cover
`pension_category`, `scheme_name`, values and ages only. Three layers, none
catches it.

Also visible in img1: Fyn stated it needed **both** the scheme type *and*
Defined Contribution/Benefit before recording, then recorded on `"Sip"` alone.
It inferred DC correctly, but it recorded against its own stated precondition.

## Is this systemic, as CSJ suspects?

Checked, because it is the right question. **For this class of defect, no.**

Scanned all live tool schemas for fields that advertise a closed value set in
their description but carry no `enum`:

```
tools: 53
fields WITH enum: 49
fields advertising a closed set in prose but NO enum: 1
  create_pension  scheme_type  workplace|sipp|personal_pension|stakeholder|final_salary|career_average
```

**Exactly one** — this bug. And across `app/Services/Stores/Normalisers/`, only
`PensionNormaliser` silently coerces an unknown value; `SavingsAccountNormaliser:114`
uses `default => $accountType`, passing the raw value through so it fails
validation downstream instead of being quietly rewritten. Pension is the outlier.

**A related systemic issue that is real, though:** `create_pension` is
`"strict": true` with **every** property in `required`, so the model must emit all
16 fields whether or not it knows them, and zero-fills what it does not. The
`handleCreatePension` WP-1 comment acknowledges exactly this and strips impossible
zeros — but only for three age fields. `annual_salary`,
`employee_contribution_percent`, `employer_contribution_percent`,
`monthly_contribution_amount` and the fee fields have no equivalent protection, so
a zero-fill is stored as a real 0. Worth auditing across every strict-mode
creation tool.

## Fix order

Not started.

1. **Eager-load `holdings`** in the projection path — `AssumptionsService::calculateWeightedFees`
   should not touch a lazy relation. Highest priority: it is a 500 on a shared
   endpoint for any staging user with two funded pensions. Add a regression test at
   exactly two funded pensions, since one does not reproduce it.
2. **Add an `enum`** to `create_pension.scheme_type`, and make the normaliser
   case-insensitive with an explicit failure — not a silent `default =>
   'occupational'` — for unrecognised values. Re-record the golden master after
   the schema change (see the `reference_tool_schema_description_governs_llm_defaults`
   memory).
3. **Duplicate detection** — decide whether it should be fuzzy (provider + value +
   type) rather than exact-name, and fix the warning wording either way.
4. Audit zero-fill protection across strict-mode creation tools (the WP-1 pattern
   generalised).
5. Consider whether `preventLazyLoading` being production-off is wise: it means
   staging catches these and production hides them as N+1s. That is arguably the
   right way round, but it guarantees prod carries silent performance debt.
