---
name: vault-context
description: Load project knowledge from the fynlaBrain Obsidian vault for a specific module or topic. Surfaces architecture docs, current state, recent fixes/bugs, session history, feedback rules, and reports. Use when starting work on any module, before dispatching sub-agents, or when you need context about how something was built or what bugs have been fixed. Triggers on "/vault-context", "load vault", "check vault", "vault context", or before any feature/fix work on a module.
disable-model-invocation: true
---

# Vault Context — Load Project Knowledge

Read targeted sections from the fynlaBrain Obsidian vault to give Claude full context before working on any module.

**Vault location:** `/Users/CSJ/Desktop/fynlaBrain/`

## Usage

- `/vault-context general` — Session-level context (feedback rules, recent sessions, TODOs, reports)
- `/vault-context [module]` — Module-specific context (architecture, current state, fixes, rules)
- `/vault-context` with no argument — defaults to `general`

## Module Map

| Module | Architecture Doc | Current State Doc | Other Sources |
|--------|-----------------|-------------------|---------------|
| investment | `Architecture/v083/09-MODULES.md` | `Current State/Investment.md` | `Investment Tree/` |
| estate | `Architecture/v083/09-MODULES.md` | `Current State/EstatePlanning.md` | — |
| protection | `Architecture/v083/09-MODULES.md` | `Current State/Protection.md` | — |
| retirement | `Architecture/v083/09-MODULES.md` | `Current State/Retirement.md` | — |
| savings | `Architecture/v083/09-MODULES.md` | `Current State/Savings.md` | — |
| goals | `Architecture/v083/09-MODULES.md` | `Current State/GoalsLifeEvents.md` | — |
| property | `Architecture/v083/09-MODULES.md` | `Current State/Property.md` | — |
| auth | `Architecture/v083/03-AUTHENTICATION-SECURITY.md` | `Current State/Auth.md` | — |
| database | `Architecture/v083/02-DATABASE.md` | — | — |
| frontend | `Architecture/v083/05-FRONTEND-ARCHITECTURE.md` | — | `Design/` |
| backend | `Architecture/v083/04-BACKEND-ARCHITECTURE.md` | — | — |
| deployment | `Architecture/v083/11-CONFIGURATION-DEPLOYMENT.md` | `Current State/DeploymentBuild.md` | `Deploy/` |
| ai-chat | `Architecture/v083/10-NEW-SYSTEMS.md` | — | `March/March24Updates/AI/` |
| design-system | — | — | `Design/fynlaDesignGuide.md` |
| tax | `Architecture/v083/08-FINANCIAL-CALCULATIONS.md` | `Current State/UKTaxes.md` | — |
| onboarding | `Architecture/v083/10-NEW-SYSTEMS.md` | `Current State/Onboarding.md` | — |
| payments | `Architecture/v083/10-NEW-SYSTEMS.md` | `Current State/PaymentSubscription.md` | `Revolut/` |
| user-profile | `Architecture/v083/09-MODULES.md` | `Current State/UserProfile.md` | — |
| net-worth | `Architecture/v083/09-MODULES.md` | `Current State/NetWorth.md` | — |
| admin | `Architecture/v083/04-BACKEND-ARCHITECTURE.md` | `Current State/Admin.md` | — |

## General Mode (`/vault-context general` or `/vault-context`)

Use at the start of every session or when you need a broad overview.

### Step 1: Load ALL feedback rules

These are non-negotiable rules from past sessions. Read EVERY file:

```bash
ls /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_*.md
```

Read each file and present a summary of each rule. These apply to ALL work.

### Step 2: Read recent session history from vault

```bash
# Get the 3 most recent session update folders
ls -d /Users/CSJ/Desktop/fynlaBrain/March/March*Updates 2>/dev/null | sort -V | tail -3
```

For each of the 3 most recent folders:
- Read any deploy notes (`deploy*.md`, `*deploy*.md`)
- Read any session summaries (`session*.md`, `*summary*.md`)
- Read any TODO files (`*TODO*.md`, `CSJTODO.md`)

Present key items: what was worked on, what was deployed, outstanding issues.

### Step 3: Read vault TODO (may be newer than repo)

```bash
# Most recent CSJTODO in vault
find /Users/CSJ/Desktop/fynlaBrain/March -name "CSJTODO.md" -type f 2>/dev/null | sort -V | tail -1
```

Compare with repo `TODO.md`. If different, present both.

### Step 4: Check recent reports

```bash
find /Users/CSJ/Desktop/fynlaBrain/Reports -name "*.md" -newer /Users/CSJ/Desktop/fynlaBrain/Reports -mtime -7 2>/dev/null
```

If any recent reports, summarise key findings (tech debt, security, code review).

### Step 5: Present summary

```markdown
## Vault Context: General

### Feedback Rules (MUST follow)
- [each rule from feedback_*.md — name and one-line summary]

### Recent Sessions (last 3)
- [date]: [what was worked on, deployed, outstanding]

### Outstanding Items
- [from vault CSJTODO / repo TODO]

### Recent Reports
- [findings or "None in last 7 days"]
```

## Module Mode (`/vault-context [module]`)

Use before working on a specific module.

### Step 1: Read architecture doc

Look up the module in the Module Map above. Read the architecture doc, but **trim to the relevant section only** — do not read the entire 09-MODULES.md file.

```bash
# For modules using 09-MODULES.md, grep for the section
grep -n -i "[module_name]" "/Users/CSJ/Desktop/fynlaBrain/Architecture/v083/09-MODULES.md" | head -5
```

Then read from the section header to the next `## ` header (typically 50-200 lines).

For modules with their own architecture doc (auth, database, frontend, backend, deployment, tax), read the full file but summarise — focus on patterns, key classes, relationships.

### Step 2: Read current state doc

```bash
cat "/Users/CSJ/Desktop/fynlaBrain/Current State/[CurrentStateDoc].md" 2>/dev/null
```

Present: what exists, known limitations, key files.

### Step 3: Scan for recent fixes and bugs

Search deploy and fix files from the last 14 days for mentions of this module:

```bash
# Find deploy/fix docs mentioning this module
grep -ril "[module_name]" /Users/CSJ/Desktop/fynlaBrain/March/March*Updates/deploy*.md /Users/CSJ/Desktop/fynlaBrain/March/March*Updates/*fix*.md /Users/CSJ/Desktop/fynlaBrain/March/March*Updates/*Fix*.md 2>/dev/null | tail -5
```

Also search the repo March updates:

```bash
grep -ril "[module_name]" March/March*Updates/deploy*.md March/March*Updates/*fix*.md March/March*Updates/*Fix*.md 2>/dev/null | tail -5
```

For each matching file, extract the bug/fix entries related to this module. Present: what broke, why, how it was fixed.

### Step 4: Read recent session history for this module

```bash
# Find session notes mentioning this module in last 5 update folders
for dir in $(ls -d /Users/CSJ/Desktop/fynlaBrain/March/March*Updates 2>/dev/null | sort -V | tail -5); do
  grep -ril "[module_name]" "$dir"/*.md 2>/dev/null
done
```

Present: what was recently worked on in this module, any outstanding items.

### Step 5: Load ALL feedback rules

Same as general mode — read every `feedback_*.md` file. These always apply.

```bash
ls /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_*.md
```

### Step 6: Load design system (if UI work)

If the module involves Vue components or frontend work:

```bash
cat "/Users/CSJ/Desktop/fynlaBrain/Design/fynlaDesignGuide.md" 2>/dev/null | head -100
```

Present: key colours, typography, component patterns.

### Step 7: Check reports for this module

```bash
grep -ril "[module_name]" /Users/CSJ/Desktop/fynlaBrain/Reports/*.md 2>/dev/null
```

If found, extract relevant findings (tech debt, code review issues, security).

### Step 8: Present summary

```markdown
## Vault Context: [Module Name]

### Architecture
[key patterns, relationships, file locations — from architecture doc]

### Current State
[what exists, known limitations — from current state doc]

### Recent Fixes & Bugs
[bugs found, what caused them, how fixed — from deploy/fix docs]

### Session History
[what was recently worked on, outstanding items]

### Rules & Feedback (MUST follow)
[all feedback rules — these are non-negotiable]

### Design System
[colours, typography, patterns — if UI work, otherwise omit]

### Reports
[relevant findings from code reviews, tech debt — if any]
```

## Important

- The vault is at `/Users/CSJ/Desktop/fynlaBrain/` — it is NOT a git repo, just read files directly.
- Feedback rules from `feedback_*.md` are ALWAYS included regardless of mode.
- Architecture docs can be large — trim to the relevant section, don't dump the whole file.
- Recent fixes are critical — they prevent re-introducing bugs that were already found and fixed.
- If a vault file doesn't exist, skip that section silently — don't error.
- This skill is read-only — it never writes to the vault (use session-end/vault-sync for that).
