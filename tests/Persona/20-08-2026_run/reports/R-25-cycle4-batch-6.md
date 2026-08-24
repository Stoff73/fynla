# R-25 — Cycle 4, batch 6

**Agent:** `peak-earners-c4` (persona-tester) · **Persona:** `peak_earners`
**Surface:** web + `/m`, local · **Account:** David (16)
**Batch closed:** 2026-08-22 ~19:16 · Continues R-19 … [R-24](R-24-cycle4-D04-reverified-green.md)

Pensions and the D-05 cache question I said in R-24 should be tested rather than inferred.
**This batch answers it.**

---

## Done

- Ran the **D-05 cache-invalidation test** properly: recorded the cache state, made a real
  data change through the UI, and re-read every layer.
- Entered the persona's **Global Finance Corp Pension platform fee (0.35%)** — a genuine
  gap closed.
- Tested the **per-pension risk control** against a control case on a different form.
- Checked `/m` parity for the dashboard (see R-24) and attempted deeper `/m` routes.

### GREEN
- The workplace pension edit form **does** carry Platform Fee and Advisor Fee — they sit
  behind a **"Show additional information"** disclosure. I had a defect half-drafted saying
  they did not exist; checking the collapsed section first killed it. Not raised.
- `platform_fee_percent` saved correctly: `0.3500`, `updated_at` 20:14:37.

---

## D-23 (MEDIUM) — Cache invalidation is one layer deep, so a "fresh" dashboard rebuilds from stale analyses

This is the concrete answer to R-19 D-05, which I flagged in R-24 as untested.

**Method.** Recorded `mobile_dashboard_16` (cached 20:08:23). Edited the Global Finance
Corp Pension through the UI and saved. Re-read every cache key without clearing anything.

**Result:**

| Key | After a pension update |
|---|---|
| `mobile_dashboard_16` | **GONE — invalidated** ✓ |
| `retirement_analysis_16` | **EXISTS** ✗ |
| `investment_analysis_16` | **EXISTS** ✗ |
| `savings_analysis_16` | **EXISTS** ✗ |

So the outer dashboard blob *is* invalidated on a data change — the comment at
`MobileDashboardAggregator.php:37` is half true. But `aggregateModules()` rebuilds that
blob by calling `$agent->analyze($userId)` on each agent, and **every agent cache
survived**. The dashboard is therefore reassembled, with a fresh `cached_at`, out of
analyses that are up to 24 hours old (`BaseAgent.php:21` → `CACHE_TTL_STANDARD = 86400`).

A fresh timestamp on a stale figure is worse than an obviously stale one, because nothing
on the surface indicates it. This is the mechanism behind R-19 D-05's symptom — Sarah's
Protection card sitting at £0 for hours after W-0186's fix had merged.

**Not the same as D-05 as written**, so it should be its own item: D-05 said invalidation
does not happen; the accurate finding is that it happens at one layer and not the layer
that holds the numbers.

## D-22 (HIGH) — The pension's risk level control is silently discarded, and it is stripped by the validator

Selected **Upper-Medium** on "Risk Level for This Pension" (the persona sets both of
David's pensions to Upper Medium), saved, and the value did not persist. Repeated it,
verifying the button's own class changed on click, so the selection registered in the UI.

**Verified with a control**, which rules out "the click never landed":

| | Field set in the same submit | Result |
|---|---|---|
| `dc_pensions.id=9` | `platform_fee_percent` → 0.35 | **saved** ✓ |
| `dc_pensions.id=9` | `risk_preference` → upper_medium | **still `medium`** ✗ |
| `investment_accounts.id=26` (different form, same control style) | `risk_preference` → high | **saved** ✓ |

`updated_at` moved to 20:14:37, so the request succeeded and the row was written — this one
field was dropped from it.

**Root cause, traced end to end:**

- `resources/js/components/Retirement/DCPensionForm.vue:323` binds
  `v-model="formData.risk_preference"`, so the client holds and sends it.
- `RetirementController::updateDCPension()` (`:444-447`) takes a `StoreDCPensionRequest`
  and calls `$request->validated()`.
- **`app/Http/Requests/Retirement/StoreDCPensionRequest.php` has no `risk_preference`
  rule.** It validates `platform_fee_percent` at `:64` — which is exactly why that field
  survived the same submit. `validated()` returns only validated keys, so
  `risk_preference` is stripped before it reaches the model.
- `DCPension::$fillable` **includes** `risk_preference` (`app/Models/DCPension.php:61`),
  and `RetirementController:865-866` reads
  `if ($pension->has_custom_risk && $pension->risk_preference)` downstream.

So the column is fillable, the form offers it, the client sends it, the code consumes it —
and it can never be set. Silent data loss with a success response.

Same family as W-0009 (a payload discarded on edit), different mechanism: a validation
allowlist omission rather than a destructuring bug. **Worth the same sweep W-0009 should
have prompted — compare each form request's rules against its model's `$fillable`.**

Screenshot: `144-web-david-pension-risk-discarded-still-medium.png`

## D-17 strengthened — the API already accepts pension holdings; only the UI is missing

R-22 D-17 reported that a Defined Contribution pension's holdings cannot be entered because
the detail view has no Holdings tab. The gap is narrower than that and therefore cheaper to
fix: `RetirementController::updateDCPension():449-450` already does

```php
$holdings = $data['holdings'] ?? null;
unset($data['holdings']);
```

so the **update endpoint accepts holdings today**. Combined with `holdings.holdable_type`
supporting `App\Models\DCPension` and a seeded pension carrying three, the capability
exists in the schema *and* the API. **Only the client has no control.**

## Also observed — the mobile payload's retirement block is zeroed where the web page has values

`/api/v1/mobile/dashboard` for David returns:

```
modules.retirement: { years_to_retirement: 0, pot_value: 500000, guaranteed_income: 11502.4,
                      projected_income: 0, target_income: 0, income_gap: 0, total_pensions: 3 }
```

`years_to_retirement: 0` for a 49-year-old retiring at 60 — the same page's own retirement
view says "Years to Go 11". And `target_income`, `projected_income` and `income_gap` are all
zero while `/net-worth/retirement` shows Target £110,767 and Projected Gross £39,853.

`pot_value`, `guaranteed_income` (the £11,502 state pension I entered) and `total_pensions`
are all correct, so the block is half-populated. **Not raised as its own item** — it is very
likely the same stale-agent-analysis effect as D-23, and should be re-checked once D-23 is
fixed rather than chased separately now.

---

## Not done, and why

- **Deeper `/m` parity (D-19, D-20, the wills, the mortgage share) — I COULD NOT TEST
  THIS.** A cold navigation to a deep `/m` route (`/m/app/investment`) bounces to the
  desktop `/login` and drops the `/m` session; that matches the known behaviour in the
  `reference_m_verification_path` note ("the bridge doesn't fire on cold nav"). With a
  desktop session live in the same browser the two token stores fought each other and I
  could not hold a stable `/m` session long enough to read a module screen. **The dashboard
  parity in R-24 is solid; the rest of `/m` needs a clean browser context or csjones.**
- The joint account's "Cash" placeholder (R-21 D-11) — still untouched, still awaiting the
  coordinator's decision, asked three times.
- `employer_matching_limit` (persona 8%) has no field on the pension form. The column exists
  and stays NULL. **Not raised** — the employer contribution of 8% is captured, and the two
  are the same number for this persona, so nothing user-facing is wrong.

## Assumptions

- I treated "the row's `updated_at` moved but this field did not" as proof the request
  succeeded and the field was dropped, rather than that the save failed.
- I set the pension platform fee to 0.35% on the persona's authority; no invented values.

## Needs

- Board IDs for **D-22** and **D-23**.
- D-23 should go to whoever holds D-05 — they are the same story and the fix is in the same
  file. **D-05's wording needs correcting on the board**: invalidation does fire, one layer
  too shallow.
- D-22 pairs naturally with W-0009 and deserves the same allowlist sweep.

## Noticed

- I nearly raised "the pension form has no platform fee field" before opening **"Show
  additional information"**. That is the third false positive this run caused by a control
  that exists but is not visible in the first render (the others were a modal opening before
  its handler was live, twice). The pattern is worth a line in the playbook: **on this app,
  confirm a control is genuinely absent before reporting it missing.**
