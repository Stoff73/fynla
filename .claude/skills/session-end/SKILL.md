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

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
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

## Step 5: Sync fynlaBrain Obsidian Vault

The vault is at `/Users/CSJ/Desktop/fynlaBrain/` (NOT a git repo — write files directly).

### 5a: Determine the target folder

```bash
HOUR=$(date +%H)
TODAY=$(date +%d)

if [ "$HOUR" -ge 19 ]; then
  # After 7pm — save to NEXT day's folder
  TARGET_DAY=$(date -v+1d +%d 2>/dev/null || date -d "+1 day" +%d)
else
  # Before 7pm — save to today's folder
  TARGET_DAY=$TODAY
fi

TARGET_FOLDER="March${TARGET_DAY}Updates"
```

This determines where the CSJTODO handover goes. Session notes and vault updates always go in today's folder.

### 5b: Copy update notes to vault

```bash
TODAY_FOLDER="March$(date +%d)Updates"
mkdir -p "/Users/CSJ/Desktop/fynlaBrain/March/$TODAY_FOLDER"

for file in /Users/CSJ/Desktop/fynla/March/$TODAY_FOLDER/*.md; do
  [ -f "$file" ] || continue
  filename=$(basename "$file")
  vault_path="/Users/CSJ/Desktop/fynlaBrain/March/$TODAY_FOLDER/$filename"
  if [ ! -f "$vault_path" ] || ! diff -q "$file" "$vault_path" > /dev/null 2>&1; then
    cp "$file" "$vault_path"
    echo "SYNCED: $TODAY_FOLDER/$filename"
  fi
done
```

Also sync subdirectories:

```bash
find "/Users/CSJ/Desktop/fynla/March/$TODAY_FOLDER" -type d -mindepth 1 | while read subdir; do
  rel_path="${subdir#/Users/CSJ/Desktop/fynla/}"
  vault_path="/Users/CSJ/Desktop/fynlaBrain/$rel_path"
  mkdir -p "$vault_path"
  for file in "$subdir"/*.md; do
    [ -f "$file" ] || continue
    filename=$(basename "$file")
    if [ ! -f "$vault_path/$filename" ] || ! diff -q "$file" "$vault_path/$filename" > /dev/null 2>&1; then
      cp "$file" "$vault_path/$filename"
      echo "  SYNCED: $filename"
    fi
  done
done
```

### 5c: Update git history

Create or update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar$(date +%d).md` with today's commits.

**Format:**
```markdown
---
tags:
  - git-history
  - mar-2026
date: YYYY-MM-DD
commits: N
---

# Commits — DD March 2026

Back to [[Git History/Mar2026/Mar2026 Commits|March 2026 Commits]]

**N commits** — breakdown by type

---

| Time | Hash | Type | Message |
|------|------|------|---------|
| HH:MM | `abcdef12` | + | feat: description |
```

Type codes: `+` feat, `~` fix, `D` docs, `^` update/refactor, `T` test, `C` chore, `-` merge/other.

Also update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar2026 Commits.md`:
- Update total commit count
- Update type breakdown counts
- Add/update today's row in the Daily Logs table

### 5d: Update March Index

Read `/Users/CSJ/Desktop/fynlaBrain/March/March Index.md` and:

1. **Sessions section** — Add or update today's date entry with session summaries
2. **Update Notes section** — Add wikilinks to any new files in today's update folder
3. **Git history link** — Update the commit count in the header

### 5e: Update Home.md

Update `/Users/CSJ/Desktop/fynlaBrain/Home.md`:
- Git history total commit count
- March 2026 row in the Git History table
- Any new reports

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
mkdir -p "March/$TARGET_FOLDER"
cp CSJTODO.md "March/$TARGET_FOLDER/CSJTODO.md"

# 3. Vault
mkdir -p "/Users/CSJ/Desktop/fynlaBrain/March/$TARGET_FOLDER"
cp CSJTODO.md "/Users/CSJ/Desktop/fynlaBrain/March/$TARGET_FOLDER/CSJTODO.md"
```

### 6f: Final commit for CSJTODO + vault sync docs

If the CSJTODO or any deploy notes were created/updated, commit and push them:

```bash
git add CSJTODO.md March/March*Updates/*.md
git commit -m "$(cat <<'EOF'
docs: session end — CSJTODO handover + update notes

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
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
- [deployed / deploy notes at March/MarchXXUpdates/deploy.md / nothing to deploy]

### Vault Sync
- [N] files synced to fynlaBrain
- Git history updated ([N] commits today)
- March Index updated

### Outstanding for Next Session
- [top items from CSJTODO.md, or "Clean slate"]

### CSJTODO saved to
- `CSJTODO.md` (project root)
- `March/[TARGET_FOLDER]/CSJTODO.md`
- `/Users/CSJ/Desktop/fynlaBrain/March/[TARGET_FOLDER]/CSJTODO.md`
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
