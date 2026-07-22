# Mobile Dashboard Build — Plan & Location Confirmation

## Location confirmation (the goal asked to verify this)

**`mobile-host.blade.php` is NOT where the dashboard lives.** It is only the outer
iframe shell (`<iframe src="/m/landing">`). The dashboard that is *served inside
the iframe* is the isolated Vue SPA:

```
GET /m            → resources/views/mobile-host.blade.php   (iframe shell)
                     iframe src = /m/landing  (static placeholder) → "Get started" → /m/app
GET /m/app/{any?} → resources/views/mobile-app.blade.php     (boots Vue SPA from public/m-build)
                     inner Vue router → /dashboard → resources/mobile/views/Dashboard.vue  ← THE DASHBOARD
```

So the correct build target is **`resources/mobile/views/Dashboard.vue`**, compiled
via `npm run build:mobile` into `public/m-build/`, served by `mobile-app.blade.php`
inside the `/m` iframe. We also point the iframe host's "Get started" flow at the
app. `mobile-host.blade.php` only needs a one-line change if we want the iframe to
deep-link to the app; the dashboard markup/logic belongs in `Dashboard.vue`.

## Data wiring (real data, not mock)

| Mockup element            | Source                                                            |
|---------------------------|------------------------------------------------------------------|
| 4 finance cards           | `/api/v1/mobile/dashboard` → `modules` + `net_worth`             |
| Card click-through        | mobile route `/m/app/module/:slug` (the in-app nav equivalent)   |
| Recommendations accordion | NEW `/api/v1/mobile/recommendations` → RecommendationsAggregator |
| 57% stat (dynamic)        | NEW `MobileLevelService::percentile()` — level distribution      |
| Level + progress wheel    | `MobileLevelService` — 3/5/7…cap-10 thresholds                   |
| Fyn chat                  | EXISTING `/api/conversations` (create + /messages). FE only.     |
| Fyn suggestions           | Derived from the user's recommendation list                      |

## Level rules
L1→2 = 3 actions, L2→3 = 5, L3→4 = 7 … `actionsForLevel(n) = min(10, 3 + (n-1)*2)`.
Completed actions counted across all recommendations (any card). Percentile =
share of users whose level ≤ this user's level (cached).

## Build + test workflow
`docs/mobile/mockup-to-app-workflow.md` + `scripts/build-mobile.sh` +
`tests/Feature/Mobile/MobileDashboardDataTest.php` (backend) + Playwright UI checks.
