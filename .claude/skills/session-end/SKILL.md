---
name: session-end
description: Wrap up a development session. Runs tech debt check on changed files, generates deploy notes, updates the fynlaBrain Obsidian vault (session index, git history, update notes, reports), and commits and pushes. Use when the user says "end session", "wrap up", "finish up", "session end", "that's it for today", or when a significant block of work is complete.
disable-model-invocation: true
---

# Session End - Post-Session Wrap-Up

Systematically close out a Fynla development session. Ensures nothing is forgotten.

## Step 1: Gather Session Changes

```bash
# All changes (staged + unstaged + untracked)
git status
git diff --stat HEAD
git diff --name-only HEAD 2>/dev/null
git diff --name-only --cached 2>/dev/null
git ls-files --others --exclude-standard 2>/dev/null
```

If there are no changes at all, skip to Step 6 (vault update) and report a clean session.

```bash
# Today's commits (to summarise what was done)
git log --since="midnight" --oneline
```

## Step 2: Tech Debt Check

If files were changed, run the `/tech-debt-session` skill to audit changed files for:
- Duplicate code
- Dead/redundant code
- Convention violations (design system, tax hardcoding, acronyms)
- Complexity issues
- Security concerns

Report findings to the user. Do NOT auto-fix — let them decide.

## Step 3: Generate Deploy Notes (if applicable)

If PHP or Vue files changed, generate deployment documentation.

### Categorise Changed Files

```bash
git diff --name-only origin/main...HEAD 2>/dev/null || git diff --name-only HEAD~5...HEAD
```

Sort into categories:

| Category | Pattern | Action |
|----------|---------|--------|
| PHP Backend | `app/**/*.php`, `config/*.php`, `routes/*.php` | Upload via SiteGround File Manager |
| Frontend | `resources/js/**`, `resources/css/**` | Rebuild with `./deploy/fynla-org/build.sh` then upload `public/build/` |
| Migrations | `database/migrations/*.php` | Upload + SSH `php artisan migrate --force` |
| Seeders | `database/seeders/*.php` | Upload + SSH `php artisan db:seed --class=XSeeder --force` |
| Deploy Config | `deploy/**`, `.htaccess` | Upload if changed |

### Generate Deploy Checklist

Create a deploy notes file listing:
- All files to upload with server paths (`~/www/fynla.org/public_html/...`)
- Whether frontend build is needed
- SSH commands to run post-upload
- Warnings (migrations, config changes, new dependencies)

Save to both locations:
1. Project: `March/March[DD]Updates/deploy.md`
2. Vault: `/Users/CSJ/Desktop/fynlaBrain/March/March[DD]Updates/deploy.md`

Create the directories if they don't exist.

### SSH Commands Template

Always include:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Step 4: Commit, Push (Automatic)

Always commit and push at the end of every session if there are changes.

### Commit
1. Stage relevant files (exclude .env, secrets, node_modules, vendor)
2. Generate a descriptive commit message from the changes
3. Commit

```bash
git add <specific-files>
git commit -m "$(cat <<'EOF'
Descriptive commit message here.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

### Push
```bash
git push -u origin $(git branch --show-current)
```

## Step 5: Update fynlaBrain Obsidian Vault

The fynlaBrain vault is at `/Users/CSJ/Desktop/fynlaBrain/`. It is an Obsidian vault (NOT a git repo). All files use Obsidian-flavoured Markdown with YAML frontmatter and `[[wikilinks]]`.

### 5a: Copy Update Notes to Vault

Copy any new/modified files from today's `March/March[DD]Updates/` folder to the vault:

```bash
# Create directory if needed
mkdir -p "/Users/CSJ/Desktop/fynlaBrain/March/March[DD]Updates"

# Copy new/changed files
for file in /Users/CSJ/Desktop/fynla/March/March[DD]Updates/*.md; do
  cp "$file" "/Users/CSJ/Desktop/fynlaBrain/March/March[DD]Updates/"
done
```

### 5b: Update Git History

Create or update the daily git history file at `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar[DD].md`.

**Format** (match existing files like `Mar07.md`):

```markdown
---
tags:
  - git-history
  - mar-2026
date: 2026-03-[DD]
commits: [COUNT]
---

# Commits — [DD] March 2026

Back to [[Git History/Mar2026/Mar2026 Commits|March 2026 Commits]]

**[COUNT] commits** — [type breakdown]

---

| Time | Hash | Type | Message |
|------|------|------|---------|
| HH:MM | `abcdef12` | + | feat: description |
| HH:MM | `abcdef13` | ~ | fix: description |
```

Type codes: `+` feat, `~` fix, `D` docs, `R` refactor/review, `S` style, `C` chore, `T` test, `-` merge/other.

Also update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar2026 Commits.md`:
- Update the total commit count
- Add/update the row for today's date in the Daily Logs table

### 5c: Update March Session Index

Read `/Users/CSJ/Desktop/fynlaBrain/March/March Index.md` and:

1. **Sessions section** — Add or update today's date entry under `## Sessions`:

```markdown
### March[DD] (X sessions)

- [[Session X - brief description of what was done in this session]]
```

Include the session ID (first 8 chars) for reference. Describe the actual work done, not just "development session".

2. **Update Notes section** — Add links to any new files in `March[DD]Updates/`:

```markdown
### March[DD]Updates

- [[deploy]] — Deployment guide: X files, rebuild needed
- [[other-file]] — Description
```

Use `[[wikilinks]]` matching the filename without `.md` extension.

### 5d: Update Home.md (if needed)

Update `/Users/CSJ/Desktop/fynlaBrain/Home.md` if:
- The git commit count changed (update the March row in the Git History table)
- New reports were generated (add to Reports section)
- New major features or architecture changes occurred

### 5e: Copy Any New Reports

If significant reports were generated (code reviews, test reports, analysis), copy them to `/Users/CSJ/Desktop/fynlaBrain/Reports/` with Obsidian frontmatter:

```markdown
---
tags:
  - report
  - [topic]
date: 2026-03-[DD]
---
```

## Step 6: Generate TODO.md

Create or update `TODO.md` in the project root if there are ANY outstanding items. This file is the handover document for the next session.

### 6a: Gather outstanding items

Collect from all sources:

```bash
# 1. Tech debt findings from Step 2 that weren't fixed
# 2. Incomplete tasks from any task list used this session
# 3. Known bugs or issues discovered but not addressed
# 4. Items deferred with "TODO", "FIXME", "HACK" in changed files
git diff --name-only HEAD~10 -- '*.php' '*.vue' '*.js' 2>/dev/null | xargs grep -n "TODO\|FIXME\|HACK\|XXX" 2>/dev/null

# 5. Failing tests (if tests were run)
# 6. Checkpoint failures from /plan-and-build that weren't resolved
# 7. Any items the user mentioned but weren't started
```

### 6b: Check for previous TODO.md

```bash
cat TODO.md 2>/dev/null
```

If a previous `TODO.md` exists, check which items were completed this session (mark them done) and which are still outstanding (carry forward).

### 6c: Write TODO.md

```markdown
# TODO — Fynla

*Last updated: [today's date] by session [session-id first 8 chars]*
*Previous session: [date of last TODO.md update]*

## Carried Forward (from previous session)

- [ ] [item from previous TODO.md that wasn't done]
- [x] [item that WAS done this session — mark complete]

## Outstanding from This Session

### Implementation Incomplete
- [ ] [feature/task that was planned but not finished]
- [ ] [sub-task that was deferred]

### Tech Debt
- [ ] [tech debt finding from audit — file:line — description]
- [ ] [convention violation found but not fixed]

### Known Issues
- [ ] [bug discovered during testing but not fixed]
- [ ] [edge case identified but not handled]

### Deferred Items
- [ ] [item explicitly deferred — reason]

## Context for Next Session

[2-3 sentences describing where work left off, what state the codebase is in,
and what the next session should start with. Include any gotchas or warnings.]

## Files to Review

[List any files that were changed but not fully tested, or that need
a second look in the next session.]
```

### 6d: Save to both locations

1. Project root: `TODO.md`
2. Vault: `/Users/CSJ/Desktop/fynlaBrain/March/March[DD]Updates/TODO.md`

### 6e: If nothing outstanding

If there are genuinely NO outstanding items (all tasks done, no tech debt, no bugs):

```markdown
# TODO — Fynla

*Last updated: [today's date]*

No outstanding items. Clean slate for next session.

## Context for Next Session

[Brief summary of what was accomplished and what's ready for the next piece of work.]
```

This is still valuable — it confirms to the next session that the slate is clean.

## Step 7: Session Summary

Present a clean wrap-up to the user:

```markdown
## Session Complete

**Changes:** X files modified, Y files created
**Tech debt:** X issues found (Y critical, Z warnings)
**Deploy notes:** Generated at March/MarchXXUpdates/deploy.md
**Git:** Committed and pushed to origin/[branch]
**Vault:** Updated — session index, git history, update notes
**TODO:** [N] outstanding items written to TODO.md / Clean slate

**What was done this session:**
- [bullet summary of work from git log/diff]

**Outstanding for next session:**
- [top 3 items from TODO.md, or "None"]
```

## Important

- ALWAYS commit and push if there are changes — this is automatic, not optional.
- ALWAYS update the fynlaBrain vault — session index, git history, and update notes. This is the project knowledge base.
- Do NOT run `migrate:fresh` or `migrate:refresh`.
- Do NOT skip the tech debt check — it catches issues before they accumulate.
- If deploy notes were generated, remind the user to upload files if deploying.
- Match the exact Obsidian vault format — use `[[wikilinks]]`, YAML frontmatter with tags, and the established file structure.
- The vault is NOT a git repo — just write files directly, no git commands needed for fynlaBrain.
- Copy files from fynla to fynlaBrain — do not create separate versions. Both should have the same content.
