# Claude Code Automation Recommendations for Fynla

## Codebase Profile

| Attribute | Value |
|-----------|-------|
| **Language** | PHP 8.1 + JavaScript (ES modules) |
| **Backend** | Laravel 10, Sanctum, Pest, Pint (PSR-12) |
| **Frontend** | Vue 3, Vuex 4, Vue Router 4, Vite 5, Tailwind CSS 3 |
| **Database** | MySQL 8 |
| **Charts** | ApexCharts (vue3-apexcharts) |
| **Testing** | Pest (PHP), Vitest + Vue Test Utils (JS), Playwright (E2E) |
| **Hosting** | SiteGround (manual deploy via File Manager) |
| **CI/CD** | None (no GitHub Actions or pipelines) |
| **Scale** | ~315 Vue components, 141+ PHP services, 68 controllers, 70 models, 1,075+ tests |

---

## Current Setup Inventory

### Already Configured

| Type | Name | Status |
|------|------|--------|
| **Skill** | `systematic-debugging` | Active, well-configured |
| **Subagent** | `database-optimizer` | Active |
| **Subagent** | `premium-ui-designer` | Active |
| **Subagent** | `product-manager` | Active |
| **Subagent** | `ux-writing-expert` | Active |
| **Subagent** | `laravel-stack-deployer` | Active |
| **Plugin** | `context7` | Enabled (MCP: live docs) |
| **Plugin** | `frontend-design` | Enabled |
| **Plugin** | `code-review` | Enabled |
| **Plugin** | `code-simplifier` | Enabled |
| **Plugin** | `claude-md-management` | Enabled |
| **Plugin** | `claude-code-setup` | Enabled |
| **Plugin** | `ralph-loop` | Enabled |
| **Plugin** | `php-lsp` | Enabled |
| **Plugin** | `laravel-boost` | Enabled |

### Not Configured

| Type | Gap |
|------|-----|
| **Hooks** | None configured - no auto-formatting, linting, or file protection |
| **MCP Servers** | No `.mcp.json` - context7 runs via plugin only |
| **CI/CD** | No GitHub Actions or automated pipelines |
| **Permissions** | `settings.local.json` has accumulated stale/verbose entries |

---

## Recommendations

### 1. Hooks (Highest Impact - None Exist Yet)

Hooks are the biggest gap. They run automatically on tool events and prevent common mistakes.

#### 1a. Auto-Format PHP on Save

**Why:** Pint (PSR-12) is configured but only runs manually. Every PHP file edit should be auto-formatted to prevent style drift and failed architecture tests.

**Where:** `.claude/settings.json` (project-level, checked into git)

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "filepath=\"$CLAUDE_FILE_PATH\"; if [[ \"$filepath\" == *.php ]]; then ./vendor/bin/pint \"$filepath\" --quiet 2>/dev/null; fi"
          }
        ]
      }
    ]
  }
}
```

**Impact:** Eliminates all PSR-12 formatting issues automatically. No more manual `./vendor/bin/pint` runs.

#### 1b. Block Sensitive File Edits

**Why:** `.env`, `.env.production`, lock files, and migration history should never be edited accidentally. The codebase has production SSH credentials in deploy scripts.

**Where:** `.claude/settings.json`

```json
{
  "hooks": {
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "filepath=\"$CLAUDE_FILE_PATH\"; blocked_patterns=(\".env\" \"composer.lock\" \"package-lock.json\" \".env.production\"); for pattern in \"${blocked_patterns[@]}\"; do if [[ \"$filepath\" == *\"$pattern\"* ]]; then echo \"BLOCKED: Cannot edit $pattern files. These contain sensitive configuration or lock files.\"; exit 2; fi; done"
          }
        ]
      }
    ]
  }
}
```

**Impact:** Prevents accidental edits to environment files, lock files, and production configs.

#### 1c. Run Related Tests After PHP Edits

**Why:** 1,075+ tests exist but are only run manually. Auto-running related tests after editing a service or controller catches regressions immediately.

**Where:** `.claude/settings.json`

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "filepath=\"$CLAUDE_FILE_PATH\"; if [[ \"$filepath\" == *.php && \"$filepath\" != *migration* && \"$filepath\" != *seeder* ]]; then testpath=$(echo \"$filepath\" | sed 's|app/|tests/Unit/|' | sed 's|Services/|Services/|' | sed 's|\\.php|Test.php|'); if [ -f \"$testpath\" ]; then ./vendor/bin/pest \"$testpath\" --no-coverage 2>/dev/null; fi; fi"
          }
        ]
      }
    ]
  }
}
```

**Impact:** Catches regressions immediately after code changes. Only runs if a matching test file exists.

---

### 2. Skills

#### 2a. Deploy Checklist Skill

**Why:** Deployment is manual (SiteGround File Manager) and error-prone. A deploy skill could automate the checklist: build, list changed files, generate upload instructions, and provide the SSH cache-clear command.

**Where:** `.claude/skills/deploy-checklist/SKILL.md`

```yaml
---
name: deploy-checklist
description: Generate deployment checklist with changed files, build output, and upload instructions for SiteGround
disable-model-invocation: true
---
```

**Skill content should:**
1. Run `git diff --name-only origin/main...HEAD` to list changed files
2. Categorise into PHP files (manual upload) and frontend files (build required)
3. If frontend changes detected, remind to run `./deploy/fynla-org/build.sh`
4. Generate the upload path mapping (local path -> SiteGround path)
5. Output the SSH cache-clear command
6. Warn about any migration files that need `php artisan migrate` on server

**Impact:** Eliminates forgotten file uploads and missed cache clears. Reduces deployment errors.

#### 2b. New Feature Scaffold Skill

**Why:** Adding a new feature requires creating files in 6+ locations (controller, service, model, migration, Vue component, Vuex store, API service, route, form request). A scaffold skill ensures all patterns are followed.

**Where:** `.claude/skills/scaffold-feature/SKILL.md`

```yaml
---
name: scaffold-feature
description: Scaffold a new feature with all required files following Fynla conventions
disable-model-invocation: true
---
```

**Skill content should:**
1. Accept feature name and module
2. Create controller in `app/Http/Controllers/Api/`
3. Create service in `app/Services/{Module}/`
4. Create form request in `app/Http/Requests/`
5. Add routes to `routes/api.php`
6. Create Vue component in `resources/js/components/{Module}/`
7. Create or update Vuex store module
8. Create API service in `resources/js/services/`
9. Create Pest test in `tests/Unit/` and `tests/Feature/`
10. All files follow existing conventions (strict types, PSR-12, canonical enums, etc.)

**Impact:** Ensures consistent patterns across the 7 modules. Saves 30-60 minutes per new feature.

---

### 3. MCP Servers

#### 3a. MySQL MCP Server

**Why:** Fynla uses MySQL 8 with 40+ tables. Currently, database inspection requires running artisan commands or raw SQL via bash. A MySQL MCP server gives Claude direct schema inspection and query capabilities.

**Install:**
```bash
claude mcp add mysql-server -- npx -y @anthropic/mysql-mcp --host 127.0.0.1 --port 3306 --user root --database fynla
```

Or add to `.mcp.json` (project-level):
```json
{
  "mcpServers": {
    "mysql": {
      "command": "npx",
      "args": ["-y", "@anthropic/mysql-mcp", "--host", "127.0.0.1", "--port", "3306", "--user", "root", "--database", "fynla"]
    }
  }
}
```

**Impact:** Direct schema exploration, query testing, and data verification without bash commands. Speeds up database-related debugging and development.

#### 3b. Playwright MCP Server

**Why:** Playwright is already a devDependency (`@playwright/test`). The E2E test infrastructure exists but has no tests. A Playwright MCP server enables browser automation for testing the Vue SPA directly.

**Install:**
```bash
claude mcp add playwright -- npx -y @anthropic/playwright-mcp
```

**Impact:** Enables automated E2E testing of the Vue SPA, form submissions, and multi-step flows (login, preview mode, module navigation). Complements existing browser automation via claude-in-chrome.

---

### 4. Subagents

#### 4a. Security Reviewer Agent

**Why:** Fynla handles sensitive financial data (pensions, investments, IHT calculations, property values). The app has authentication, MFA, RBAC, and encryption. A security reviewer agent should automatically audit auth flows, input validation, and data exposure risks.

**Where:** `.claude/agents/security-reviewer.md`

```markdown
---
name: security-reviewer
description: Audit code changes for security vulnerabilities in financial data handling, authentication flows, input validation, and data exposure. Focus on OWASP top 10, Laravel-specific security patterns, and UK financial data protection requirements.
model: inherit
---

When reviewing code, check for:
- SQL injection via raw queries (only Repository/Service layer should query)
- XSS in Vue templates (v-html usage, unescaped output)
- Mass assignment vulnerabilities (missing $fillable/$guarded)
- Authentication bypass (missing auth:sanctum middleware)
- Preview user data leakage (is_preview_user isolation)
- Sensitive data in API responses (password hashes, tokens)
- Missing rate limiting on sensitive endpoints
- CSRF protection gaps
- Input validation completeness (FormRequest coverage)
- Encryption of PII fields
```

**Impact:** Catches security issues before they reach production. Critical for a financial planning app handling sensitive personal data.

#### 4b. UK Tax Compliance Reviewer Agent

**Why:** Fynla's core value is accurate UK tax calculations. Tax rules change annually (budget announcements). A compliance reviewer ensures calculations match current HMRC rules and flags hardcoded tax values.

**Where:** `.claude/agents/tax-compliance-reviewer.md`

```markdown
---
name: tax-compliance-reviewer
description: Review tax calculation code for UK compliance. Verify all tax values come from TaxConfigService (not hardcoded), check IHT/CGT/income tax/pension calculations against current HMRC rules, and flag any values that may have changed in recent budgets.
model: inherit
---

When reviewing tax-related code, verify:
- All tax thresholds/rates come from TaxConfigService (never hardcoded)
- IHT calculations use correct NRB (325k), RNRB (175k), rates (40%/36%)
- Pension Annual Allowance calculations handle taper correctly
- ISA allowance checks are dynamic (not hardcoded 20k)
- CGT calculations use correct rates and annual exempt amount
- Income tax band calculations handle Scottish rates if applicable
- Child Benefit HICBC calculations use correct thresholds
- Taper relief for PETs uses correct 7-year schedule
- All fallback values in TaxDefaults match current tax year
```

**Impact:** Prevents tax calculation errors that could give users incorrect financial advice. Essential for a financial planning tool.

---

### 5. Permissions Cleanup

#### 5a. Clean Up `settings.local.json`

**Why:** The current `settings.local.json` has 49 permission entries, many of which are stale one-off commands (lines 36-39 are multi-line bash snippets that were accidentally saved as permissions). This bloats context and slows permission matching.

**Recommended clean `settings.local.json`:**

```json
{
  "permissions": {
    "allow": [
      "Bash(php artisan:*)",
      "Bash(./vendor/bin/pest:*)",
      "Bash(./dev.sh:*)",
      "Bash(./deploy/fynla-org/build.sh:*)",
      "Bash(./deploy/csjones-fynla/build.sh:*)",
      "Bash(composer install:*)",
      "Bash(npm install)",
      "Bash(git status:*)",
      "Bash(git add:*)",
      "Bash(git commit:*)",
      "Bash(git push:*)",
      "Bash(git pull:*)",
      "Bash(git branch:*)",
      "Bash(git checkout:*)",
      "Bash(git fetch:*)",
      "Bash(git log:*)",
      "Bash(git diff:*)",
      "Bash(gh pr create:*)",
      "Bash(gh pr merge:*)",
      "Bash(mysql:*)",
      "Bash(php:*)",
      "Bash(ls:*)",
      "Bash(wc:*)",
      "Bash(sort:*)",
      "Bash(lsof:*)",
      "Bash(kill:*)",
      "Bash(pkill:*)",
      "WebSearch",
      "WebFetch(domain:developer.revolut.com)",
      "WebFetch(domain:github.com)"
    ]
  }
}
```

**Impact:** Reduces from 49 to 30 clean entries. Removes stale multi-line bash commands that were accidentally saved as permissions. Faster permission matching and cleaner config.

---

### 6. Project-Level Settings File

#### 6a. Create `.claude/settings.json` (Shared via Git)

**Why:** Currently only `settings.local.json` exists (gitignored). A project-level `settings.json` would share hooks and common permissions with any collaborators and persist across machines.

**Where:** `.claude/settings.json`

```json
{
  "hooks": {
    "PostToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "filepath=\"$CLAUDE_FILE_PATH\"; if [[ \"$filepath\" == *.php ]]; then ./vendor/bin/pint \"$filepath\" --quiet 2>/dev/null; fi"
          }
        ]
      }
    ],
    "PreToolUse": [
      {
        "matcher": "Edit|Write",
        "hooks": [
          {
            "type": "command",
            "command": "filepath=\"$CLAUDE_FILE_PATH\"; for p in .env .env.production composer.lock package-lock.json; do if [[ \"$filepath\" == *\"$p\"* ]]; then echo \"BLOCKED: $p is protected.\"; exit 2; fi; done"
          }
        ]
      }
    ]
  }
}
```

**Impact:** Hooks persist across sessions and machines. Auto-formatting and file protection are always active.

---

### 7. MCP Server Configuration File

#### 7a. Create `.mcp.json` (Shared via Git)

**Why:** No `.mcp.json` exists. MCP servers configured here are auto-discovered by Claude Code for anyone working on the project.

**Where:** `.mcp.json` (project root)

```json
{
  "mcpServers": {
    "mysql": {
      "command": "npx",
      "args": ["-y", "@anthropic/mysql-mcp", "--host", "127.0.0.1", "--port", "3306", "--user", "root", "--database", "fynla"]
    }
  }
}
```

**Impact:** Database access available automatically in every session. No manual setup required.

---

## Priority Implementation Order

| Priority | Recommendation | Effort | Impact |
|----------|----------------|--------|--------|
| 1 | **Create `.claude/settings.json` with Pint hook + file protection** | 5 min | High - auto-formatting + safety on every edit |
| 2 | **Clean up `settings.local.json`** | 5 min | Medium - cleaner config, faster matching |
| 3 | **Deploy checklist skill** | 15 min | High - eliminates deployment errors |
| 4 | **Security reviewer agent** | 10 min | High - critical for financial app |
| 5 | **Tax compliance reviewer agent** | 10 min | High - prevents incorrect tax advice |
| 6 | **MySQL MCP server** | 5 min | Medium - direct DB access in sessions |
| 7 | **Scaffold feature skill** | 20 min | Medium - consistent new feature creation |
| 8 | **Playwright MCP server** | 5 min | Low-Medium - enables E2E testing |
| 9 | **Auto-run related tests hook** | 5 min | Medium - catches regressions immediately |

---

## What's Already Well Covered

The existing setup is strong in several areas:

- **Debugging**: `systematic-debugging` skill is comprehensive and well-structured
- **Documentation lookup**: `context7` plugin provides live docs for Vue, Laravel, Tailwind
- **Code quality**: `code-review`, `code-simplifier`, `php-lsp`, `laravel-boost` plugins
- **UI work**: `premium-ui-designer` + `frontend-design` cover design needs
- **Planning**: `product-manager` agent handles feature planning
- **Database**: `database-optimizer` handles query performance
- **Deployment**: `laravel-stack-deployer` handles deployment guidance
- **UX writing**: `ux-writing-expert` handles user-facing text

The main gaps are **automation hooks** (nothing runs automatically), **security review** (critical for financial data), and **deployment safety** (manual process with no guardrails).

---

**Want help implementing any of these?** Just ask and I can set up any recommendation above. You can also ask for more recommendations in any specific category.
