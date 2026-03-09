# Claude Code Usage Analysis — Agent, Skill & Plugin Recommendations

**Date:** 9 March 2026 (updated)
**Data:** 1,520 prompts over 151 days (8 Oct 2025 — 9 Mar 2026)
**Vault:** fynlaBrain — 470 markdown files, 13 top-level sections, 1,145 git commits

---

## Overview

| Metric | Value |
|--------|-------|
| Total prompts | 1,520 |
| Fynla project | 1,400 (92%) |
| Other projects | 120 (FastingTracker, GymTracker, FPS, fynlaAdvisory) |
| Sessions (Fynla) | 126 (JSONL files) |
| Sessions in vault | 124 (2 missing — current + 1 stub) |
| Avg prompts/session | 19.4 |
| Longest session | 132 prompts |
| Peak month | Feb 2026: 1,007 prompts |

### Vault Statistics (fynlaBrain)

| Section | Files | Description |
|---------|-------|-------------|
| Architecture (v083) | 13 | Full app documentation, 640 KB |
| Architecture (v07) | 12 | Historical reference |
| Git History | 111 | 1,145 commits across 104 daily logs + 6 monthly + 1 index |
| Sessions (Feb) | 73 | Claude Code session transcripts |
| Sessions (March) | 51 | Claude Code session transcripts |
| Update Notes | 110 | Deploy/feature/fix notes |
| Current State | 30 | Module-level documentation |
| Reports | 6 | Security, tech debt, usage analysis |
| Other (Design, Deploy, Revolut, Personas, Plans, App Mapping) | 64 | Supporting documentation |

---

## 1. What You Do Most Frequently

**Ranked by prompt volume:**

| Rank | Activity | Prompts | % |
|------|----------|---------|---|
| 1 | **Git operations** (commit, push, PR, merge) | 204 | 13.4% |
| 2 | **Deployment tracking** (deploy .md files, build, upload lists) | 190 | 12.5% |
| 3 | **Bug fixing** (errors, broken features, corrections) | 162 | 10.7% |
| 4 | **Feature development** (implement, create, build) | 133 | 8.8% |
| 5 | **Payment/Revolut** integration | 82 | 5.4% |
| 6 | **Reading/analysis** (understand code, review plans) | 76 | 5.0% |
| 7 | **Testing** (Pest, browser, manual) | 71 | 4.7% |
| 8 | **Browser testing** (Chrome, screenshots, GIFs) | 52 | 3.4% |
| 9 | **Database seeding** | 52 | 3.4% |
| 10 | **UI/Design/Styling** | 42 | 2.8% |
| 11 | **Documentation/Vault management** | 35 | 2.3% |

**Most repeated exact prompts:**

| Prompt | Count |
|--------|-------|
| `start the dev servers` | 17x |
| `commit this` | 10x |
| `switch to main` | 8x |
| `commit and push` | 5x |
| `comitt, push, pr and merge` | 5x |
| `build correctly` | 5x |
| `reseed the database` | 4x |

**Key insight:** ~25% of all prompts are git + deployment overhead, not actual development work. An additional ~5% is vault/documentation management that should be fully automated.

---

## 2. Recommended Skills (Reusable Workflows)

### `/ship` — Commit, Push, PR & Merge Pipeline

**Evidence:** 30+ prompts are variations of "commit, push, create PR and merge" in 6+ different spellings.

**What it does:**
1. Commits staged changes with auto-generated message
2. Pushes to remote
3. Creates PR with summary
4. Merges to main
5. Switches back to main and pulls

**Estimated savings:** ~200 prompts

---

### `/deploy-notes` — Generate Deployment Tracking File

**Evidence:** 120 prompts involve creating or updating deploy .md files. Manually requested in every session.

**What it does:**
1. Diffs current branch against main
2. Categorises changed files (PHP, Vue, config, migrations)
3. Determines if a rebuild is necessary (any JS/Vue changes)
4. Generates `deploy{Date}.md` with:
   - Files changed (grouped by type)
   - Files to upload
   - Rebuild required (yes/no)
   - Build command to run
   - SSH cache-clear commands
   - Deployment status checkboxes
5. Updates existing deploy file if one exists for today

**Estimated savings:** ~120 prompts

---

### `/session-start` — Session Bootstrap

**Evidence:** Most sessions start with "read claude.md", "start dev servers", "read plan file to get up to speed". Context re-established manually every time.

**What it does:**
1. Seeds database (`php artisan db:seed`)
2. Starts dev servers (`./dev.sh`)
3. Reads current branch and latest deploy/plan files
4. Shows summary: current branch, pending tasks, last session activity

**Estimated savings:** ~50 prompts

---

### `/session-end` — Session Wrap-Up

**Evidence:** Sessions end inconsistently — sometimes commit, sometimes push, sometimes forget deploy notes.

**What it does:**
1. Runs tech-debt session check
2. Commits all changes
3. Updates/creates deploy notes
4. Pushes to remote
5. Optionally creates PR and merges
6. Seeds database as final step

**Estimated savings:** ~40 prompts

---

### `/plan-rewrite` — Module Plan Generation

**Evidence:** Plans rewritten for Retirement, Investment/Savings, Protection, Estate, and Holistic — all following the same format.

**What it does:**
1. Takes module name + context docs as input
2. Explores agent, services, and Vue components for that module
3. Generates plan following standard format:
   - Executive Summary
   - Current State
   - What-If Analysis
   - Conclusion
   - Actions (centralised, with enable/disable toggles)

**Estimated savings:** ~30 prompts per plan rewrite

---

### `/browser-test` — Login & Test Feature

**Evidence:** 52 browser testing prompts, many involving the same login + navigate + test cycle.

**What it does:**
1. Opens Chrome at localhost:8000
2. Logs in with `chris@fynla.org` / `Password1!`
3. Asks user for verification code
4. Navigates to specified feature/page
5. Reports visual state and console errors

**Estimated savings:** ~30 prompts

---

## 3. Recommended Plugins (Standalone Tools)

### `deploy-tracker` Plugin

**Problem:** 120+ prompts managing deployment markdown files manually. Every session involves "update deployX.md", "mark as deployed", "what files changed".

**What it builds:**
- Auto-tracks file changes per branch using git diff
- Generates deploy checklists grouped by file type
- Marks items as uploaded/deployed
- Maintains a deployment log across sessions
- Works with SiteGround or any manual-upload workflow
- Stores deployment history for audit trail

**Scope:** Reusable for any project with manual deployment (not Fynla-specific).

---

### `git-workflow` Plugin

**Problem:** 204 prompts are git operations — mostly the same commit-push-PR-merge pipeline with slight variations.

**What it builds:**
- Configurable git pipeline commands (`/ship`, `/quick-commit`, `/merge-to-main`)
- Branch naming convention enforcement
- Auto-generated commit messages from diff analysis
- PR templates with summary auto-fill
- Branch cleanup after merge

**Scope:** Reusable for any git-based project.

---

### `session-context` Plugin

**Problem:** Every new session requires re-reading plans, CLAUDE.md, and re-establishing what was being worked on. 37 of 72 sessions start with manual context loading.

**What it builds:**
- Persists session state (current branch, current task file, last deploy notes path)
- Auto-loads context when a new session starts
- Detects the "resume previous work" pattern
- Shows a session summary card on startup

**Scope:** Reusable for any long-running project.

---

## 4. Recommended Agents (Autonomous Subagents)

### `build-compliance-agent`

**Evidence:** 29 "build correctly" corrections (2.1% of all prompts). The #1 recurring frustration is Claude not following design system rules — wrong button colours, wrong build process, wrong patterns.

**What it does:**
- Automatically validates every UI change against `fynlaDesignGuide.md` BEFORE presenting results
- Checks:
  - No pink/amber/orange buttons or elements
  - Correct Tailwind tokens (raspberry, horizon, spring, violet, savannah, eggshell)
  - Correct font weights (900 for display/h1, 700 for h2-h5)
  - Correct card patterns and component structure
  - No hardcoded hex values
  - No banned CSS patterns
- Runs as a post-edit hook or review agent on any `.vue` or `.css` file change

**Impact:** Eliminates the "build correctly" correction loop entirely.

---

### `deploy-orchestrator-agent`

**Evidence:** Deployment is the second-highest activity (190 prompts). It's a complex multi-step process spread across manual commands.

**What it does:**
- Orchestrates the full deployment pipeline:
  1. Runs build script (`./deploy/fynla-org/build.sh`)
  2. Generates deploy checklist from git diff
  3. Diffs against last deployment
  4. Validates build output exists and is correct
  5. Provides SSH commands for cache clearing
  6. Updates deploy notes automatically
- Could integrate with the existing `laravel-stack-deployer` agent

**Impact:** Reduces deployment from a 10+ prompt conversation to a single command.

---

### `plan-generator-agent`

**Evidence:** 25+ plans created (visible in `~/.claude/plans/`), all following similar structure. Each plan requires reading current state docs, analysing agent/service code, and generating structured output.

**What it does:**
- Takes a module/feature name + context docs as input
- Explores the codebase (agents, services, controllers, Vue components)
- Generates a structured implementation plan:
  - Executive Summary
  - Current State analysis
  - What-If scenarios
  - Conclusion with recommendations
  - Actionable task list with priorities
- Follows established Fynla plan format

**Impact:** Reduces plan creation from a multi-session effort to a single agent run.

---

### `test-and-seed-agent` (or Hook)

**Evidence:** 52 seeding prompts + constant frustration about forgotten seeds. "Why have you not run the seeders?" is a recurring correction that the user is furious about.

**What it does:**
- Auto-runs `php artisan db:seed` after any backend change, migration, or test run
- Validates preview personas work after seeding
- Could be implemented as a hook rather than a full agent:
  ```json
  {
    "hooks": {
      "PostToolUse": [{
        "matcher": "Bash",
        "pattern": "migrate|seed|test",
        "command": "php artisan db:seed --force"
      }]
    }
  }
  ```

**Impact:** Eliminates the #1 source of user frustration.

---

## 5. Vault Sync — Automated Session-to-Vault Pipeline

**This is the highest-value automation for the fynlaBrain Obsidian vault.** Currently 126 JSONL session files exist in `~/.claude/projects/-Users-CSJ-Desktop-fynla/`, but only 124 are in the vault, and new sessions are never captured automatically. The vault also falls behind on git commit history.

### Problem

| Gap | Impact |
|-----|--------|
| New sessions not written to vault | Knowledge lost — session transcripts only exist as raw JSONL |
| Auto-compact destroys session detail | After compaction, the full conversation is reduced to a summary |
| Git commits not synced to vault | New daily commit logs must be manually regenerated |
| Update notes not synced | New `{Month}{Day}Updates/` folders created in project aren't mirrored |
| Month index pages stale | `Feb Index.md` and `March Index.md` must be updated manually |

### Solution: Three Hooks + One Script

#### The Parser Script: `sync-session-to-vault.sh`

Location: `/Users/CSJ/Desktop/fynla/scripts/sync-session-to-vault.sh`

This script does everything needed to keep the vault current. It accepts the transcript JSONL path as input (provided automatically by hooks) and:

1. **Parses the JSONL** — extracts user/assistant text messages, strips tool_use blocks, thinking blocks, and system-reminder tags
2. **Determines the date** — from the first message timestamp
3. **Determines the session number** — counts existing sessions for that day in the vault
4. **Generates YAML frontmatter** — matching existing format: `date`, `session_id`, `user_messages`, `assistant_messages`, `tags`
5. **Writes the session markdown** to `fynlaBrain/{Month}/{Month}{Day}/Session {N} - {first_prompt}.md`
6. **Updates the month index** (`Feb Index.md` or `March Index.md`) if a new day section is needed
7. **Syncs git commits** — runs `git log` for any new commits since the last vault sync, writes/updates daily commit files in `Git History/`
8. **Syncs update notes** — copies any new `{Month}{Day}Updates/` folders from the project to the vault

The script is **idempotent** — safe to run multiple times on the same session. It checks for existing files by `session_id` in frontmatter before creating duplicates.

#### Hook 1: `PreCompact` (auto) — Capture Before Context Loss

```json
{
  "hooks": {
    "PreCompact": [
      {
        "matcher": "auto",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-session-to-vault.sh \"$CLAUDE_TRANSCRIPT_PATH\" precompact"
          }
        ]
      }
    ]
  }
}
```

**Why:** Auto-compact fires when the context window fills up. This is the **last chance** to capture the full uncompacted session transcript. After compaction, earlier messages are replaced with a summary and detail is permanently lost.

**Hook input:** Claude Code provides `transcript_path` in the hook input, which points to the session's JSONL file (e.g., `~/.claude/projects/-Users-CSJ-Desktop-fynla/414e7d21-....jsonl`).

**Trigger:** Only fires on automatic compaction (`matcher: "auto"`), not manual `/compact` commands, to avoid unnecessary runs.

---

#### Hook 2: `SessionEnd` — Final Sync on Session Close

```json
{
  "hooks": {
    "SessionEnd": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-session-to-vault.sh \"$CLAUDE_TRANSCRIPT_PATH\" sessionend"
          }
        ]
      }
    ]
  }
}
```

**Why:** Captures the session when it ends normally (user types `/exit`, closes terminal, or session times out). This is the primary sync point for sessions that don't hit auto-compact.

**Note:** `SessionEnd` hooks cannot block session termination — they run as cleanup. The script must complete quickly (< 5 seconds) or run asynchronously.

---

#### Hook 3: `Stop` — Incremental Git/Update Sync

```json
{
  "hooks": {
    "Stop": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-commits-to-vault.sh 2>/dev/null &"
          }
        ]
      }
    ]
  }
}
```

**Why:** After each Claude response, check if new git commits were made and sync them to the vault's `Git History/` folder. Runs in the background (`&`) so it doesn't slow down the conversation. This keeps daily commit files up-to-date in real time rather than waiting for session end.

**Separate script:** `sync-commits-to-vault.sh` is a lightweight script that only handles git commit syncing — no JSONL parsing. It:
1. Reads a `.last-commit-sync` marker file to know where it left off
2. Runs `git log` for any new commits since that marker
3. Appends to or creates daily commit files in `Git History/{Month}{Year}/{Month}{Day}.md`
4. Updates the marker file

---

### Combined Hook Configuration

Add to `/Users/CSJ/Desktop/fynla/.claude/settings.json`:

```json
{
  "hooks": {
    "PreCompact": [
      {
        "matcher": "auto",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-session-to-vault.sh \"$CLAUDE_TRANSCRIPT_PATH\" precompact"
          }
        ]
      }
    ],
    "SessionEnd": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-session-to-vault.sh \"$CLAUDE_TRANSCRIPT_PATH\" sessionend"
          }
        ]
      }
    ],
    "Stop": [
      {
        "matcher": "",
        "hooks": [
          {
            "type": "command",
            "command": "bash /Users/CSJ/Desktop/fynla/scripts/sync-commits-to-vault.sh 2>/dev/null &"
          }
        ]
      }
    ]
  }
}
```

### Data Flow

```
Session active
    │
    ├─── [Stop hook] ──→ sync-commits-to-vault.sh ──→ Git History/{Month}{Day}.md
    │    (after each response, background)
    │
    ├─── [PreCompact hook] ──→ sync-session-to-vault.sh ──→ {Month}/{Month}{Day}/Session N.md
    │    (before auto-compact, captures full transcript)
    │
    └─── [SessionEnd hook] ──→ sync-session-to-vault.sh ──→ {Month}/{Month}{Day}/Session N.md
         (session close, final capture)             │
                                                    ├──→ Updates {Month} Index.md
                                                    ├──→ Updates Git History/
                                                    └──→ Syncs {Month}{Day}Updates/
```

### Session Markdown Format (output)

The generated session files match the existing vault format exactly:

```markdown
---
date: 2026-03-09 07:42
session_id: 414e7d21-5c5e-4bd1-8f7d-0e680ebef098
user_messages: 7
assistant_messages: 156
tags:
  - session
  - march-2026
---

# First user prompt truncated to ~60 chars

**Date:** 09 March 2026, 07:42
**Messages:** 7 user / 156 assistant

---

## You

Full text of user message...

## Claude

Full text of assistant response (tool calls shown as *[Used tool: ToolName]*)

## You

Next user message...
```

### Alternative: `/vault-sync` Skill (Manual Trigger)

For on-demand vault syncing when the hooks aren't enough:

**What it does:**
1. Finds all JSONL files in `~/.claude/projects/-Users-CSJ-Desktop-fynla/`
2. Compares against existing vault sessions by `session_id`
3. Writes any missing sessions
4. Syncs all git commits since last sync
5. Copies new update note folders
6. Updates all index pages (Home, Feb Index, March Index, Git History Index)
7. Reports what was added

**When to use:** After bulk session recovery, after clearing old sessions, or as a periodic full-sync to catch anything the hooks missed.

---

## 6. What Belongs in CLAUDE.md (Gaps & Strengthening)

### Already covered well:
- Build commands and deployment process
- Design system rules and colour palette
- Preview user isolation
- Key architectural patterns

### Should be added:

#### Session Protocol
```
## Session Protocol
1. ALWAYS seed database at session start: `php artisan db:seed`
2. When resuming work, read the latest deploy notes and plan files
3. At session end: commit, update deploy notes, push
4. NEVER report work as "done" without testing in browser
5. ALWAYS test with at least one preview persona after UI changes
```

#### Git Workflow Standard
```
## Git Workflow
- Branch naming: feature name in camelCase (e.g., retirementPlan, estateRewrite)
- After completing work: commit -> push -> create PR -> merge to main -> switch to main -> pull
- Always update deploy notes before pushing
- Never work on a merged branch — check branch status first
```

#### Plan Document Format
```
## Plan Document Standard
All module plans follow this structure:
1. Executive Summary
2. Current State
3. What-If Analysis
4. Conclusion
5. Actions (centralised format with enable/disable toggles)
```

#### Verification Before Completion
```
## Verification Rule
NEVER claim a task is complete without:
1. Running the dev server and visually checking the change
2. Confirming no console errors
3. Testing with at least one preview persona
4. Running `php artisan db:seed` after any backend change
```

#### Vault Awareness
```
## fynlaBrain Vault
The Obsidian vault at ~/Desktop/fynlaBrain is the project's knowledge base.
- Sessions, git history, update notes, and architecture docs are stored there
- Vault sync hooks handle automatic updates (PreCompact, SessionEnd, Stop)
- When creating new update note folders ({Month}{Day}Updates/), they will be
  auto-synced to the vault at session end
- Architecture docs live in Architecture/v083/ — update when major changes ship
```

#### Design System Enforcement
The current design system rules are documented but violated 29 times across sessions. Consider converting from documentation to a **pre-commit hook** or **build-compliance agent** that programmatically validates changes against `fynlaDesignGuide.md`.

---

## 7. Frustration Analysis

| Frustration Source | Occurrences | Root Cause |
|-------------------|-------------|------------|
| "Build correctly" / design violations | 29 | Claude ignoring fynlaDesignGuide.md |
| "Why no seeders" | 12 | Claude skipping db:seed |
| "Read claude.md" | 10 | Claude not reading project instructions |
| "Not how we build" | 8 | Wrong component patterns or colours |
| "Wrong login details" | 4 | Claude using wrong test credentials |
| "Reporting done without testing" | 3 | No verification before claiming complete |

**246 prompts (16.2%)** contain frustration indicators — corrections, reverts, or repeated instructions. Nearly all are preventable with the skills, agents, and hooks recommended above.

---

## 8. Priority Implementation Order

| Priority | Change | Type | Prompts Saved | Frustration Eliminated |
|----------|--------|------|---------------|----------------------|
| 1 | **Vault sync hooks** (PreCompact + SessionEnd + Stop) | Hook | — | Critical (knowledge preservation) |
| 2 | `/ship` skill | Skill | ~200 | Low |
| 3 | `/deploy-notes` skill | Skill | ~120 | Low |
| 4 | `build-compliance-agent` | Agent | ~30 | High (29 corrections) |
| 5 | Auto-seed hook | Hook | ~50 | High (12 corrections) |
| 6 | `/session-start` skill | Skill | ~50 | Medium |
| 7 | CLAUDE.md session protocol + vault awareness | Docs | ~20 | Medium |
| 8 | `/session-end` skill | Skill | ~40 | Low |
| 9 | `/vault-sync` skill (manual full-sync) | Skill | ~10 | Low |
| 10 | `deploy-orchestrator-agent` | Agent | ~30 | Low |
| 11 | `/browser-test` skill | Skill | ~30 | Medium |
| 12 | `/plan-rewrite` skill | Skill | ~30 | Low |

**Total estimated savings:** ~610 prompts (~40% of current usage), with vault sync as the #1 priority for knowledge preservation and the highest-frustration items eliminated next.

---

## 9. Implementation Checklist

### Phase 1: Vault Sync (immediate)
- [ ] Write `scripts/sync-session-to-vault.sh` (JSONL parser + vault writer)
- [ ] Write `scripts/sync-commits-to-vault.sh` (git log → vault commit files)
- [ ] Add PreCompact, SessionEnd, and Stop hooks to `.claude/settings.json`
- [ ] Test with current session — verify output matches existing vault format
- [ ] Verify idempotency — run twice, confirm no duplicates

### Phase 2: Core Workflow Skills
- [ ] Create `/ship` skill
- [ ] Create `/deploy-notes` skill
- [ ] Add auto-seed PostToolUse hook
- [ ] Create `/session-start` skill
- [ ] Create `/session-end` skill

### Phase 3: Agents & Compliance
- [ ] Build `build-compliance-agent`
- [ ] Build `deploy-orchestrator-agent`
- [ ] Create `/vault-sync` skill for manual full-sync
- [ ] Create `/browser-test` skill
- [ ] Create `/plan-rewrite` skill

### Phase 4: CLAUDE.md Updates
- [ ] Add session protocol section
- [ ] Add git workflow standard
- [ ] Add vault awareness section
- [ ] Add verification rule
