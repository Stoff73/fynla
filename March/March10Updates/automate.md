# Claude Code Automation Recommendations

**Date:** 10 March 2026
**Source:** Analysis of Obsidian vault (`fynlaBrain/`), 124 session transcripts, 1,145 git commits, and existing automation config

---

## Key Finding: 25% Prompt Overhead

From `agentSkill.md` analysis across 1,520 prompts:

| Activity | % of Prompts | Prompts Saved by Automating |
|----------|-------------|----------------------------|
| Git operations (commit/push/PR/merge) | 13.4% | ~30 |
| Deployment tracking (deploy.md files) | 12.5% | ~120 |
| Session setup ("start dev servers") | ~3.3% | ~50 |
| Database seeding requests | ~3.4% | ~52 |
| **Total recoverable** | **~32%** | **~252 prompts** |

---

## Recommended Automations (Priority Order)

### 1. `/session-start` Skill - Pre-Session Bootstrap

**Saves:** ~50 prompts
**Replaces:** "start dev servers", "seed the database", "what branch am I on"

What it does:
- Runs `php artisan db:seed` (enforces the #1 critical rule)
- Checks git status and current branch
- Shows recent commits since last session
- Reads pending tasks from Obsidian vault session index
- Starts `./dev.sh` if not running
- Displays quick context summary (what was worked on last)

---

### 2. `/deploy-notes` Skill - Auto-Generate Deployment Docs

**Saves:** ~120 prompts
**Replaces:** manual `MarchXUpdates/` file creation

What it does:
- Diffs current branch vs main
- Categorises changed files (PHP/Vue/config/migration/seeder)
- Determines if rebuild needed (`build.sh` vs PHP-only upload)
- Generates SiteGround upload checklist with exact paths
- Generates SSH cache-clear commands
- Saves to `[Month][Date]Updates/` in Obsidian vault format

---

### 3. `/session-end` Skill - Post-Session Wrap-Up

**Saves:** ~20 prompts
**Replaces:** inconsistent end-of-session steps

What it does:
- Runs `/tech-debt-session` on changed files
- Runs `php artisan db:seed` (final reseed)
- Generates deploy notes if files changed (`/deploy-notes`)
- Updates Obsidian vault session index (`March Index.md`)
- Optionally commits and pushes
- Shows summary of session work

---

### 4. `/ship` Skill - Atomic Git Pipeline

**Saves:** ~30 prompts
**Replaces:** "commit this", "push it", "create a PR", "merge to main"

What it does:
- Stages relevant files (excludes .env, secrets)
- Generates commit message from changes
- Pushes with `-u` flag
- Creates PR with auto-generated summary
- Optionally merges to main after confirmation

---

### 5. Pre-Session Hook - Database Seed Verification

**Type:** Hook (automatic, no invocation needed)
**Replaces:** The seeding violations that caused fury

What it does:
- On first Bash tool use of a session, checks if TaxConfiguration table is seeded
- If not seeded, auto-runs `php artisan db:seed`
- Prevents the #1 recurring failure mode

---

### 6. Obsidian Vault Sync - Session Auto-Indexing

**Type:** Part of `/session-end` skill
**Replaces:** Manual updates to `Feb Index.md` / `March Index.md`

What it does:
- Creates session file with standard YAML frontmatter (date, branch, tags)
- Auto-appends entry to monthly index
- Cross-links to deploy notes and git commits

---

## Complete Seeder Reference (17 Seeders)

`php artisan db:seed` runs all Phase 1 + Phase 2 seeders via `DatabaseSeeder.php`.

### Phase 1: Required Data (MUST RUN - app will not function without these)

| # | Seeder | Purpose | Individual Command |
|---|--------|---------|-------------------|
| 1 | `TaxConfigurationSeeder` | UK tax rates, allowances, thresholds (5 tax years) | `php artisan db:seed --class=TaxConfigurationSeeder --force` |
| 2 | `TaxProductReferenceSeeder` | ISA/GIA/Bond tax treatment info | `php artisan db:seed --class=TaxProductReferenceSeeder --force` |
| 3 | `ActuarialLifeTablesSeeder` | ONS life expectancy data for estate/retirement projections | `php artisan db:seed --class=ActuarialLifeTablesSeeder --force` |
| 4 | `RolesPermissionsSeeder` | Auth roles and permissions (must run before AdminUserSeeder) | `php artisan db:seed --class=RolesPermissionsSeeder --force` |
| 5 | `AdminUserSeeder` | Admin test accounts (demo@fps.com, admin@fps.com) | `php artisan db:seed --class=AdminUserSeeder --force` |
| 6 | `PreviewUserSeeder` | 6 preview personas (young_family, peak_earners, widow, entrepreneur, young_saver, retired_couple) | `php artisan db:seed --class=PreviewUserSeeder --force` |
| 7 | `SavingsMarketRatesSeeder` | Savings benchmark rates | `php artisan db:seed --class=SavingsMarketRatesSeeder --force` |
| 8 | `PlanConfigurationSeeder` | Admin-configurable plan rates, benchmarks, defaults | `php artisan db:seed --class=PlanConfigurationSeeder --force` |
| 9 | `RetirementActionDefinitionSeeder` | Configurable retirement plan action triggers | `php artisan db:seed --class=RetirementActionDefinitionSeeder --force` |
| 10 | `InvestmentActionDefinitionSeeder` | Configurable investment plan action triggers | `php artisan db:seed --class=InvestmentActionDefinitionSeeder --force` |
| 11 | `ProtectionActionDefinitionSeeder` | Configurable protection plan action triggers | `php artisan db:seed --class=ProtectionActionDefinitionSeeder --force` |
| 12 | `TaxActionDefinitionSeeder` | Configurable tax optimisation action triggers | `php artisan db:seed --class=TaxActionDefinitionSeeder --force` |
| 13 | `SubscriptionPlanSeeder` | Subscription pricing and trial config (Student/Standard/Pro) | `php artisan db:seed --class=SubscriptionPlanSeeder --force` |

### Phase 2: Optional Data (local/development/staging only)

| # | Seeder | Purpose | Individual Command |
|---|--------|---------|-------------------|
| 14 | `HouseholdSeeder` | Households for multi-user testing | `php artisan db:seed --class=HouseholdSeeder --force` |
| 15 | `TestUsersSeeder` | Additional test user accounts | `php artisan db:seed --class=TestUsersSeeder --force` |

### Standalone Seeder (not in DatabaseSeeder - run manually)

| # | Seeder | Purpose | Individual Command |
|---|--------|---------|-------------------|
| 16 | `OccupationCodeSeeder` | ONS SOC 2020 occupation codes (truncates and reloads) | `php artisan db:seed --class=OccupationCodeSeeder --force` |

### Quick Fix Reference

| Issue | Seeder to Run |
|-------|--------------|
| Tax calculations failing / wrong rates | `TaxConfigurationSeeder` |
| Tax Status tab empty | `TaxProductReferenceSeeder` |
| Preview personas broken / 403 errors | `PreviewUserSeeder` |
| Life expectancy errors | `ActuarialLifeTablesSeeder` |
| Savings market rates missing | `SavingsMarketRatesSeeder` |
| Plan actions not showing | `RetirementActionDefinitionSeeder` + `InvestmentActionDefinitionSeeder` + `ProtectionActionDefinitionSeeder` + `TaxActionDefinitionSeeder` |
| Subscription plans missing | `SubscriptionPlanSeeder` |
| Plan benchmarks/defaults wrong | `PlanConfigurationSeeder` |
| Occupation dropdown empty | `OccupationCodeSeeder` |
| Roles/permissions errors | `RolesPermissionsSeeder` |
| Admin accounts missing | `AdminUserSeeder` (after `RolesPermissionsSeeder`) |

---

## What Already Works Well (Keep As-Is)

- Pint auto-formatting hook (PostToolUse)
- Protected file guards (.env, lock files)
- 7 domain-expert agents (tax, security, database, UI, etc.)
- deploy-checklist skill (complements `/deploy-notes`)
- tech-debt-session and tech-debt-full skills
- MySQL and Playwright MCP servers

---

## Implementation Priority

| Phase | Skills/Hooks | Impact |
|-------|-------------|--------|
| **Now** | `/session-start`, `/session-end` | Bookend every session with proper setup/teardown |
| **Next** | `/deploy-notes`, `/ship` | Eliminate git + deploy overhead (25% of prompts) |
| **Later** | Pre-session seed hook, vault auto-indexing | Full automation of manual steps |

---

## Existing Setup Summary

### Current Skills (7)
| Skill | Purpose |
|-------|---------|
| `/systematic-debugging` | Four-phase debugging framework |
| `/tech-debt-session` | Session-level changed file audit |
| `/tech-debt-full` | Full codebase weekly/monthly audit |
| `/deploy-checklist` | Pre-deployment verification |
| `/scaffold-feature` | Full feature scaffolding |
| `/skill-creator` | Create and test skills |
| `/cost-estimate` | Development cost estimation |

### Current Agents (7)
| Agent | Purpose |
|-------|---------|
| `database-optimizer` | Query and schema optimisation |
| `laravel-stack-deployer` | Production deployment |
| `product-manager` | Feature planning and user stories |
| `premium-ui-designer` | UI/UX polish and animations |
| `security-reviewer` | Auth and API security |
| `tax-compliance-reviewer` | UK HMRC tax compliance |
| `ux-writing-expert` | Copy and messaging |

### Current MCP Servers (2)
- **mysql** - Direct database queries
- **playwright** - Browser automation

### Current Hooks
- **PostToolUse:** Auto-format PHP with Pint
- **PreToolUse:** Block edits to .env, composer.lock, package-lock.json

### Installed Plugins
- superpowers, fynla-dev-skills, fynla-compliance, fynla-design, fynla-ops
- frontend-design, context7, code-review, code-simplifier, claude-md-management
- claude-code-setup, php-lsp, feature-dev, security-guidance
