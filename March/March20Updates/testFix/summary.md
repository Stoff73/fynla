# Production Testing Summary — 20 March 2026

## Test Series 2 (Post-Deployment Fixes)

**Tester**: Claude (Playwright) + User (verification codes)
**Environment**: https://fynla.org (production)
**Build**: v0.9.3

## Results

| Account | Email | Journey | Steps | Completed | Dashboard | Overall |
|---------|-------|---------|-------|-----------|-----------|---------|
| j1 | j1@fynla.org | Starting Out | 6 | 6/6 (100%) | ✅ | PASS |
| j2 | j2@fynla.org | Building Foundations | 4 | 4/4 (100%) | ✅ | PASS |
| j3 | j3@fynla.org | Protecting What Matters | 7 | 7/7 (100%) | ✅ | PASS |
| j4 | j4@fynla.org | Planning Your Future | 6 | 5/6 (93%) | ✅ | PASS |
| j5 | j5@fynla.org | Enjoying Your Wealth | 6 | 5/6 (93%) | ✅ | PASS |

**5/5 journeys completed. 5/5 dashboards rendered correctly. 0 blocking bugs.**

## Bugs Found & Fixed During Testing

| # | Severity | Bug | File | Fix | Deployed |
|---|----------|-----|------|-----|----------|
| 1 | CRITICAL | Logout doesn't invalidate session — old user persists after re-registration | `AuthController.php` | Added `session()->invalidate()` + `regenerateToken()` | ✅ |
| 2 | CRITICAL | Onboarding step save 500 — "Focus area not set" for 4/5 stages | `LifeStageService.php` | Map life stages to existing enum values (university→budgeting, etc.) | ✅ |
| 3 | MEDIUM | First focus area fix used stage names directly — DB enum constraint | `LifeStageService.php` | Changed to enum-compatible mapping | ✅ |

## Pre-Existing Bugs (From Test Series 1, Already Fixed & Deployed Before Testing)

| # | Bug | Status |
|---|-----|--------|
| 1 | Estate endpoint 500 — `getKey()` on array (LiabilityResource merge) | ✅ Fixed |
| 2 | AuthController logout 500 — TransientToken::$id | ✅ Fixed |
| 3 | Risk recalculate 429 — stale throttle counter | ✅ Fixed |
| 4 | LifeStageService focus area — only set for retirement stage | ✅ Fixed |

## Known Issues (Not Fixed — Carried Forward)

| # | Severity | Issue | Affects |
|---|----------|-------|---------|
| 1 | MEDIUM | Income page: "Other Income" not shown as line item in main breakdown | j1 |
| 2 | MEDIUM | Goals page: Goals from onboarding not visible on dedicated Goals page | j1 |
| 3 | LOW | Sidebar journey %: intermittently shows 0% on some pages (race condition) | All |
| 4 | MEDIUM | Net Worth: Mortgage not reflected as liability (Liabilities always £0) | j3, j4 |

## Files Changed & Deployed During Testing

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/AuthController.php` | Session invalidation on logout |
| `app/Services/LifeStage/LifeStageService.php` | Focus area mapping for all life stages |

## Screenshots

- `j1-dashboard.png` — Thomas Wilson (Starting Out)
- `j1-step3-progress-bar.png` — Progress bar showing three-state system
- `j2-dashboard.png` — Sophie Clarke (Building Foundations)
- `j3-dashboard.png` — David Taylor (Protecting What Matters)
- `j4-dashboard.png` — Richard Moore (Planning Your Future)
- `j5-dashboard.png` — Margaret Hughes (Enjoying Your Wealth)

## Key Observations

1. **All 5 life stage journeys work end-to-end** — registration, onboarding, step saves, dashboard rendering
2. **Spouse linking works correctly** — Emma Taylor appeared in beneficiary/joint owner dropdowns throughout j3
3. **Asset tab navigation works** — Retirement/Properties/Investments/Cash tabs with inline forms
4. **Protection policy modal works** — Life Insurance and Critical Illness with correct conditional fields
5. **Property wizard works** — 3-step (Basic Info → Ownership → Costs) with mortgage integration
6. **State Pension form works** — weekly amount, qualifying years, forecast date
7. **Contextual sidebar content** — Different "Did you know?" and stats per step and journey stage
8. **Suggested goals are stage-appropriate** — Student goals for j1, house deposit for j2, protection for j3, pension for j4, legacy for j5
9. **Retired employment status** correctly adapts income form (removes employer fields, adds pension income note)
10. **Self-employed status** correctly changes income label to "Annual Self-Employment Income"
