---
name: session-end
description: Wrap up a Fynla development session so the next one starts cleanly. Asks whether this is a context-clear or end-of-day wrap, commits and pushes all work, runs the tech-debt session audit, invokes the vault-sync skill, writes a dated handover file (`handover-YYYY-MM-DD-session-N.md`) into the correct date folder for session-start to pick up on resume, and mirrors session state into `planning-with-files` docs (`task_plan.md` / `findings.md` / `progress.md`) if they exist or if the vault sync failed — so the next session-start still has continuity via the plugin fallback channel. Use when the user says "end session", "wrap up", "finish up", "session end", "that's it for today", "I'm clearing context", "/clear in a moment", or when a significant block of work is complete. Pair of session-start — they share the handover file format, folder convention, and planning-with-files fallback contract.
disable-model-invocation: false
---

# Session End — Post-Session Wrap-Up

Close out a Fynla development session cleanly so the next one — whether tomorrow morning or two minutes from now after a context clear — can pick up without losing state.

This skill is the counterpart to `session-start`. They share a convention: session-end writes `handover-YYYY-MM-DD-session-N.md` into a dated `April*Updates/` folder; session-start reads the most recent handover from today's folder (or yesterday's if none exists today). Keep that contract intact.

---

## Configuration (adjust per repo)

Mirror of the session-start config — keep these in sync when copying the skill to another Fynla variant.

```bash
VAULT_ROOT="/Users/CSJ/Desktop/fynlaBrain"
MEMORY_DIR="/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory"
UPDATES_PARENT="$(date +%B)"             # e.g. "April" — this repo's convention
```

Update-folder naming: `${MONTH_NAME}/${MONTH_NAME}${UNPADDED_DAY}Updates/` (e.g. `April/April20Updates/`, not `April/April20Updates/`). Day is unpadded — `date +%-d` on BSD, `date +%-d` on GNU. The repo and the vault use the same convention so paths mirror 1:1.

---

## Phase 1: Ask — context-clear or end-of-day?

This is the first thing the skill does. Don't guess — the two modes produce handovers with different scope and go to different folders. Use a blocking question:

> "Is this an **end-of-day** wrap or a **context-clear** (continuing same session after `/clear`)?"

- **end-of-day** → handover goes in **tomorrow's** `April*Updates/` folder; comprehensive scope (full day's arc, plans for tomorrow, deploy status).
- **context-clear** → handover goes in **today's** `April*Updates/` folder; tight scope ("we just finished X, next step is Y"). File name gets a `-clear` suffix so session-start can tell them apart at a glance.

Capture the answer into `MODE` (`eod` or `clear`).

---

## Phase 2: Compute the target date and folder

```bash
if [ "$MODE" = "eod" ]; then
  TARGET_DATE=$(date -v+1d +%Y-%m-%d)   # tomorrow
  TARGET_DAY=$(date -v+1d +%-d)
  TARGET_MONTH=$(date -v+1d +%B)
  SUFFIX=""
else
  TARGET_DATE=$(date +%Y-%m-%d)         # today
  TARGET_DAY=$(date +%-d)
  TARGET_MONTH=$(date +%B)
  SUFFIX="-clear"
fi

TARGET_FOLDER="${TARGET_MONTH}/${TARGET_MONTH}${TARGET_DAY}Updates"
VAULT_TARGET_FOLDER="${VAULT_ROOT}/${TARGET_FOLDER}"
REPO_TARGET_FOLDER="${TARGET_FOLDER}"

mkdir -p "$REPO_TARGET_FOLDER" "$VAULT_TARGET_FOLDER"

# Session number — count existing handover files in the target folder and +1
existing=$(ls "$REPO_TARGET_FOLDER"/handover-*.md 2>/dev/null | wc -l | tr -d ' ')
SESSION_N=$((existing + 1))

HANDOVER_NAME="handover-${TARGET_DATE}-session-${SESSION_N}${SUFFIX}.md"
echo "Will write: $REPO_TARGET_FOLDER/$HANDOVER_NAME"
```

If `date -v+1d` doesn't work on the user's shell (GNU vs BSD date), fall back to `date -d "tomorrow" +...`.

---

## Phase 3: Gather session changes

```bash
git status --short
git diff --stat HEAD
git diff --name-only HEAD
git diff --name-only --cached
git ls-files --others --exclude-standard

# Today's commits — to summarise what was done
git log --since="midnight" --format='%h %s%n%b%n---'
```

If there are no changes AND no commits today, skip to Phase 7 (vault-sync) and note a clean session in the handover.

---

## Phase 4: Tech-debt audit

If any files changed this session, invoke the `tech-debt-session` skill to audit them for duplicate code, dead code, convention drift (design system, tax hardcoding, acronyms), and complexity. Surface findings to the user — do NOT auto-fix. The handover will record deferred items.

---

## Phase 5: Commit and push everything

**No session ends with uncommitted work. Ever.** Uncommitted work that survives to the next session is how bugs come back from the dead — files get edited twice, one copy gets committed, the other is lost.

### 5a. Stage and commit

```bash
git status
# Stage specific files — avoid git add -A so .env, credentials, etc. don't sneak in
git add <file> <file> ...

git commit -m "$(cat <<'EOF'
<descriptive subject, imperative mood>

<body: what changed, why, anything subtle>

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Group logically — if the session touched two separate concerns, make two commits. Match the repo's existing commit-message style (check `git log -5` first).

### 5b. Push

```bash
BRANCH=$(git rev-parse --abbrev-ref HEAD)
git push -u origin "$BRANCH"
```

If the push fails on HTTP 400 with a big pack, run `git gc` and retry with `git -c http.postBuffer=524288000 push`.

### 5c. Verify clean

```bash
git status  # must be "nothing to commit, working tree clean"
```

If it's not clean, investigate — don't paper over. A file that resists committing is usually either (a) ignored by `.gitignore` intentionally, (b) a permissions issue, or (c) something you don't want in git (secrets). Handle the real cause.

---

## Phase 6: Deploy notes (only if code shipped)

If `*.php` or `*.vue` files changed and haven't been deployed this session, capture what the user would need to ship. Build the list from `git diff`, never from memory.

```bash
git diff --name-only origin/main...HEAD -- '*.php' '*.vue' '*.js' 'database/**' 'config/**' 'routes/**'
```

Sort into categories (PHP backend / frontend / migrations / seeders / deploy config / composer) and note:

- Which files need uploading
- Whether a Vite rebuild is needed (`./deploy/fynla-org/build.sh`)
- Whether `composer install` / `migrate --force` / `cache:clear` are needed on the server

Write the deploy note into the target update folder as `deploy-${TARGET_DATE}.md`. The handover references it.

---

## Phase 7: Invoke vault-sync skill

**Use the `Skill` tool to run `vault-sync`.** It handles: session transcript capture, codebase metrics, git history, Month Index, Home.md, design guide mirror, formatting audits, memory-file audit. It's idempotent — run it once per session, regardless of mode.

Pass the target folder override if the mode is `eod`, so vault-sync knows session notes belong to tomorrow's bucket. The skill reads conversation context, so just noting "end-of-day for $TARGET_DATE" is enough.

Do NOT manually sync individual files — vault-sync is comprehensive.

---

## Phase 8: Write the handover file

This is the artifact session-start will read next. It's a snapshot of "where we left off" — immutable once written. Use one of two templates depending on `MODE`.

### 8a. End-of-day template

```markdown
---
type: handover
mode: end-of-day
date: ${TARGET_DATE}
session: ${SESSION_N}
branch: <current branch>
previous_session: <previous date + session, from last handover file found>
---

# Handover — ${TARGET_DATE}, Session ${SESSION_N}

## Where we left off
<2-3 sentences. What the user and Claude were working on at session close. What's the immediate context for tomorrow morning.>

## What shipped today
<bulleted list drawn from `git log --since="midnight"` — commit subjects, not hashes>

## What's in flight (NOT done)
<items the user explicitly deferred, or tasks that were started but not finished. Be honest — if something was attempted and failed, say so.>

## Deploy status
<one of:
- "Nothing to deploy — all work was docs/refactor/local"
- "Deployed to dev (csjones.co/fynla) — notes at $TARGET_FOLDER/deploy-${TARGET_DATE}.md"
- "Ready to deploy but NOT deployed — see deploy note">

## Tech debt found this session
<items flagged by tech-debt-session audit that weren't fixed. Link to the offending file/line.>

## Known issues / blockers
<anything that's broken right now. Login flow failing? An error in the log? A test that's red? Be specific.>

## Rules reinforced this session
<any feedback the user gave that was saved to memory — summarise in one line each with the memory file path. Helps the next Claude know which rules are freshly-painful.>

## Next session should
<3-5 concrete bullets. "First, pull main. Then run the failing test in `tests/Feature/FooTest.php` — the fix is probably in `app/Services/BarService.php:142`."
Be specific enough that the next Claude doesn't have to guess.>

## Context hints
- Active branch type: <design | mixed | mainline>
- Behind origin/main by: <N commits, from `git rev-list`>
- Uncommitted: <"none, working tree clean" — if anything else, you haven't finished this skill>
- Last commit: <hash + subject>
```

### 8b. Context-clear template (tighter)

```markdown
---
type: handover
mode: context-clear
date: ${TARGET_DATE}
session: ${SESSION_N}
branch: <current branch>
---

# Context Clear Handover — ${TARGET_DATE}, Session ${SESSION_N}

## Immediate state
<One sentence. What were you literally doing at the moment of clear? "About to commit the TFSA validator fix." "Waiting for user to pick between approach A and B for goal rollover.">

## The thread
<3-5 bullets — what the conversation was working toward. Don't summarise the whole project, just the arc that got interrupted.>

## Files touched (uncommitted or recently committed)
<from `git status` + `git log --since="1 hour ago"`>

## What the next Claude needs to know
<the one or two non-obvious things. "The user wants British spelling in UI copy." "We decided against approach X because of reason Y — don't re-propose it.">

## Pick up from here
<Literally the next action. "Run `./vendor/bin/pest tests/Feature/TfsaTest.php` — it was failing on line 47 before the clear.">
```

### 8c. Write to both locations

```bash
# Repo
echo "$HANDOVER_CONTENT" > "$REPO_TARGET_FOLDER/$HANDOVER_NAME"

# Vault (mirror — vault is NOT a git repo, write files directly)
cp "$REPO_TARGET_FOLDER/$HANDOVER_NAME" "$VAULT_TARGET_FOLDER/$HANDOVER_NAME"
```

### 8d. Mirror to planning-with-files (second channel)

The `planning-with-files` plugin keeps `task_plan.md` / `findings.md` / `progress.md` at the repo root as a second memory channel — phase-level state, session log, and accumulated research. Session-start reads these as a fallback when the vault is unreachable and as supplementary context when it isn't. This phase keeps them in sync so the fallback actually works.

Two code paths depending on what already exists:

**Path A — the three files exist (user is actively using planning-with-files):**

Append a session entry to `progress.md`, update phase status in `task_plan.md` if any phases moved, and add new findings to `findings.md`. The plugin's own hooks will nudge during the next session; we just need to keep the files current.

```bash
if [ -f progress.md ]; then
  cat >> progress.md <<EOF

## ${TARGET_DATE} — session ${SESSION_N} (${MODE})
- Handover: [[${HANDOVER_NAME%.md}]]
- Branch: $(git rev-parse --abbrev-ref HEAD)
- Commits this session: $(git log --since="midnight" --oneline | wc -l | tr -d ' ')
- Status: $(git status --short | wc -l | tr -d ' ') uncommitted
- Next: <one-line what the next session should start with>
EOF
fi
```

For `task_plan.md`, re-read it and tick off any phase the session actually completed. Do NOT rewrite it wholesale — the user's phase structure is load-bearing. Only change status markers (`[ ]` → `[x]`) and add new phases if the session added work. If the session explicitly finished a phase, note that in `progress.md` and update the phase marker.

For `findings.md`, append any non-trivial discoveries from the session — patterns, gotchas, rejected approaches. Skip if nothing new was learned. Keep it terse — the plugin's guidance is the 2-action rule: findings go to disk before they're lost.

**Path B — the three files DON'T exist, AND the vault sync in Phase 7 failed or wrote no files:**

We're in fallback mode — the vault wasn't reachable this session, so the handover may be the only written record. Seed the three planning-with-files docs from the handover so the next session has continuity via the plugin even without a working vault. Structure:

```bash
if ! [ -f task_plan.md ] && [ "$VAULT_SYNC_FAILED" = "true" ]; then
  cat > task_plan.md <<EOF
# Task Plan — seeded by session-end fallback

> Created by session-end because vault-sync failed. Run \`/planning-with-files:plan\` to formalise.

## Current phase
<from handover's "Where we left off">

## Phases
- [ ] <from handover's "What's in flight">

## Decisions log
<from handover's "Rules reinforced">
EOF

  cat > findings.md <<EOF
# Findings — seeded ${TARGET_DATE}
<from handover's "Known issues / blockers" and "Tech debt found">
EOF

  cat > progress.md <<EOF
# Progress — seeded ${TARGET_DATE}

## ${TARGET_DATE} — session ${SESSION_N}
<paste the handover's "What shipped today" list>
EOF
fi
```

**Path C — three files don't exist AND vault sync succeeded:**

Do nothing. The user isn't using planning-with-files for this project; creating the files unsolicited would clutter the repo root. If they want it, they'll invoke `/planning-with-files:plan` themselves.

Whichever path runs, commit the updated (or new) files in Phase 10 alongside the handover.

---

## Phase 9: Update the rolling CSJTODO.md

`CSJTODO.md` at the repo root is the running "what's open right now" document. The handover is a point-in-time snapshot; CSJTODO is the living state. Both exist for different reasons — don't collapse them.

```bash
cat CSJTODO.md 2>/dev/null
```

If it exists:
- Tick items completed this session as `[x]`
- Keep uncompleted items
- Add newly-discovered outstanding items
- Update the "Last updated" stamp

If it doesn't exist, create it. Structure:

```markdown
# CSJTODO — Fynla

*Last updated: ${TODAY} — ${MODE} wrap, session ${SESSION_N}*

## Outstanding
- [ ] <items>

## Tech debt deferred
- [ ] <from this session's audit>

## Known issues
- [ ] <bugs found, not fixed>

## Deploy status
<one-line summary>
```

Keep it terse. CSJTODO.md is a scan-in-two-seconds doc, not an essay.

---

## Phase 10: Final commit of handover and docs

The handover, CSJTODO, and any deploy notes generated in this phase need to be committed and pushed too — otherwise the next session won't find them.

```bash
git add "$REPO_TARGET_FOLDER/$HANDOVER_NAME" CSJTODO.md "$REPO_TARGET_FOLDER"/deploy-*.md 2>/dev/null
# Include planning-with-files docs if Phase 8d updated them
for f in task_plan.md findings.md progress.md; do
  [ -f "$f" ] && git diff --quiet "$f" 2>/dev/null || git add "$f" 2>/dev/null
done

git commit -m "$(cat <<EOF
docs(session): ${MODE} handover ${TARGET_DATE}-session-${SESSION_N}

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push
```

---

## Phase 11: Session summary

Present one clean wrap-up to the user. Be concrete, not verbose.

```markdown
## Session Complete — ${MODE}

**Date:** ${TODAY}
**Target:** ${TARGET_DATE} (${MODE})
**Branch:** <branch>
**Git:** committed + pushed (<N> commits this session)
**Working tree:** clean
**Vault:** synced

### Handover written
- `${REPO_TARGET_FOLDER}/${HANDOVER_NAME}`
- `${VAULT_TARGET_FOLDER}/${HANDOVER_NAME}` (vault mirror)

### Outstanding for next session
- <top 3 items from the handover>

### Deploy status
<one line>

**Next:** run `session-start` when you're ready to pick up. It will find this handover automatically.
```

---

## Critical rules

- **Always ask the mode question first.** Don't infer from time of day — the user may be wrapping at 10am because they're moving to another repo, or clearing context at 11pm. Ask.
- **Always commit AND push.** Local-only commits are one bad machine-reboot from dust.
- **Always run vault-sync.** The vault is the project knowledge base — skip it and context erodes.
- **Always write the handover.** Even a clean session gets one: "nothing changed today, branch at commit X, nothing deployed." The next session's existence assumes a handover exists.
- **Handover date = target-session date**, not today's date (for end-of-day). This matches how session-start scans folders.
- **Deploy notes come from `git diff`, never memory.** Claude will miss files if it lists from memory.
- **Don't run `migrate:fresh` / `migrate:refresh`** — they wipe data.
- **Don't skip the tech-debt check** unless no files changed.
