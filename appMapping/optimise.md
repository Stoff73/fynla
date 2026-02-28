# Claude Code Automation - Fynla

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
| **Scale** | ~315 Vue components, 141+ PHP services, 68 controllers, 70 models, 1,075+ tests |

---

## Full Inventory

### Hooks (`.claude/settings.json`)

| Hook | Type | Trigger | What It Does |
|------|------|---------|--------------|
| **Pint Auto-Format** | PostToolUse | Edit\|Write on `*.php` | Runs `./vendor/bin/pint` on the file automatically |
| **File Protection** | PreToolUse | Edit\|Write | Blocks edits to `.env`, `.env.production`, `composer.lock`, `package-lock.json` |

### Skills (`.claude/skills/`)

| Skill | Invocation | Purpose |
|-------|------------|---------|
| `systematic-debugging` | `/systematic-debugging` | Four-phase debugging framework - root cause before fixes |
| `deploy-checklist` | `/deploy-checklist` | Generates deployment file list, build instructions, upload paths, SSH commands |
| `scaffold-feature` | `/scaffold-feature` | Scaffolds all files for a new feature (controller, service, model, migration, Vue, store, tests) |

### Subagents (`.claude/agents/`)

| Agent | Auto-Invoked When |
|-------|-------------------|
| `database-optimizer` | Queries are slow or designing new tables/schemas |
| `laravel-stack-deployer` | Production deployment tasks |
| `product-manager` | Planning new features or creating user stories |
| `premium-ui-designer` | Polishing UI, adding animations, improving UX |
| `ux-writing-expert` | Improving user-facing text, error messages, labels |
| `security-reviewer` | Reviewing auth flows, API endpoints, or code touching sensitive financial data |
| `tax-compliance-reviewer` | Modifying tax calculations, financial projections, or TaxConfigService usage |

### Plugins (Global `~/.claude/settings.json`)

| Plugin | Purpose |
|--------|---------|
| `context7` | Live documentation lookup for Vue, Laravel, Tailwind, etc. |
| `frontend-design` | Production-grade frontend interface design |
| `code-review` | Pull request code review |
| `code-simplifier` | Code clarity and refactoring |
| `claude-md-management` | CLAUDE.md audit and improvement |
| `claude-code-setup` | Automation recommendations |
| `ralph-loop` | Iterative refinement loop |
| `php-lsp` | PHP language server integration |
| `laravel-boost` | Laravel-specific tooling |

### MCP Servers (`.mcp.json`)

| Server | Package | Purpose |
|--------|---------|---------|
| `mysql` | `@benborla29/mcp-server-mysql` | Direct MySQL schema inspection and query access |
| `playwright` | `@playwright/mcp` | Browser automation and E2E testing |
| `claude-in-chrome` | (IDE extension) | Live browser automation via Chrome |
| `context7` | (via plugin) | Library documentation lookup |

### Permissions (`.claude/settings.local.json`)

33 clean permission entries covering: artisan, pest, dev/deploy scripts, git operations, gh CLI, mysql, web search, and Revolut API docs.

---

## CLAUDE.md Files

| File | Purpose |
|------|---------|
| `CLAUDE.md` | Root project context - commands, architecture, key rules, deployment |
| `resources/js/CLAUDE.md` | Frontend conventions - store patterns, mixins, utils, components, directives |
| `app/Services/CLAUDE.md` | Service layer - agent pattern, traits, constants, TaxConfigService, exceptions |
| `app/Http/CLAUDE.md` | HTTP layer - controllers, middleware, request validation, resources, routes |
| `tests/CLAUDE.md` | Testing - Pest syntax, unit/feature/architecture patterns, factories, mocking |
| `database/CLAUDE.md` | Database - migrations, seeders, column conventions, indexes, factories |

---

## Future Considerations

| Idea | Type | When |
|------|------|------|
| Auto-run related tests after PHP edits | Hook | When test coverage is higher and test speed is fast enough |
| GitHub Actions CI pipeline | CI/CD | When ready to automate test runs on push/PR |
| Sentry MCP server | MCP | When error tracking is added to production |
| Auto-run architecture tests on commit | Hook | When pre-commit workflow is established |
