---
name: session-start
description: Bootstrap a new development session. Seeds the database, checks git status, shows recent activity, and displays context summary. Run this at the start of every session to ensure the environment is ready. Use when the user says "start session", "get ready", "set up", "begin", or at the start of any new conversation.
disable-model-invocation: true
---

# Session Start - Pre-Session Bootstrap

Prepare the development environment for a new Fynla session. This is the FIRST thing that runs in every session.

## Step 1: Database Seed (CRITICAL - NEVER SKIP)

Run the full database seed. This is the #1 rule of the project — zero tolerance for skipping.

```bash
php artisan db:seed
```

If seeding fails, diagnose immediately. Common fixes:

| Error | Fix |
|-------|-----|
| Table doesn't exist | `php artisan migrate && php artisan db:seed` |
| Duplicate key | Safe to ignore — seeders use `updateOrCreate()` |
| Connection refused | Check MySQL is running: `mysql.server start` or `brew services start mysql` |

### Complete Seeder Inventory (17 seeders)

**Phase 1 — Required (13 seeders, run by `db:seed`):**

| Seeder | Purpose |
|--------|---------|
| `TaxConfigurationSeeder` | UK tax rates, allowances, thresholds (5 tax years) |
| `TaxProductReferenceSeeder` | ISA/GIA/Bond tax treatment info |
| `ActuarialLifeTablesSeeder` | ONS life expectancy data for estate/retirement projections |
| `RolesPermissionsSeeder` | Auth roles and permissions |
| `AdminUserSeeder` | Admin accounts (demo@fps.com, admin@fps.com) |
| `PreviewUserSeeder` | 6 preview personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) |
| `SavingsMarketRatesSeeder` | Savings benchmark rates |
| `PlanConfigurationSeeder` | Admin-configurable plan rates, benchmarks, defaults |
| `RetirementActionDefinitionSeeder` | Retirement plan action triggers |
| `InvestmentActionDefinitionSeeder` | Investment plan action triggers |
| `ProtectionActionDefinitionSeeder` | Protection plan action triggers |
| `TaxActionDefinitionSeeder` | Tax optimisation action triggers |
| `SubscriptionPlanSeeder` | Subscription pricing and trial config |

**Phase 2 — Dev/Staging only (2 seeders, run by `db:seed` in local/dev/staging):**

| Seeder | Purpose |
|--------|---------|
| `HouseholdSeeder` | Households for multi-user testing |
| `TestUsersSeeder` | Additional test user accounts |

**Standalone — not in DatabaseSeeder (run manually when needed):**

| Seeder | Purpose | Command |
|--------|---------|---------|
| `OccupationCodeSeeder` | ONS SOC 2020 occupation codes | `php artisan db:seed --class=OccupationCodeSeeder --force` |

### Quick Fix Reference

| Issue | Seeder |
|-------|--------|
| Tax calculations failing | `TaxConfigurationSeeder` |
| Tax Status tab empty | `TaxProductReferenceSeeder` |
| Preview personas broken / 403 | `PreviewUserSeeder` |
| Life expectancy errors | `ActuarialLifeTablesSeeder` |
| Savings market rates missing | `SavingsMarketRatesSeeder` |
| Plan actions not showing | All 4 action definition seeders |
| Subscription plans missing | `SubscriptionPlanSeeder` |
| Plan benchmarks wrong | `PlanConfigurationSeeder` |
| Occupation dropdown empty | `OccupationCodeSeeder` |
| Roles/permissions errors | `RolesPermissionsSeeder` |

## Step 2: Git Status & Branch Context

```bash
git status
git branch --show-current
git log --oneline -10
```

Report to the user:
- Current branch name
- Whether there are uncommitted changes
- Last 10 commits (so we know where we left off)

If on a feature branch, also show divergence from main:
```bash
git log --oneline main..HEAD
```

## Step 3: Recent Activity Summary

Check what was worked on recently:

```bash
# Files changed today
git log --since="midnight" --name-only --pretty=format:"" | sort -u

# Files changed in last 3 days (in case session spans days)
git log --since="3 days ago" --oneline
```

## Step 4: Check Dev Server

Check if the dev server is already running:

```bash
# Check if Laravel/Vite are running
lsof -i :8000 2>/dev/null | head -3
lsof -i :5173 2>/dev/null | head -3
```

If not running, tell the user:
> Dev server is not running. Start it with `./dev.sh` when ready.

Do NOT auto-start the dev server — the user may want to do backend-only work.

## Step 5: Session Context Display

Present a clean summary to the user:

```markdown
## Session Ready

**Branch:** `branch-name`
**Status:** Clean / X uncommitted changes
**Last work:** [summary of recent commits]

**Database:** Seeded successfully (17 seeders)
**Dev server:** Running on :8000/:5173 / Not running

**Recent changes:**
- [list of recently changed files/features]
```

## Important

- ALWAYS seed first. No exceptions. No "I'll do it later". Seed FIRST.
- Do NOT run `migrate:fresh` or `migrate:refresh` — these destroy data.
- Do NOT auto-start dev servers — let the user decide.
- Do NOT make any code changes — this is a read-only bootstrap.
- If the user has a specific task in mind, after displaying the summary, proceed to their request.
