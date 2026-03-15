---
name: session-end
description: Wrap up a development session. Runs tech debt check on changed files, reseeds the database, generates deploy notes, updates the Obsidian vault session index, and optionally commits and pushes. Use when the user says "end session", "wrap up", "finish up", "session end", "that's it for today", or when a significant block of work is complete.
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

If there are no changes at all, skip to Step 6 (final reseed) and report a clean session.

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

## Step 4: Update Obsidian Vault Session Index

Determine today's date and session count. Read the current March Index:

```bash
cat "/Users/CSJ/Desktop/fynlaBrain/March/March Index.md"
```

Check if today already has entries. If not, add a new date section. Append the session entry.

**Format to follow (match existing pattern):**

Under `## Sessions`, add or append to today's date:

```markdown
### March[DD] (X sessions)

- [[Session X - brief description of what was done]]
```

Under `## Update Notes`, add if deploy notes were generated:

```markdown
### March[DD]Updates

- [[deploy]]
- [[other-notes-if-any]]
```

Also update the project copy if one exists at `/Users/CSJ/Desktop/fynla/March/`.

## Step 5: Commit, Push & PR (Automatic)

Always commit, push, and create a PR at the end of every session if there are changes.

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

### Create PR
```bash
gh pr create --title "Short descriptive title" --body "$(cat <<'EOF'
## Summary
- Brief description of changes

## Test plan
- [ ] Testing steps

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

Report the PR URL to the user.

## Step 6: Final Database Reseed (CRITICAL - NEVER SKIP)

Always reseed at the end of every session, regardless of what was done:

```bash
php artisan db:seed
```

This ensures the next session (or any manual testing) starts with clean, complete data.

## Step 7: Session Summary

Present a clean wrap-up to the user:

```markdown
## Session Complete

**Changes:** X files modified, Y files created
**Tech debt:** X issues found (Y critical, Z warnings)
**Deploy notes:** Generated at March/MarchXXUpdates/deploy.md
**Vault index:** Updated March Index.md
**Git:** Committed and pushed / Uncommitted changes remain
**Database:** Reseeded successfully

**What was done this session:**
- [bullet summary of work from git log/diff]

**Next steps:**
- [any pending items, tech debt to address, deploy actions needed]
```

## Important

- ALWAYS reseed at the end. No exceptions.
- ALWAYS commit, push, and create a PR if there are changes — this is automatic, not optional.
- Do NOT run `migrate:fresh` or `migrate:refresh`.
- Do NOT skip the tech debt check — it catches issues before they accumulate.
- If deploy notes were generated, remind the user to upload files if deploying.
- Match the exact Obsidian vault index format — use `[[wikilinks]]` for cross-references.
