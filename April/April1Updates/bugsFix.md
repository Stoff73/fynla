# Bugs Fixed — 1 April 2026 (fynImprovement branch)

## From Production Testing (fynTest.pdf)

| # | Issue | Fix | Commit |
|---|-------|-----|--------|
| BI-1 | Fyn not picking up rental income | Income breakdown now passes all 7 types with "[relevant UK earnings]" labels | f3fe7f3 |
| BI-2 | Fyn showing internal ID numbers (ID 375) | Explicit instruction: "NEVER show internal record IDs to the user" | f3fe7f3 |
| BI-3 | Property totals not split by ownership, no mortgage awareness | Property records now include ownership %, mortgage balance, rental income | f3fe7f3 |
| BI-4 | Fyn using acronyms (AEA) | Expanded ban list to 17 specific acronyms | f3fe7f3 |
| BI-5 | Can't distinguish employment vs other income for pension advice | Income types labelled as relevant/not relevant UK earnings + financial knowledge block | f3fe7f3 |
| BI-6 | No affordability check on contribution recommendations | Affordability rules require checking surplus, emergency fund, debt, goals | 5ff184e |
| BI-7 | Fyn mentions irrelevant concepts (AA taper, MPAA, carry forward) | Relevance rule: only mention concepts that apply to this user's position | 300a3d1 |
| BI-8 | Fyn doesn't flag Personal Allowance reclaim opportunity at £100k | Added 60% effective relief knowledge for £100k-£125k income range | 300a3d1 |
| BI-9 | Cashflow surplus showing £0 (clamped to zero) | Removed max(0.0, ...) clamp so shortfalls are visible | 8024bc9 |
| BI-10 | Surplus calculated from gross income not net | Now uses DisposableIncomeAccessor (same figure as income tab) | 7de845d |
| BI-11 | Monthly income calculation missing 5 of 7 income sources | Fixed (superseded by BI-10 — no longer manually calculating income) | 7de845d |
| BI-12 | "Other Income" field exists — all income should be categorised | Removed `annual_other_income` field from form, DB, backend, and all references | pending |

## Outstanding Issues (not yet fixed)

| # | Issue | Notes |
|---|-------|-------|
| OI-1 | Fyn could explain things more simply by default | User noted Fyn did simplify when asked — personality instruction could be stronger |
| OI-2 | `blue-50`, `blue-200`, `blue-500` used in HICBC warning on income tab (line 162-166) | Should use design system tokens (`light-blue-*` or `violet-*`) |
| OI-3 | `<button>` nested inside `<button>` warnings in Vite (5 instances in AiChatPanel) | HTML spec violation — needs refactor to use `<div>` with click handlers |
| OI-4 | Pension income field (`annual_pension_income`) exists in form but not in DB | Auto-calculated from DB pensions — works but inconsistent with other income fields |
