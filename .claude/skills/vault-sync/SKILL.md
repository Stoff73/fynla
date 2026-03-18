---
name: vault-sync
description: Sync project documentation to the fynlaBrain Obsidian vault, update version numbers across CLAUDE.md/README/vault, ensure git history is current, and audit memory files. Use when the user says "sync vault", "update vault", "update fynlaBrain", "sync docs", "update versions", "update README", "update CLAUDE.md metrics", or at session end after significant work. Also use proactively after any feature that changes component/service/controller/model counts.
disable-model-invocation: true
---

# Vault Sync — Documentation & Version Maintenance

Keep all project documentation, version numbers, and the fynlaBrain Obsidian vault in sync with the actual codebase.

## Step 1: Count Current Codebase Metrics

Get the actual counts from the codebase:

```bash
# Vue Components
find resources/js/components resources/js/views resources/js/mobile -name "*.vue" 2>/dev/null | wc -l

# PHP Services
find app/Services -name "*.php" 2>/dev/null | wc -l

# Controllers
find app/Http/Controllers -name "*.php" 2>/dev/null | wc -l

# Models
find app/Models -name "*.php" 2>/dev/null | wc -l

# API Endpoints (approximate)
php artisan route:list --json 2>/dev/null | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "N/A"

# Vuex Store Modules
find resources/js/store/modules -name "*.js" 2>/dev/null | wc -l

# Agents
find app/Agents -name "*Agent.php" 2>/dev/null | wc -l

# Test Cases
./vendor/bin/pest --no-coverage 2>/dev/null | tail -5 | grep -oP '\d+ passed' || echo "N/A"

# Services directories
find app/Services -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l

# API Services (frontend)
find resources/js/services -name "*.js" 2>/dev/null | wc -l

# Factories
find database/factories -name "*.php" 2>/dev/null | wc -l

# Migrations
find database/migrations -name "*.php" 2>/dev/null | wc -l

# Database tables
php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::getAllTables()->count();" 2>/dev/null || echo "N/A"
```

Store these counts for comparison.

## Step 2: Update CLAUDE.md Metrics

Read the current CLAUDE.md and compare the metrics table against actual counts:

```bash
head -20 CLAUDE.md
```

If any counts have changed, update the table:

```markdown
| Metric | Count |
|--------|-------|
| Vue Components | [actual] |
| PHP Services | [actual] |
| Controllers | [actual] |
| Models | [actual] |
| Vuex Stores | [actual] |
| Agents | [actual] |
```

Also check the version number matches the latest:

```bash
grep "Version" CLAUDE.md
```

If a new version has been deployed, update it.

## Step 3: Update README.md

Read the current README.md Quick Stats table and update if any counts changed:

```bash
head -30 README.md
```

Update the Quick Stats table to match current codebase counts. Also check the test badge count.

## Step 4: Sync Update Notes to fynlaBrain

### 4a: Copy new/changed March update files

```bash
# Determine today's date folder
TODAY=$(date +%d)
SRC="/Users/CSJ/Desktop/fynla/March/March${TODAY}Updates"
DST="/Users/CSJ/Desktop/fynlaBrain/March/March${TODAY}Updates"

if [ -d "$SRC" ]; then
  mkdir -p "$DST"
  for file in "$SRC"/*.md; do
    if [ -f "$file" ]; then
      filename=$(basename "$file")
      # Copy if new or changed
      if [ ! -f "$DST/$filename" ] || ! diff -q "$file" "$DST/$filename" > /dev/null 2>&1; then
        cp "$file" "$DST/$filename"
        echo "Synced: $filename"
      fi
    fi
  done
fi
```

### 4b: Sync any other changed docs

Check for docs in other March update folders that may have been modified:

```bash
# Find recently modified docs in March/
find /Users/CSJ/Desktop/fynla/March -name "*.md" -newer /Users/CSJ/Desktop/fynla/CLAUDE.md -mtime -1 2>/dev/null
```

Copy any that are newer than what's in fynlaBrain.

## Step 5: Update Git History in fynlaBrain

### 5a: Create/update today's git history file

```bash
TODAY=$(date +%d)
HISTORY_FILE="/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar${TODAY}.md"
```

Get today's commits and create/update the daily log following the established format (see `Mar07.md` as template):

- YAML frontmatter with tags, date, commit count
- Commit table with Time, Hash, Type, Message columns
- Type codes: `+` feat, `~` fix, `D` docs, `R` refactor/review, `S` style, `C` chore, `T` test, `-` merge

### 5b: Update monthly commits index

Update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar2026 Commits.md`:
- Total commit count
- Today's row in the Daily Logs table

### 5c: Update March session index

Update `/Users/CSJ/Desktop/fynlaBrain/March/March Index.md`:
- Session entries under today's date
- Update Notes section with links to new files
- Use `[[wikilinks]]` matching filenames without `.md`

### 5d: Update Home.md

Update `/Users/CSJ/Desktop/fynlaBrain/Home.md`:
- Version number if changed
- Git commit count for March
- Any new reports added to Reports section

## Step 6: Audit Memory Files

Check that memory files are current and not stale:

```bash
ls -lt /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/
```

### 6a: Check for stale memories

Read each memory file and verify it's still accurate:
- Project memories with dates — are they still relevant?
- Feedback memories — are they still applicable or has the pattern been fixed?
- Reference memories — do the external references still exist?

### 6b: Check for missing memories

After this session, should any new memories be saved?
- New patterns learned
- New preferences expressed by the user
- New external references discovered
- New project state changes

### 6c: Update MEMORY.md index

Ensure `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/MEMORY.md` has entries for all memory files and no orphaned references.

## Step 7: Version Consistency Check

Verify the version number is consistent across all locations:

```bash
# CLAUDE.md
grep "Version" /Users/CSJ/Desktop/fynla/CLAUDE.md

# README.md (if version is mentioned)
grep -i "version" /Users/CSJ/Desktop/fynla/README.md | head -3

# package.json
grep '"version"' /Users/CSJ/Desktop/fynla/package.json 2>/dev/null

# Home.md in vault
grep "Version" /Users/CSJ/Desktop/fynlaBrain/Home.md

# Version page (if exists)
find /Users/CSJ/Desktop/fynla/resources -name "*version*" -o -name "*Version*" 2>/dev/null | head -5
```

If any are out of sync, update them all to match the latest deployed version.

## Step 8: Summary Report

```markdown
## Vault Sync Complete

**Metrics updated:** CLAUDE.md [updated/no change] | README.md [updated/no change]
**Version:** v[X.Y.Z] — consistent across [N] locations

**fynlaBrain synced:**
- Update notes: [N] files synced to March[DD]Updates
- Git history: Mar[DD].md [created/updated] ([N] commits)
- March Index: [updated/no change]
- Home.md: [updated/no change]

**Memory audit:**
- [N] memory files checked
- [N] stale memories found [details]
- [N] new memories saved

**Counts changed:**
| Metric | Was | Now |
|--------|-----|-----|
| [only rows where count changed] |
```

## Important

- The fynlaBrain vault is at `/Users/CSJ/Desktop/fynlaBrain/` — it is NOT a git repo, just write files directly.
- Use Obsidian format: YAML frontmatter with tags, `[[wikilinks]]`, MOC index files.
- Never invent version numbers — only use the version that's actually deployed.
- Match the existing file structure and naming patterns in fynlaBrain.
- This skill is safe to run multiple times — it's idempotent (only updates what changed).
- If README.md or CLAUDE.md metrics haven't changed, don't touch them.
