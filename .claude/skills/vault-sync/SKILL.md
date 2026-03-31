---
name: vault-sync
description: Sync project documentation to the fynlaBrain Obsidian vault, update version numbers, git history, March Index, Home.md, and audit all vault formatting/connections. Use when the user says "sync vault", "update vault", "update fynlaBrain", "sync docs", or at session end after significant work.
disable-model-invocation: true
---

# Vault Sync — Full Documentation & Integrity Check

Sync all project documentation to the fynlaBrain Obsidian vault, then verify every file is correctly formatted, connected, and up to date.

**Vault location:** `/Users/CSJ/Desktop/fynlaBrain/` (NOT a git repo — write files directly)
**Obsidian config:** `/Users/CSJ/Desktop/fynlaBrain/.obsidian/` (stock config, no custom plugins)
**Source docs:** `/Users/CSJ/Desktop/fynla/March/March[DD]Updates/`

---

## Phase 1: Codebase Metrics

Count current codebase metrics and compare with CLAUDE.md:

```bash
echo "Vue Components: $(find resources/js/components resources/js/views resources/js/mobile -name '*.vue' 2>/dev/null | wc -l | tr -d ' ')"
echo "PHP Services: $(find app/Services -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Controllers: $(find app/Http/Controllers -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Models: $(find app/Models -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Vuex Stores: $(find resources/js/store/modules -name '*.js' 2>/dev/null | wc -l | tr -d ' ')"
echo "Agents: $(find app/Agents -name '*Agent.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Migrations: $(find database/migrations -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Factories: $(find database/factories -name '*.php' 2>/dev/null | wc -l | tr -d ' ')"
echo "Service dirs: $(find app/Services -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l | tr -d ' ')"
echo "API Services: $(find resources/js/services -name '*.js' 2>/dev/null | wc -l | tr -d ' ')"
```

If any counts changed vs CLAUDE.md metrics table, update them. Also check README.md Quick Stats table.

---

## Phase 2: Sync Update Notes to Vault

### 2a: Identify all unsynced files

Compare the local `March/March[DD]Updates/` folders against the vault:

```bash
# For each date folder in local repo
for dir in /Users/CSJ/Desktop/fynla/March/March*Updates; do
  folder=$(basename "$dir")
  vault_dir="/Users/CSJ/Desktop/fynlaBrain/March/$folder"

  if [ -d "$dir" ]; then
    for file in "$dir"/*.md; do
      [ -f "$file" ] || continue
      filename=$(basename "$file")
      if [ ! -f "$vault_dir/$filename" ]; then
        echo "NEW: $folder/$filename"
      elif ! diff -q "$file" "$vault_dir/$filename" > /dev/null 2>&1; then
        echo "CHANGED: $folder/$filename"
      fi
    done
  fi
done
```

### 2b: Copy all new/changed files

For each file identified above:

1. Create the vault directory if it doesn't exist: `mkdir -p "$vault_dir"`
2. Copy the file: `cp "$file" "$vault_dir/$filename"`
3. After copying, verify frontmatter (see Phase 4)

### 2c: Check for subdirectories

Some update folders have subdirectories (e.g. `testFix/`, `plan/`). Sync those too:

```bash
for dir in /Users/CSJ/Desktop/fynla/March/March*Updates; do
  find "$dir" -type d -mindepth 1 | while read subdir; do
    rel_path="${subdir#/Users/CSJ/Desktop/fynla/}"
    vault_path="/Users/CSJ/Desktop/fynlaBrain/$rel_path"
    if [ ! -d "$vault_path" ]; then
      echo "NEW DIR: $rel_path"
      mkdir -p "$vault_path"
    fi
    for file in "$subdir"/*.md; do
      [ -f "$file" ] || continue
      filename=$(basename "$file")
      if [ ! -f "$vault_path/$filename" ]; then
        cp "$file" "$vault_path/$filename"
        echo "  SYNCED: $filename"
      fi
    done
  done
done
```

---

## Phase 3: Update Git History

### 3a: Create/update today's daily commit log

Get today's date and commits:

```bash
TODAY=$(date +%d)
TODAY_FULL=$(date +%Y-%m-%d)
COMMITS=$(git log --oneline --since="$(date +%Y-%m-%d) 00:00:00" --until="$(date -v+1d +%Y-%m-%d) 00:00:00" 2>/dev/null)
COMMIT_COUNT=$(echo "$COMMITS" | grep -c '^' 2>/dev/null || echo 0)
```

Create/update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar${TODAY}.md` following this exact format:

```markdown
---
tags:
  - git-history
  - mar-2026
date: [TODAY_FULL]
commits: [COMMIT_COUNT]
---

# Commits — [DAY] March 2026

Back to [[Git History/Mar2026/Mar2026 Commits|March 2026 Commits]]

**[N] commits** — [breakdown by type: N feat, N fix, N docs, etc.]

**Related:** [[Architecture/v083/...]] (relevant cross-links based on what was changed)

---

| Time | Hash | Type | Message |
|------|------|------|---------|
| HH:MM | `abcd1234` | + | feat: description |
| HH:MM | `efgh5678` | ~ | fix: description |
```

**Type codes:**
- `+` = feat
- `~` = fix
- `D` = docs
- `^` = refactor
- `T` = test
- `C` = chore
- `S` = style
- `P` = perf
- `-` = other/merge

Generate the table from:
```bash
git log --format="%H %ai %s" --since="$(date +%Y-%m-%d) 00:00:00" --until="$(date -v+1d +%Y-%m-%d) 00:00:00" 2>/dev/null
```

### 3b: Update monthly commits index

Read and update `/Users/CSJ/Desktop/fynlaBrain/Git History/Mar2026/Mar2026 Commits.md`:
- Update total commit count
- Update the commit type breakdown
- Add/update today's row in the Daily Logs table: `| [[Mar${TODAY}]] | [N] | [highlight] |`

### 3c: Update Home.md git history count

Update the March 2026 row in the Git History table:
```
| [[Git History/Mar2026/Mar2026 Commits|March 2026]] | [NEW_TOTAL] | [DAYS] |
```

---

## Phase 4: Vault Formatting & Frontmatter Audit

Check ALL files in today's update folder (and any other recently synced folders) for correct Obsidian formatting.

### 4a: Frontmatter check

Every `.md` file in the vault should ideally have YAML frontmatter. For update notes, this is optional but recommended. For git history files, it's required.

**Required frontmatter for git history files:**
```yaml
---
tags:
  - git-history
  - mar-2026
date: YYYY-MM-DD
commits: N
---
```

**Recommended frontmatter for update notes:**
```yaml
---
tags:
  - march-2026
  - [topic tag: deploy, bug-fix, code-review, feature, etc.]
date: YYYY-MM-DD
---
```

If files are missing frontmatter, add it. If they have it but it's malformed, fix it.

### 4b: Wikilink format check

Scan synced files for broken patterns:
- Links should use `[[Target]]` or `[[Path/To/Target|Display Text]]` format
- File references in update note indices should NOT include `.md` extension
- Check that wikilink targets actually exist as files in the vault

```bash
# Find all wikilinks in recently synced files
grep -oP '\[\[([^\]|]+)' /Users/CSJ/Desktop/fynlaBrain/March/March*Updates/*.md 2>/dev/null | sort -u
```

### 4c: No orphaned files

Every file in an update folder should be linked from the March Index. Check:

```bash
# Files in vault update folders
for folder in /Users/CSJ/Desktop/fynlaBrain/March/March*Updates; do
  foldername=$(basename "$folder")
  for file in "$folder"/*.md; do
    [ -f "$file" ] || continue
    filename=$(basename "$file" .md)
    if ! grep -q "\[\[$filename\]\]" "/Users/CSJ/Desktop/fynlaBrain/March/March Index.md" 2>/dev/null; then
      echo "UNLINKED: $foldername/$filename"
    fi
  done
done
```

---

## Phase 5: Update March Index

### 5a: Add session entry

If there isn't already a session entry for today under `## Sessions`, add one:

```markdown
### March[DD] ([N] sessions — [N] commits)

Session [N]: [Brief summary of what was done — features, fixes, deploys]
```

Follow the style of existing entries (see March30, March27, etc.).

### 5b: Add update note links

Under `## Update Notes`, add/update the section for today's update folder:

```markdown
### March[DD]Updates

- [[filename1]] — Brief one-line description
- [[filename2]] — Brief one-line description
```

**Rules:**
- Use `[[filename]]` without `.md` extension (Obsidian wikilink format)
- Use `[[filename|Display Name]]` only if the filename isn't human-readable
- Add a brief `—` description after each link
- If the file has subdirectories, use indented sub-sections

### 5c: Verify all existing links resolve

For the section just added, verify every `[[wikilink]]` target exists as a file:

```bash
# Extract wikilinks from the March[DD]Updates section
# Check each one exists in the vault
```

---

## Phase 6: Update Home.md

Read `/Users/CSJ/Desktop/fynlaBrain/Home.md` and check:

1. **Version number** — matches the current deployed version in CLAUDE.md
2. **Git History table** — March 2026 commit count and day count are current
3. **Reports section** — any new reports from this session are linked
4. **Current State docs** — if a module's state changed significantly, note it

Only update what actually changed.

---

## Phase 7: Cross-Link Integrity

### 7a: Check bidirectional links

For key documents (deploy guides, code reviews, session summaries), verify:
- The March Index links TO the file
- The file links BACK to `[[March Index]]` or `[[Home]]` where appropriate

### 7b: Architecture cross-references

If today's work touched a module, check that the relevant update notes cross-reference the architecture doc:
- `[[Architecture/v083/09-MODULES|Module Guide]]` for module changes
- `[[Architecture/v083/03-AUTHENTICATION-SECURITY|Auth & Security]]` for auth changes
- `[[Architecture/v083/10-NEW-SYSTEMS|New Systems]]` for payment/AI changes

### 7c: Current State doc freshness

Check if any Current State docs are stale relative to today's changes:

```bash
ls -lt /Users/CSJ/Desktop/fynlaBrain/Current\ State/*.md | head -10
```

If a Current State doc hasn't been updated in 2+ weeks and today's work touched that module, flag it for the user.

---

## Phase 8: Memory File Audit

### 8a: Check for stale memories

```bash
ls -lt /Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/*.md
```

Read each memory file. Flag any that:
- Reference code/files that no longer exist
- Contain project status that's outdated
- Have contradictory information vs current state

### 8b: Check MEMORY.md index

Verify every `.md` file in the memory directory has an entry in MEMORY.md, and every MEMORY.md entry points to an existing file.

### 8c: Suggest new memories

Based on this session's work, should any new memories be saved? Only suggest if the information is:
- Not derivable from the code
- Useful across future sessions
- Not already captured in an existing memory

---

## Phase 9: Summary Report

```markdown
## Vault Sync Complete

**Date:** [today]
**Version:** v[X.Y.Z] — consistent across [N] locations

### Metrics
| Metric | CLAUDE.md | Actual | Status |
|--------|-----------|--------|--------|
| [only rows where count differs, or "All metrics current"] |

### Files Synced
- [N] new files copied to vault
- [N] changed files updated
- [folder list]

### Git History
- Mar[DD].md: [created/updated] ([N] commits)
- Monthly index: updated ([TOTAL] total March commits)

### March Index
- Session entry: [added/updated]
- Update notes: [N] wikilinks [added/verified]

### Formatting
- Frontmatter: [N] files checked, [N] fixed
- Wikilinks: [N] checked, [N] broken (list any)
- Orphaned files: [N] (list any)

### Cross-Links
- [N] bidirectional links verified
- [N] architecture cross-references added
- Stale Current State docs: [list or "none"]

### Memory
- [N] memory files audited
- [N] stale, [N] updated, [N] new
```

---

## Important Rules

- The vault is at `/Users/CSJ/Desktop/fynlaBrain/` — NOT a git repo, write files directly
- Use Obsidian format: YAML frontmatter with `tags`, `[[wikilinks]]`, MOC index files
- Wikilinks use `[[filename]]` WITHOUT `.md` extension
- Never invent version numbers — use what's deployed
- Match existing naming conventions (CamelCase for files, kebab-case for some older ones)
- This skill is idempotent — safe to run multiple times
- If nothing changed, say so — don't create fake updates
- Run the formatting checks on ALL synced files, not just new ones
- The March Index format has evolved — use the style from March25+ entries (session summaries + update note sections)
