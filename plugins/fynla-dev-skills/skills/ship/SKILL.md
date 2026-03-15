---
name: ship
description: Atomic git pipeline — commit, push, and optionally create a PR or merge. Handles staging, commit message generation, branch management, and PR creation in one command. Use when the user says "ship", "ship it", "commit and push", "push and PR", "push pr and merge", "send it", or any variation of committing + pushing + PR creation.
disable-model-invocation: true
---

# Ship — Atomic Git Pipeline

One command to commit, push, and optionally create a PR or merge. Eliminates the repetitive "commit this, push it, create a PR, merge it" workflow.

## Step 1: Assess Current State

```bash
git status
git rev-parse --abbrev-ref HEAD
git diff --stat HEAD
git diff --name-only HEAD 2>/dev/null
git diff --name-only --cached 2>/dev/null
git ls-files --others --exclude-standard 2>/dev/null
```

If there are no changes (staged, unstaged, or untracked), check if there are unpushed commits:

```bash
git log origin/$(git rev-parse --abbrev-ref HEAD)..HEAD --oneline 2>/dev/null
```

If nothing to commit AND nothing to push, tell the user and stop.

## Step 2: Stage Files

Stage all relevant files. **Exclude** sensitive and generated files:

**Always exclude:**
- `.env`, `.env.production`, `.env.local`
- `credentials.json`, `*.key`, `*.pem`
- `node_modules/`, `vendor/`
- `storage/logs/`
- `public/hot`

**Stage by reading the changed files:**

```bash
# Stage modified and new files (but not deleted)
git add [specific files]
```

Prefer adding specific files by name. Only use `git add -A` if the user explicitly asks for it.

If there are files the user likely does NOT want committed (temp files, debug logs, large binaries), flag them before staging:

> "I see these files — should I include them?"
> - `tech-debt-report.md` (generated report)
> - `storage/logs/laravel.log` (log file)

## Step 3: Generate Commit Message

Analyse all staged changes and draft a commit message:

**Rules:**
- Use conventional commit format: `type: description`
- Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`
- If changes span multiple types, use the dominant one
- Keep the first line under 72 characters
- Add a body (2-3 sentences) if the change is substantial
- End with `Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>`

**Examples:**
```
feat: add pension contribution optimiser with tax relief calculator
fix: correct IHT calculation for married couples with RNRB taper
docs: update CLAUDE.md metrics and add session management skills
refactor: extract coverage gap logic into dedicated analyser service
style: apply pint formatting to estate module services
```

**For multi-scope changes:**
```
feat: implement protection plan with coverage gap analysis and recommendations

Add ProtectionPlanBuilder service generating per-policy recommendations,
coverage gap visualisation, and what-if scenarios. Includes action
definitions seeder and admin-configurable thresholds.

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
```

## Step 4: Commit

```bash
git commit -m "$(cat <<'EOF'
[generated commit message]

Co-Authored-By: Claude Opus 4.6 <noreply@anthropic.com>
EOF
)"
```

If the commit fails due to pre-commit hooks (e.g., pint formatting), fix the issue and create a NEW commit (never `--amend`).

## Step 5: Push

Determine if the branch tracks a remote:

```bash
git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null
```

If no upstream, push with `-u`:
```bash
git push -u origin $(git rev-parse --abbrev-ref HEAD)
```

If upstream exists:
```bash
git push
```

## Step 6: Create PR (if applicable)

**When to create a PR:**
- If the current branch is NOT `main` — always offer to create a PR
- If the user said "PR", "pull request", or "ship and PR"
- If the user said "push pr and merge" — create PR AND merge

**When to skip PR:**
- If on `main` and the user just said "ship" or "commit and push"
- If the user explicitly said "no PR"

**PR creation:**

```bash
gh pr create --title "[PR title]" --body "$(cat <<'EOF'
## Summary
- [1-3 bullet points from commit analysis]

## Changed Files
- [count] PHP files
- [count] Vue components
- [count] other files

## Test Plan
- [ ] Run `./vendor/bin/pest` — all tests pass
- [ ] Verify on localhost with preview persona
- [ ] Check changed feature works as expected

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

**PR title rules:**
- Under 70 characters
- Matches the commit message type + description
- No period at the end

## Step 7: Merge (if requested)

Only merge if the user explicitly asked (e.g., "push pr and merge", "ship and merge", "merge it").

```bash
gh pr merge --merge --delete-branch
```

After merge, switch back to main and pull:
```bash
git checkout main && git pull
```

## Step 8: Report

Show what was done:

```
## Shipped

**Commit:** [hash] [message]
**Branch:** [branch name]
**Pushed:** origin/[branch]
**PR:** [URL] (if created)
**Merged:** Yes/No

**Files shipped:**
- [count] PHP, [count] Vue, [count] other
```

## Quick Modes

The user may invoke with shorthand. Handle these patterns:

| User Says | Action |
|-----------|--------|
| "ship" | Commit + push (PR if not on main) |
| "ship it" | Same as "ship" |
| "commit and push" | Commit + push only, no PR |
| "push" | Push only (no new commit if clean) |
| "push and PR" | Push + create PR |
| "push pr and merge" | Push + create PR + merge + switch to main |
| "ship and merge" | Commit + push + PR + merge |
| "just commit" | Commit only, no push |

## Important

- NEVER force push (`--force`) unless the user explicitly requests it
- NEVER amend existing commits — always create new ones
- NEVER skip hooks (`--no-verify`) unless the user explicitly requests it
- NEVER push to main without confirming if there's a branch protection bypass warning
- NEVER commit `.env`, credentials, or secrets — warn and exclude
- ALWAYS use HEREDOC format for commit messages (ensures proper formatting)
- ALWAYS show the commit message to the user before committing if there are >10 changed files
- If on main and there are substantial changes, suggest creating a feature branch first
