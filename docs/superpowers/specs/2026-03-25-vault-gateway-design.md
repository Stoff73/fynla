# Vault Gateway System — Design Spec

**Date:** 25 March 2026
**Purpose:** Ensure every Claude instance (sessions, sub-agents, branch work) has access to accumulated project knowledge from the fynlaBrain Obsidian vault before making decisions.

**Problem:** Claude loses context between sessions. 693 files of architecture docs, current state, bug fixes, session history, and hard-won rules sit in the vault but no Claude instance reads them. The same mistakes get repeated.

## Components

### 1. `vault-context` Skill

Core skill that assembles a targeted brief from the vault for a given module or topic.

**Invocation:** `/vault-context investment` or `/vault-context deployment` or `/vault-context general`

**Vault location:** `/Users/CSJ/Desktop/fynlaBrain/`

**What it reads, in order:**

| # | Layer | Source | Purpose |
|---|-------|--------|---------|
| 1 | Architecture | `Architecture/v083/*.md` (relevant section) | How the module is built, patterns, relationships |
| 2 | Current State | `Current State/*.md` | What exists now, known limitations |
| 3 | Recent Fixes | `March/March*Updates/deploy*.md`, `*fix*.md`, `*Fix*.md` | Bugs found and fixed, what broke and why |
| 4 | Session History | `March/March*Updates/*.md` (last 3 sessions) | What was worked on recently, outstanding items |
| 5 | Reports | `Reports/*.md` | Code reviews, tech debt, security findings |
| 6 | Feedback Rules | Memory `feedback_*.md` files (ALL) | User frustrations, non-negotiable rules |
| 7 | Design System | `Design/fynlaDesignGuide.md` (if UI work) | Colours, typography, component patterns |

**Module → Vault doc mapping:**

| Module | Architecture Doc | Current State Doc | Other Sources |
|--------|-----------------|-------------------|---------------|
| Investment | `v083/09-MODULES.md` | `Investment.md` | `Investment Tree/` |
| Estate | `v083/09-MODULES.md` | `EstatePlanning.md` | — |
| Protection | `v083/09-MODULES.md` | `Protection.md` | — |
| Retirement | `v083/09-MODULES.md` | `Retirement.md` | — |
| Savings | `v083/09-MODULES.md` | `Savings.md` | — |
| Goals | `v083/09-MODULES.md` | `GoalsLifeEvents.md` | — |
| Property | `v083/09-MODULES.md` | `Property.md` | — |
| Auth/Security | `v083/03-AUTHENTICATION-SECURITY.md` | `Auth.md` | — |
| Database | `v083/02-DATABASE.md` | — | — |
| Frontend | `v083/05-FRONTEND-ARCHITECTURE.md` | — | `Design/` |
| Backend | `v083/04-BACKEND-ARCHITECTURE.md` | — | — |
| Deployment | `v083/11-CONFIGURATION-DEPLOYMENT.md` | `DeploymentBuild.md` | `Deploy/` |
| AI Chat | `v083/10-NEW-SYSTEMS.md` | — | `March/March24Updates/AI/` |
| Design System | — | — | `Design/fynlaDesignGuide.md` |
| Tax/Financial | `v083/08-FINANCIAL-CALCULATIONS.md` | `UKTaxes.md` | — |
| Onboarding | `v083/10-NEW-SYSTEMS.md` | `Onboarding.md` | — |
| Payments | `v083/10-NEW-SYSTEMS.md` | `PaymentSubscription.md` | `Revolut/` |
| User Profile | `v083/09-MODULES.md` | `UserProfile.md` | — |
| Net Worth | `v083/09-MODULES.md` | `NetWorth.md` | — |

**Fix/bug scanning:** The skill scans deploy and fix files for mentions of the module name (case-insensitive grep) and extracts the relevant bug/fix entries. This surfaces historical issues so they aren't re-introduced.

**`general` mode:** Used at session start. Loads:
- All feedback rules (memory files)
- Last 3 session notes from vault
- Outstanding TODOs from vault (`CSJTODO.md` in most recent March folder)
- Recent reports (last 7 days)
- No module-specific architecture/current state

**Output format:**

```markdown
## Vault Context: [Module]

### Architecture
[key patterns, relationships, file locations — trimmed to module section]

### Current State
[what exists, known limitations]

### Recent Fixes & Bugs (last 7 days)
[bugs found, what caused them, how they were fixed — from deploy/fix docs]

### Session History
[what was worked on recently, outstanding items — from last 3 sessions]

### Rules & Feedback (MUST follow)
[all feedback rules — these are non-negotiable]

### Reports
[relevant findings from code reviews, tech debt — if any]
```

### 2. CLAUDE.md Vault Reference Map

A concise section added to the root CLAUDE.md so the module→vault mapping is always in context. Claude can look up which vault doc to read without invoking the skill.

```markdown
## Vault Reference (fynlaBrain)

Vault path: `/Users/CSJ/Desktop/fynlaBrain/`

Before working on any module, load vault context: `/vault-context [module]`

| Module | Architecture | Current State |
|--------|-------------|---------------|
| Investment | v083/09-MODULES | Investment.md |
| Estate | v083/09-MODULES | EstatePlanning.md |
| Protection | v083/09-MODULES | Protection.md |
| Retirement | v083/09-MODULES | Retirement.md |
| Savings | v083/09-MODULES | Savings.md |
| Goals | v083/09-MODULES | GoalsLifeEvents.md |
| Property | v083/09-MODULES | Property.md |
| Auth/Security | v083/03-AUTH | Auth.md |
| Frontend | v083/05-FRONTEND | — |
| Backend | v083/04-BACKEND | — |
| Deployment | v083/11-CONFIG | DeploymentBuild.md |
| AI Chat | v083/10-NEW-SYSTEMS | — |
| Tax/Financial | v083/08-FINANCIAL | UKTaxes.md |
```

### 3. Session-start Enhancement

New step added after reading memory/TODO (Step 1b):

1. Read `fynlaBrain/March/March Index.md` — extract last 3 session entries
2. Read the most recent `CSJTODO.md` from vault (may be newer than repo TODO.md)
3. Read ALL `feedback_*.md` memory files and present the rules
4. Check `Reports/` for anything from the last 7 days
5. Present a summary of accumulated context

This ensures every new session starts with the accumulated wisdom.

### 4. Agent Dispatch Protocol

A mandatory rule in CLAUDE.md for dispatching sub-agents:

```markdown
## Sub-Agent Vault Context (MANDATORY)

When dispatching ANY agent to work on module code:
1. Run `/vault-context [module]` first (or read the relevant vault docs inline)
2. Include in the agent prompt: architecture patterns, recent fixes, feedback rules
3. Specifically include ALL feedback_*.md rules — non-negotiable for every agent

Never dispatch an agent with just "fix X" or "build Y". Always include:
- What module this is in and its patterns
- Recent bugs/fixes in this area
- The feedback rules that apply
```

### 5. Pre-Edit Reminder Hook

A PostToolUse hook on Edit/Write that pattern-matches the file path to a module and echoes a reminder if vault context hasn't been loaded.

**Path patterns:**

| Path contains | Module |
|---------------|--------|
| `Services/Investment`, `components/Investment`, `Investment` | Investment |
| `Services/Estate`, `components/Estate`, `Estate` | Estate |
| `Services/Protection`, `components/Protection`, `Protection` | Protection |
| `Services/Retirement`, `components/Retirement`, `Retirement` | Retirement |
| `Services/Savings`, `components/Savings`, `Savings` | Savings |
| `Services/Goals`, `components/Goals`, `Goals` | Goals |
| `components/NetWorth/Property`, `Property` | Property |
| `deploy/`, `Deploy` | Deployment |
| `components/Admin`, `AdminController` | Admin |

**Hook output:** `VAULT CHECK: Editing [Module] module file. Ensure vault context is loaded (/vault-context [module]).`

## Flow

```
New session starts
  → session-start loads general vault context
    (feedback rules, recent sessions, TODOs, reports)

User says "work on investment detail view"
  → /vault-context investment
    (architecture, current state, recent fixes, reports, rules)

Claude dispatches agent to fix trust form
  → Reads vault context for Estate
  → Includes architecture, trust 422 fix, feedback rules in agent prompt

Claude edits app/Services/Protection/CoverageGapAnalyzer.php
  → Hook: "VAULT CHECK: Editing Protection module..."
```

## Files to Create/Modify

| File | Action |
|------|--------|
| `.claude/skills/vault-context/SKILL.md` | CREATE — the core skill |
| `.claude/skills/session-start/SKILL.md` | MODIFY — add vault general context step |
| `CLAUDE.md` | MODIFY — add vault reference map + agent dispatch protocol |
| `.claude/settings.json` | MODIFY — add pre-edit vault reminder hook |

## Success Criteria

1. Every new session automatically shows recent feedback rules, session history, and outstanding items from the vault
2. `/vault-context [module]` returns a focused brief with architecture, current state, fixes, and rules
3. Sub-agents receive vault context in their prompts
4. Editing a module file triggers a vault check reminder
5. Bugs that were previously fixed don't get re-introduced because the fix history is surfaced
