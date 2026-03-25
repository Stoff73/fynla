# Vault Gateway System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ensure every Claude instance has access to accumulated project knowledge from the fynlaBrain vault before making decisions.

**Architecture:** A `vault-context` skill reads targeted vault sections based on module/topic. Session-start auto-loads general context. CLAUDE.md contains a vault reference map. A pre-edit hook reminds about vault context. Agent dispatch rules ensure sub-agents get vault context.

**Tech Stack:** Bash (skill), Python (hook), Markdown (CLAUDE.md, skill files)

**Spec:** `docs/superpowers/specs/2026-03-25-vault-gateway-design.md`

---

### Task 1: Create the `vault-context` skill

**Files:**
- Create: `.claude/skills/vault-context/SKILL.md`

- [ ] **Step 1: Create the skill directory**

```bash
mkdir -p .claude/skills/vault-context
```

- [ ] **Step 2: Write the skill file**

Create `.claude/skills/vault-context/SKILL.md` with:
- Frontmatter: name `vault-context`, description triggers on `/vault-context`, `disable-model-invocation: true`
- Module map constant (the full module→vault-doc mapping from the spec)
- `general` mode: reads all feedback rules, last 3 session notes from vault March Index, most recent CSJTODO from vault, recent reports
- Module mode: reads Architecture doc (grep for module section), Current State doc, scans deploy/fix files for module mentions, last 3 sessions mentioning module, all feedback rules, design system if UI work
- Output format as defined in spec
- Instructions to trim architecture docs to the relevant section (not the whole 09-MODULES file)
- Scan logic: `grep -ril "[module]" /Users/CSJ/Desktop/fynlaBrain/March/March*Updates/deploy*.md` for recent fixes

The skill should handle these invocations:
- `/vault-context general` — session-level context
- `/vault-context investment` — module-specific
- `/vault-context deployment` — topic-specific
- `/vault-context` with no argument — defaults to `general`

- [ ] **Step 3: Test the skill**

Run `/vault-context general` in a conversation and verify it outputs feedback rules, recent sessions, and TODOs.
Run `/vault-context investment` and verify it outputs architecture, current state, fixes, and rules.

- [ ] **Step 4: Commit**

```bash
git add .claude/skills/vault-context/SKILL.md
git commit -m "feat: create vault-context skill for loading vault knowledge"
```

---

### Task 2: Enhance session-start skill with vault general context

**Files:**
- Modify: `.claude/skills/session-start/SKILL.md`

- [ ] **Step 1: Read the current session-start skill**

Read `.claude/skills/session-start/SKILL.md` to understand current structure.

- [ ] **Step 2: Add Step 1b after the existing Step 1**

Insert a new section between Step 1 (Read Memory, TODO & Context) and Step 2 (Git Sync):

```markdown
## Step 1b: Load Vault Context

Load accumulated knowledge from the fynlaBrain vault:

### Read recent session history from vault
```bash
# Get the 3 most recent session folders
ls -d /Users/CSJ/Desktop/fynlaBrain/March/March*Updates 2>/dev/null | sort -t'h' -k2 -n | tail -3
```

For each folder, read any session summary, deploy notes, or TODO files. Present key items.

### Read vault TODO (may be newer than repo TODO)
```bash
# Find the most recent CSJTODO.md in the vault
find /Users/CSJ/Desktop/fynlaBrain/March -name "CSJTODO.md" -type f 2>/dev/null | sort | tail -1
```

If it exists and differs from repo TODO.md, present both and note the difference.

### Load all feedback rules
```bash
ls /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_*.md
```

Read each feedback file and present a summary of ALL active rules. These are non-negotiable.

### Check recent reports
```bash
find /Users/CSJ/Desktop/fynlaBrain/Reports -name "*.md" -mtime -7 2>/dev/null
```

If any reports exist from the last 7 days, summarise key findings.

### Present vault context summary
```markdown
## Vault Context Loaded

**Feedback Rules (MUST follow):**
- [list each rule from feedback_*.md files]

**Recent Sessions:**
- [summary of last 3 sessions from vault]

**Outstanding from Vault:**
- [items from vault CSJTODO if different from repo TODO]

**Recent Reports:**
- [findings from last 7 days, or "None"]
```
```

- [ ] **Step 3: Verify the skill reads correctly**

Check that the modified skill file has valid markdown and the bash commands work:

```bash
ls -d /Users/CSJ/Desktop/fynlaBrain/March/March*Updates 2>/dev/null | sort -t'h' -k2 -n | tail -3
find /Users/CSJ/Desktop/fynlaBrain/Reports -name "*.md" -mtime -7 2>/dev/null
ls /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_*.md
```

- [ ] **Step 4: Commit**

```bash
git add .claude/skills/session-start/SKILL.md
git commit -m "feat: add vault context loading to session-start skill"
```

---

### Task 3: Add vault reference map and agent dispatch protocol to CLAUDE.md

**Files:**
- Modify: `CLAUDE.md` (insert before `## Deployment` section, around line 161)

- [ ] **Step 1: Read the current CLAUDE.md around the insertion point**

Read `CLAUDE.md` lines 155-170 to find the exact insertion point before `## Deployment`.

- [ ] **Step 2: Add the vault reference map section**

Insert before `## Deployment`:

```markdown
## Vault Reference (fynlaBrain)

The project knowledge base is at `/Users/CSJ/Desktop/fynlaBrain/` (693 Obsidian docs). Before working on any module, load its context with `/vault-context [module]`.

| Module | Architecture Doc | Current State Doc |
|--------|-----------------|-------------------|
| Investment | `v083/09-MODULES.md` | `Investment.md` |
| Estate | `v083/09-MODULES.md` | `EstatePlanning.md` |
| Protection | `v083/09-MODULES.md` | `Protection.md` |
| Retirement | `v083/09-MODULES.md` | `Retirement.md` |
| Savings | `v083/09-MODULES.md` | `Savings.md` |
| Goals | `v083/09-MODULES.md` | `GoalsLifeEvents.md` |
| Property | `v083/09-MODULES.md` | `Property.md` |
| Auth/Security | `v083/03-AUTH-SECURITY.md` | `Auth.md` |
| Database | `v083/02-DATABASE.md` | — |
| Frontend | `v083/05-FRONTEND.md` | — |
| Backend | `v083/04-BACKEND.md` | — |
| Deployment | `v083/11-CONFIG-DEPLOY.md` | `DeploymentBuild.md` |
| AI Chat | `v083/10-NEW-SYSTEMS.md` | — |
| Tax/Financial | `v083/08-FINANCIAL-CALCS.md` | `UKTaxes.md` |
| Payments | `v083/10-NEW-SYSTEMS.md` | `PaymentSubscription.md` |

### Sub-Agent Vault Context (MANDATORY)

When dispatching ANY agent to work on module code:
1. Load `/vault-context [module]` first (or read the relevant vault docs inline)
2. Include in the agent prompt: architecture patterns, recent fixes, feedback rules
3. Include ALL `feedback_*.md` rules — these are non-negotiable for every agent

Never dispatch an agent with just "fix X" or "build Y". Always include:
- What module this is in and its patterns
- Recent bugs/fixes in this area (from vault deploy/fix docs)
- The feedback rules that apply
```

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "feat: add vault reference map and agent dispatch protocol to CLAUDE.md"
```

---

### Task 4: Create pre-edit vault reminder hook

**Files:**
- Create: `.claude/hooks/vault_reminder_hook.sh`
- Modify: `.claude/settings.json`

- [ ] **Step 1: Create the hook script**

Create `.claude/hooks/vault_reminder_hook.sh`:

```bash
#!/bin/bash
# Pre-edit hook: reminds Claude to check vault context when editing module files
filepath="$CLAUDE_FILE_PATH"

# Map file paths to modules
module=""
case "$filepath" in
  *Services/Investment*|*components/Investment*|*views/Investment*|*InvestmentList*|*InvestmentDetail*)
    module="investment" ;;
  *Services/Estate*|*components/Estate*|*EstateDashboard*|*IHT*|*Trust*|*Gift*)
    module="estate" ;;
  *Services/Protection*|*components/Protection*|*ProtectionDashboard*|*PolicyForm*)
    module="protection" ;;
  *Services/Retirement*|*components/Retirement*|*Pension*|*StatePension*)
    module="retirement" ;;
  *Services/Savings*|*components/Savings*|*SavingsDashboard*|*CashOverview*)
    module="savings" ;;
  *Services/Goals*|*components/Goals*|*GoalsDashboard*|*LifeEvent*)
    module="goals" ;;
  *components/NetWorth/Property*|*PropertyForm*|*PropertyCard*|*PropertyList*)
    module="property" ;;
  *deploy/*|*Deploy*|*.htaccess)
    module="deployment" ;;
  *components/Admin*|*AdminController*|*AdminPanel*)
    module="admin" ;;
  *HasAiChat*|*AiChat*|*XaiTool*|*XaiClient*|*CoordinatingAgent*)
    module="ai-chat" ;;
  *fynlaDesignGuide*|*designSystem*)
    module="design-system" ;;
esac

if [ -n "$module" ]; then
  echo "VAULT: Editing $module module. Ensure context loaded (/vault-context $module)."
fi
```

- [ ] **Step 2: Make it executable**

```bash
chmod +x .claude/hooks/vault_reminder_hook.sh
```

- [ ] **Step 3: Add the hook to settings.json**

Add to the existing `PostToolUse` hook array for `Edit|Write` matcher:

```json
{
  "type": "command",
  "command": "bash /Users/CSJ/Desktop/fynla/.claude/hooks/vault_reminder_hook.sh"
}
```

This goes after the existing security_reminder_hook in the PostToolUse array.

- [ ] **Step 4: Test the hook**

Edit a test file in an investment path and verify the reminder appears:

```bash
CLAUDE_FILE_PATH="app/Services/Investment/PortfolioAnalyzer.php" bash .claude/hooks/vault_reminder_hook.sh
# Expected: "VAULT: Editing investment module. Ensure context loaded (/vault-context investment)."

CLAUDE_FILE_PATH="resources/js/components/Estate/GiftForm.vue" bash .claude/hooks/vault_reminder_hook.sh
# Expected: "VAULT: Editing estate module. Ensure context loaded (/vault-context estate)."

CLAUDE_FILE_PATH="app/Models/User.php" bash .claude/hooks/vault_reminder_hook.sh
# Expected: (no output — not a module-specific file)
```

- [ ] **Step 5: Commit**

```bash
git add .claude/hooks/vault_reminder_hook.sh .claude/settings.json
git commit -m "feat: add pre-edit vault reminder hook for module files"
```

---

### Task 5: Final integration test and push

**Files:**
- No new files — verification only

- [ ] **Step 1: Verify all components exist**

```bash
ls -la .claude/skills/vault-context/SKILL.md
ls -la .claude/hooks/vault_reminder_hook.sh
grep "vault-context" CLAUDE.md
grep "vault_reminder" .claude/settings.json
grep "vault" .claude/skills/session-start/SKILL.md
```

All 5 should return matches.

- [ ] **Step 2: Test vault-context skill invocation**

In a new conversation, invoke `/vault-context general` and verify output includes:
- Feedback rules summary
- Recent session notes
- Outstanding TODOs

Then invoke `/vault-context investment` and verify output includes:
- Architecture section from v083/09-MODULES
- Current State from Investment.md
- Recent fixes mentioning investment
- Feedback rules

- [ ] **Step 3: Test hook fires correctly**

Edit a Protection file and verify the vault reminder appears in the hook output.

- [ ] **Step 4: Push to remote**

```bash
git push origin main
```

- [ ] **Step 5: Update liveAITest.md with vault gateway status**

Add a note to `March/March25Updates/liveAITest.md` that the vault gateway system is implemented.
