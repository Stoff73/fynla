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

### Complete Seeder Inventory (18 seeders)

**Phase 1 — Required (14 seeders, run by `db:seed`):**

| Seeder | Purpose |
|--------|---------|
| `TaxConfigurationSeeder` | UK tax rates, allowances, thresholds (5 tax years) |
| `TaxProductReferenceSeeder` | ISA/GIA/Bond tax treatment info |
| `ActuarialLifeTablesSeeder` | ONS life expectancy data for estate/retirement projections |
| `RolesPermissionsSeeder` | Auth roles and permissions |
| `AdminUserSeeder` | Admin account (chris@fynla.org) |
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

```bash
git status
git branch --show-current
git log --oneline -10
```

### 2b: Fetch latest from remote

```bash
git fetch origin
```

### 2c: Sync based on branch

**If on `main`:**
```bash
git pull origin main
```

**If on a feature branch:**

First, check the branch's relationship with main:

```bash
# Commits on this branch not in main
git log --oneline main..HEAD

# Commits on main not in this branch
git log --oneline HEAD..origin/main

# Check if branch also has a remote tracking branch
git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null
```

Then sync in this order:

1. **Pull the branch's own remote** (if it has one):
   ```bash
   git pull origin $(git branch --show-current)
   ```

2. **Check divergence from main:**
   ```bash
   git log --oneline HEAD..origin/main --count
   ```

3. **If main has new commits**, rebase the feature branch onto main:
   ```bash
   git rebase origin/main
   ```

### 2d: Conflict resolution

If rebase or pull produces conflicts:

1. **List conflicted files:**
   ```bash
   git diff --name-only --diff-filter=U
   ```

2. **For each conflicted file**, read the file and resolve the conflict markers (`<<<<<<<`, `=======`, `>>>>>>>`). Prefer the approach that:
   - Keeps both sets of changes where possible
   - Favours the feature branch's intent for feature-specific code
   - Favours main's version for shared infrastructure (config, routes, seeders)

3. **After resolving each file:**
   ```bash
   git add <resolved-file>
   ```

4. **Continue the rebase:**
   ```bash
   git rebase --continue
   ```

5. **If conflicts are too complex to auto-resolve**, abort and report to the user:
   ```bash
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

```bash
# Files changed today
git log --since="midnight" --name-only --pretty=format:"" | sort -u

# Files changed in last 3 days (in case session spans days)
git log --since="3 days ago" --oneline
```

## Step 4: Check & Start Dev Server

Check if the dev server is already running:

```bash
# Check if Laravel/Vite are running
lsof -i :8000 2>/dev/null | head -3
lsof -i :5173 2>/dev/null | head -3
```

If not running, automatically start it in the background:

```bash
./dev.sh
```

Run this in the background so the session bootstrap can continue without waiting.

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

## Standing Authorisation After CSJ Agreement

Once CSJ explicitly approves a spec, implementation plan, or fix process, or explicitly directs its implementation, treat that agreement as standing authorisation for all reasonably necessary work within its scope. Proceed autonomously and do not ask CSJ to approve individual files, edits, commands, or routine implementation decisions again.

Standing authorisation includes:

- Creating, editing, and renaming in-scope files
- Deleting only in-scope tracked files recoverable from version control
- Running commands, tests, linters, formatters, builds, seeders, and local migrations
- Starting, stopping, or restarting local development processes
- Installing or updating dependencies required by the agreed work
- Making proportionate corrections discovered during implementation when they preserve the agreed outcome and scope

Ask CSJ for new direction only when an action would:

- Materially expand or change the agreed scope or outcome
- Require an unresolved product or technical decision with materially different consequences
- Cause destructive or irreversible loss of application data, user data, environment state, untracked files, or uncommitted work
- Access, read, reveal, use, expose, or transmit credentials or other secrets unless that credential action was explicitly included in CSJ's agreement
- Affect production or a third-party system
- Commit, push, or deploy unless that action was explicitly included in the agreement

Runtime security controls remain authoritative. If the platform itself requires approval or elevation, invoke its approval mechanism directly with the narrowest permission required; do not add a separate conversational permission round first.

## Important

- ALWAYS seed first. No exceptions. No "I'll do it later". Seed FIRST.
- Do NOT run `migrate:fresh` or `migrate:refresh` — these destroy data.
- Auto-start the dev server (`./dev.sh`) if it's not already running.
- Bootstrap must not make task-specific source edits. The prescribed Git synchronization and conflict-resolution steps may update tracked files. After displaying the summary, proceed to the user's task under the standing-authorisation rules above.
