---
name: windows-session-start
description: Bootstrap a new development session on Windows. Seeds the database, checks git status, shows recent activity, frees occupied ports, starts the dev server, and displays context summary. Run this at the start of every session on a Windows machine. Use when the user says "start session", "get ready", "set up", "begin", or at the start of any new conversation on Windows.
disable-model-invocation: true
---

# Windows Session Start - Pre-Session Bootstrap

Prepare the development environment for a new Fynla session on Windows. This is the FIRST thing that runs in every session.

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
| Connection refused | Check MySQL is running: `net start MySQL` or start it from Services |

### Complete Seeder Inventory (18 seeders)

**Phase 1 — Required (14 seeders, run by `db:seed`):**

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
| `OccupationCodeSeeder` | ONS SOC 2020 occupation codes |

**Phase 2 — Dev/Staging only (2 seeders, run by `db:seed` in local/dev/staging):**

| Seeder | Purpose |
|--------|---------|
| `HouseholdSeeder` | Households for multi-user testing |
| `TestUsersSeeder` | Additional test user accounts |


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

## Step 2: Git Sync & Branch Context

### 2a: Check current state

```powershell
git status
git branch --show-current
git log --oneline -10
```

### 2b: Fetch latest from remote

```powershell
git fetch origin
```

### 2c: Sync based on branch

**If on `main`:**
```powershell
git pull origin main
```

**If on a feature branch:**

First, check the branch's relationship with main:

```powershell
# Commits on this branch not in main
git log --oneline main..HEAD

# Commits on main not in this branch
git log --oneline HEAD..origin/main

# Check if branch also has a remote tracking branch
git rev-parse --abbrev-ref --symbolic-full-name "@{u}" 2>$null
```

Then sync in this order:

1. **Pull the branch's own remote** (if it has one):
   ```powershell
   git pull origin (git branch --show-current)
   ```

2. **Check divergence from main:**
   ```powershell
   git log --oneline HEAD..origin/main
   ```

3. **If main has new commits**, rebase the feature branch onto main:
   ```powershell
   git rebase origin/main
   ```

### 2d: Conflict resolution

If rebase or pull produces conflicts:

1. **List conflicted files:**
   ```powershell
   git diff --name-only --diff-filter=U
   ```

2. **For each conflicted file**, read the file and resolve the conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`). Prefer the approach that:
   - Keeps both sets of changes where possible
   - Favours the feature branch's intent for feature-specific code
   - Favours main's version for shared infrastructure (config, routes, seeders)

3. **After resolving each file:**
   ```powershell
   git add <resolved-file>
   ```

4. **Continue the rebase:**
   ```powershell
   git rebase --continue
   ```

5. **If conflicts are too complex to auto-resolve**, abort and report to the user:
   ```powershell
   git rebase --abort
   ```
   Then explain what conflicts exist and ask the user how they want to proceed.

### 2e: Report to the user

- Current branch name
- Whether there are uncommitted changes
- Sync status: up to date / pulled N commits / rebased onto main
- Any conflicts that were resolved or need attention
- Last 10 commits (so we know where we left off)

## Step 3: Recent Activity Summary

Check what was worked on recently:

```powershell
# Files changed today
git log --since="midnight" --name-only --pretty=format:"" | Sort-Object -Unique

# Files changed in last 3 days (in case session spans days)
git log --since="3 days ago" --oneline
```

## Step 4: Free Ports & Start Dev Server

The dev server uses ports **8000** (Laravel) and **5173** (Vite). Before starting, check if these ports are occupied and kill any processes holding them.

### Check and free ports (Windows commands):

```powershell
# Check port 8000
netstat -ano | findstr :8000
# If occupied, get the PID from the last column and kill it:
# taskkill /PID <pid> /F

# Check port 5173
netstat -ano | findstr :5173
# If occupied, get the PID from the last column and kill it:
# taskkill /PID <pid> /F
```

For each port (8000 and 5173):
1. Run `netstat -ano | findstr :<port>` to check if anything is listening
2. If a process is found, extract the PID from the output (last column)
3. Kill it with `taskkill /PID <pid> /F`
4. Confirm the port is now free

### Start the dev server

Once both ports are confirmed free, automatically start the dev server in the background:

```powershell
.\dev.ps1
```

Run this in the background so the session bootstrap can continue without waiting.

## Step 5: Session Context Display

Present a clean summary to the user:

```markdown
## Session Ready

**Branch:** `branch-name`
**Status:** Clean / X uncommitted changes
**Last work:** [summary of recent commits]

**Database:** Seeded successfully (18 seeders)
**Ports:** 8000 and 5173 cleared and available
**Dev server:** Running on :8000/:5173

**Recent changes:**
- [list of recently changed files/features]
```

## Important

- ALWAYS seed first. No exceptions. No "I'll do it later". Seed FIRST.
- Do NOT run `migrate:fresh` or `migrate:refresh` — these destroy data.
- ALWAYS free ports 8000 and 5173 before starting the dev server — kill any occupying processes.
- Auto-start the dev server (`.\dev.ps1`) after ports are confirmed free.
- Do NOT make any code changes — this is a read-only bootstrap.
- If the user has a specific task in mind, after displaying the summary, proceed to their request.
