---
name: session-end
description: Wrap up a development session. Commits all changes to local and remote, syncs the fynlaBrain Obsidian vault, generates tech debt/TODO handover for the next session. Use when the user says "end session", "wrap up", "finish up", "session end", "that's it for today", or when a significant block of work is complete.
disable-model-invocation: true
---

# Session End — Post-Session Wrap-Up

Systematically close out a Fynla development session. Ensures nothing is forgotten, everything is committed, and the next session has full context.

---

## Step 1: Gather Session Changes

```bash
# All changes (staged + unstaged + untracked)
git status
git diff --stat HEAD
git diff --name-only HEAD 2>/dev/null
git diff --name-only --cached 2>/dev/null
git ls-files --others --exclude-standard 2>/dev/null
```

If there are no changes at all, skip to Step 5 (vault sync) and report a clean session.

```bash
# Today's commits (to summarise what was done)
git log --since="midnight" --oneline
```

---

## Step 2: Tech Debt Check

If files were changed, run the `/tech-debt-session` skill to audit changed files for:
- Duplicate code
- Dead/redundant code
- Convention violations (design system, tax hardcoding, acronyms)
- Complexity issues
- Security concerns

Report findings to the user. Do NOT auto-fix — let them decide.

---

## Step 3: Commit ALL Changes (MANDATORY)

**This is not optional. Every session ends with a clean working tree.**

### 3a: Stage and commit

```bash
# Check what needs committing
git status

# Stage relevant files (exclude .env, secrets, node_modules, vendor)
git add <specific-files>

# Generate a descriptive commit message from the changes
git commit -m "$(cat <<'EOF'
Descriptive commit message here.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

If there are multiple logical groups of changes, create separate commits for each.

### 3b: Push to remote (MANDATORY)

```bash
git push -u origin $(git rev-parse --abbrev-ref HEAD)
```

If the branch hasn't been pushed yet, this creates it on the remote. If it already exists, this updates it.

### 3c: Verify clean state

```bash
git status
# Must show: "nothing to commit, working tree clean"
```

If not clean, investigate and commit remaining files. Do NOT leave uncommitted changes.

---

## Step 4: Generate Deploy Notes (if applicable)

Only if PHP or Vue files changed and haven't already been deployed this session.

### Categorise Changed Files

**CRITICAL: ALWAYS use `git diff` to list files. NEVER list from memory — you WILL miss files.**

```bash
# List ALL changed files vs main
git diff --name-only origin/main...HEAD 2>/dev/null || git diff --name-only HEAD~5...HEAD

# Check if composer.json changed (needs composer install on server)
git diff origin/main...HEAD -- composer.json composer.lock 2>/dev/null | head -3

# Check if config/ changed (needs config:clear)
git diff --name-only origin/main...HEAD -- config/ 2>/dev/null

# Check if routes/ changed (needs route:clear)
git diff --name-only origin/main...HEAD -- routes/ 2>/dev/null
```

Sort into categories:

| Category | Pattern | Action |
|----------|---------|--------|
| PHP Backend | `app/**/*.php`, `config/*.php`, `routes/*.php` | Upload via SiteGround File Manager |
| Frontend | `resources/js/**`, `resources/css/**` | Rebuild with `./deploy/fynla-org/build.sh` then upload `public/build/` |
| Migrations | `database/migrations/*.php` | Upload + SSH `php artisan migrate --force` |
| Seeders | `database/seeders/*.php` | Upload + SSH `php artisan db:seed --class=XSeeder --force` |
| Deploy Config | `deploy/**`, `.htaccess` | Upload if changed |
| Composer | `composer.json`, `composer.lock` | Upload + SSH `composer install --no-dev --optimize-autoloader` |

### Pre-Merge Check (if merging a branch)

```bash
BASE=$(git merge-base main HEAD)
MAIN_FILES=$(git diff --name-only $BASE..origin/main -- '*.php' '*.vue' '*.js')
BRANCH_FILES=$(git diff --name-only $BASE..HEAD -- '*.php' '*.vue' '*.js')
CONFLICTS=$(comm -12 <(echo "$MAIN_FILES" | sort) <(echo "$BRANCH_FILES" | sort))
if [ -n "$CONFLICTS" ]; then
  echo "WARNING: These files changed on BOTH branches — verify after merge:"
  echo "$CONFLICTS"
fi
```

### SSH Commands Template

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Step 5: Full Vault Sync (MANDATORY)

**Invoke the `/vault-sync` skill.** This is not optional — it handles ALL vault synchronisation:

- Codebase metrics update (CLAUDE.md counts)
- Copy all update notes from `[Month]/[Month][DD]Updates/` to vault
- Git history (daily commit log + monthly index)
- Month Index update (session entries + update note wikilinks)
- Home.md update (commit counts, version)
- Design guide sync (`fynlaDesignGuide.md` → `fynlaBrain/Design/`)
- Formatting audit (frontmatter, wikilinks, orphaned files)
- Cross-link integrity check
- Memory file audit

**Do NOT manually sync individual files.** The vault-sync skill is comprehensive and idempotent. Run it once and it handles everything.

If the user has specified a target folder override (e.g., "use today's folder not tomorrow's"), pass that context when invoking the skill.

---

## Step 6: Generate CSJTODO.md Handover

This is the handover document for the next session. `session-start` reads it first.

### 6a: Determine target folder

Use the `TARGET_FOLDER` calculated in Step 5a:
- Before 7pm → today's folder (next session is same day)
- After 7pm → tomorrow's folder (next session is next day)

### 6b: Gather outstanding items

```bash
# Tech debt findings from Step 2 that weren't fixed
# Incomplete tasks from any task list used this session
# Known bugs or issues discovered but not addressed
# Items deferred with TODO/FIXME/HACK in changed files
git diff --name-only HEAD~10 -- '*.php' '*.vue' '*.js' 2>/dev/null | xargs grep -n "TODO\|FIXME\|HACK\|XXX" 2>/dev/null

# Failing tests (if tests were run)
# Any items the user mentioned but weren't started
```

### 6c: Read existing CSJTODO.md

```bash
cat CSJTODO.md 2>/dev/null
```

If it exists:
- **DO NOT overwrite** — update it
- Mark items completed this session as `[x]`
- Keep uncompleted items
- Add new outstanding items
- Update the "Context for Next Session" section

### 6d: Write/update CSJTODO.md

Structure:

```markdown
# CSJTODO — Fynla

*Last updated: [date] — session [N]*
*Previous session: [date]*

---

## Session [N] ([date]) — [brief description]

### Completed This Session
- [x] [items done]

### NOT Done — Outstanding
- [ ] [items remaining]

### Context for Next Session
[2-3 sentences on where work left off, what to start with]

---

## Outstanding — Tech Debt Deferred
- [ ] [carried items]

## Known Issues
- [ ] [bugs found but not fixed]

## Deploy Status
[What's deployed, what's pending]
```

### 6e: Save to three locations

```bash
# 1. Project root (session-start reads this first)
# Already at CSJTODO.md

# 2. Target update folder in project
mkdir -p "$TARGET_MONTH/$TARGET_FOLDER"
cp CSJTODO.md "$TARGET_MONTH/$TARGET_FOLDER/CSJTODO.md"

# 3. Vault
mkdir -p "/Users/CSJ/Desktop/fynlaBrain/$TARGET_MONTH/$TARGET_FOLDER"
cp CSJTODO.md "/Users/CSJ/Desktop/fynlaBrain/$TARGET_MONTH/$TARGET_FOLDER/CSJTODO.md"
```

### 6f: Final commit for CSJTODO + vault sync docs

If the CSJTODO or any deploy notes were created/updated, commit and push them:

```bash
git add CSJTODO.md ${MONTH_NAME}/${MONTH_NAME}*Updates/*.md
git commit -m "$(cat <<'EOF'
docs: session end — CSJTODO handover + update notes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push
```

---

## Step 7: Session Summary

Present a clean wrap-up:

```markdown
## Session Complete

**Date:** [today]
**Branch:** [current branch]
**Git:** All changes committed and pushed to origin
**Working tree:** Clean

### What was done this session
- [bullet summary from git log]

### Tech Debt
- [N] issues found ([Y] critical, [Z] warnings) — [fixed/deferred]

### Deploy Status
- [deployed / deploy notes at [Month]/[Month][DD]Updates/deploy.md / nothing to deploy]

### Vault Sync
- [N] files synced to fynlaBrain
- Git history updated ([N] commits today)
- [Month] Index updated

### Outstanding for Next Session
- [top items from CSJTODO.md, or "Clean slate"]

### CSJTODO saved to
- `CSJTODO.md` (project root)
- `[Month]/[TARGET_FOLDER]/CSJTODO.md`
- `/Users/CSJ/Desktop/fynlaBrain/[Month]/[TARGET_FOLDER]/CSJTODO.md`
```

---

## Critical Rules

- **ALWAYS commit and push** — no uncommitted changes left behind. Ever.
- **ALWAYS push to remote** — local-only commits get lost if the machine dies.
- **ALWAYS sync the vault** — session index, git history, update notes. This is the project knowledge base.
- **ALWAYS generate CSJTODO** — even if it's "clean slate". The next session needs to know.
- **After 7pm rule** — CSJTODO goes in tomorrow's folder because the next session is tomorrow.
- Do NOT run `migrate:fresh` or `migrate:refresh`.
- Do NOT skip the tech debt check.
- Match Obsidian vault format: `[[wikilinks]]`, YAML frontmatter, established file structure.
- The vault is NOT a git repo — write files directly.
- Copy files from fynla to fynlaBrain — both should have the same content.
- Deploy notes must come from `git diff`, never memory.
