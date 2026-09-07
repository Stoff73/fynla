#!/usr/bin/env bash
#
# Consistency sweep — the Archivist's checks, runnable.
#
#   1. ORPHAN      every file reference in the trunk resolves
#   2. STRUCTURE   every expected trunk file exists
#   3. AGENTS      every agent definition has valid frontmatter
#
# Exit 0 = clean, 1 = findings. Run from the repo root.

set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1

ROOT="$(pwd)"
CORE="workforce/core"
findings=0
checked=0
advisories=0

red()  { printf '\033[31m%s\033[0m\n' "$1"; }
grn()  { printf '\033[32m%s\033[0m\n' "$1"; }

echo "Consistency sweep — $(date -u '+%Y-%m-%d %H:%M UTC')"
echo "================================================"

# --- 1. ORPHAN: file references in the trunk resolve ------------------------
echo
echo "[1] Orphan check — trunk file references"

# Build a basename index once — shorthand references ("01-mission.md") are names,
# not paths, and must still resolve to a real file somewhere sensible.
#
# W-0506 — the roots below are the whole of this check's accuracy. `tests`, `public`,
# `ios-native`, `routes` and `fyn-memory` were missing, so every citation of a persona
# report under tests/Persona/…/reports/, a test filename quoted as evidence, an iOS
# fixture, a built asset under m-build/, or a Fyn tool schema was unresolvable BY
# CONSTRUCTION — reported broken because the index could not see it, not because
# anything was wrong. That was 55 of 99 findings on 2026-09-01, and it is why a
# three-minute check with real findings in it stopped being read.
#
# Add a root here when documents start citing one. Do NOT instead add a heuristic that
# guesses which references are "only citations": the rule proposed on W-0506 — treat
# only paths containing a slash as links — was measured and would have kept 25
# `reports/…` citations while hiding nothing useful.
find workforce app docs Articles April config database resources .claude .remember scripts deploy \
     tests public ios-native routes fyn-memory \
     ../fynlaBrain -type f \
     \( -name '*.md' -o -name '*.php' -o -name '*.js' -o -name '*.json' -o -name '*.sh' -o -name '*.vue' -o -name '*.swift' \) \
     2>/dev/null | sed 's|.*/||' | sort -u > /tmp/sweep_names.txt

while IFS= read -r src; do
  grep -oE '`[A-Za-z0-9._/-]+\.(md|php|js|json|sh|vue)`' "$src" 2>/dev/null \
    | tr -d '`' | sort -u | while IFS= read -r ref; do
      [ -z "$ref" ] && continue
      # Format placeholders are patterns, not paths — NNNN, YYYY-MM-DD, <slug>, and
      # the elided form `F-....md` / `Foo.php` / `.php/.blade.php/.html` (W-0506).
      case "$ref" in *NNNN*|*YYYY*|*'<'*|*'...'*|*'/.'*) continue ;; esac
      base="${ref##*/}"
      # Resolve in order: exact path · under core · relative to source ·
      # known basename anywhere in the tracked tree.
      if   [ -e "$ROOT/$ref" ] \
        || [ -e "$ROOT/$CORE/$ref" ] \
        || [ -e "$(dirname "$src")/$ref" ] \
        || grep -qxF "$base" /tmp/sweep_names.txt; then
        :
      else
        echo "  BROKEN  $src -> $ref"
      fi
    done
done < <(find "$CORE" workforce/ops -name '*.md' -type f 2>/dev/null) > /tmp/sweep_orphans.txt

orphans=$(wc -l < /tmp/sweep_orphans.txt | tr -d ' ')
if [ "$orphans" -gt 0 ]; then
  cat /tmp/sweep_orphans.txt
  red "  $orphans unresolved reference(s)"
  findings=$((findings + orphans))
else
  grn "  all references resolve"
fi

# --- 2. STRUCTURE: expected trunk files exist -------------------------------
echo
echo "[2] Structure check — trunk completeness"

expected=(
  "$CORE/index.md"
  "$CORE/charter.md"
  "$CORE/constitution/00-precedence.md"
  "$CORE/constitution/01-mission.md"
  "$CORE/constitution/02-values.md"
  "$CORE/constitution/03-hard-nos.md"
  "$CORE/constitution/04-voice.md"
  "$CORE/constitution/05-perimeter.md"
  "$CORE/constitution/06-commercials.md"
  "$CORE/constitution/07-quality-bar.md"
  "$CORE/constitution/08-process.md"
  "$CORE/registry/systems.md"
  "$CORE/registry/tools.md"
  "$CORE/registry/access.md"
  "$CORE/registry/comms.md"
  "$CORE/registry/people.md"
  "$CORE/registry/rhythm.md"
  "$CORE/registry/capabilities.md"
  "$CORE/registry/storage.md"
  "$CORE/registry/meetings.md"
  "workforce/ops/FORMATS.md"
)

for f in "${expected[@]}"; do
  checked=$((checked + 1))
  if [ -f "$f" ]; then
    printf '  ok      %s\n' "$f"
  else
    echo "  MISSING $f"
    findings=$((findings + 1))
  fi
done

# --- 3. AGENTS: frontmatter valid -------------------------------------------
echo
echo "[3] Agent check — frontmatter"

for a in .claude/agents/*.md; do
  [ -e "$a" ] || continue
  checked=$((checked + 1))
  name=$(awk '/^name:/{print $2; exit}' "$a")
  hasdesc=$(grep -cE '^description:' "$a")
  opens=$(head -1 "$a")
  if [ "$opens" != "---" ] || [ -z "$name" ] || [ "$hasdesc" -eq 0 ]; then
    echo "  INVALID $a (delimiter/name/description)"
    findings=$((findings + 1))
  else
    printf '  ok      %-28s %s\n' "$name" "$(basename "$a")"
  fi
done

# --- 4. SIZE BUDGETS (00-precedence.md §2.4) --------------------------------
echo
echo "[4] Size budgets"

check_budget() { # path, budget
  [ -f "$1" ] || return 0
  checked=$((checked + 1))
  local n; n=$(wc -c < "$1" | tr -d ' ')
  if [ "$n" -gt "$2" ]; then
    printf '  OVER    %-52s %6s / %s\n' "$1" "$n" "$2"
    # W-0506 — counted as an ADVISORY, not a finding. `00-precedence.md` §2.4 says it
    # in terms: "Budgets are advisory — crossing one triggers a review, not an
    # automatic cut." Adding them to the finding total put seven standing reviews in
    # the same number as broken references, and a headline that is mostly advisories
    # is a headline nobody reads.
    advisories=$((advisories + 1))
  else
    printf '  ok      %-52s %6s / %s\n' "$1" "$n" "$2"
  fi
}

check_budget "$CORE/index.md" 3000

# Doctrine stays tight: a constitution nobody finishes reading does not bind anyone.
for f in "$CORE"/constitution/*.md "$CORE"/charter.md; do
  check_budget "$f" 8000
done

# W-0506 — the registry is budgeted separately, and the reason is what it is.
# `capabilities.md` and `sources.md` ENUMERATE: they grow with the system by design,
# and holding them to the doctrine budget reported permanent breach for doing their
# job. 8k was the wrong number for a list, not evidence of bloat. 32k still triggers
# a review — it is a budget, not permission.
for f in "$CORE"/registry/*.md; do
  check_budget "$f" 32000
done
check_budget "CLAUDE.md" 40000

# --- 5. OPS STRUCTURE -------------------------------------------------------
echo
echo "[5] Ops structure"

for d in board missions interviews handoffs gaps gates provisioning triage reports log; do
  checked=$((checked + 1))
  if [ -d "workforce/ops/$d" ]; then
    printf '  ok      workforce/ops/%s\n' "$d"
  else
    echo "  MISSING workforce/ops/$d"
    findings=$((findings + 1))
  fi
done

# --- 6. CONTRADICTION: restated rules ---------------------------------------
# Cheap heuristic for the "one home per rule" law: a distinctive phrase appearing
# in more than one trunk file is a candidate restatement, not a reference.
echo
echo "[6] Contradiction check — candidate restatements"

dupes=0
# W-0506 — a phrase that appears BESIDE a pointer to its home is a reference, not a
# restatement. This check had the same flaw as the orphan check above: it could not tell
# a duplicate from a citation with attribution, and reported all three of
# "never verifies their own work"'s references — `07-quality-bar.md:65` and
# `capabilities.md:200` both name `08-process.md` §2.4 in the same sentence, and
# `00-precedence.md:147` is this sweep's own note about the finding.
#
# `home_of` names where a clause lives. A file carrying the phrase AND a reference to
# that home is doing exactly what the fix for a restatement is supposed to produce, so
# counting it as a finding means the check can never go green.
home_of() {
  case "$1" in
    "never verifies their own work") echo "08-process.md" ;;
    *) echo "" ;;
  esac
}

for phrase in "engineering to CSJ" "100,000" "£6.99" "never verifies their own work" "any hold beats any approve"; do
  home=$(home_of "$phrase")
  if [ -n "$home" ]; then
    carriers=$(grep -rl "$phrase" "$CORE" 2>/dev/null | while IFS= read -r f; do
      grep -q "$home" "$f" || echo "$f"
    done)
  else
    carriers=$(grep -rl "$phrase" "$CORE" 2>/dev/null)
  fi
  hits=$([ -z "$carriers" ] && echo 0 || echo "$carriers" | wc -l | tr -d ' ')
  checked=$((checked + 1))
  if [ "$hits" -gt 1 ]; then
    echo "  RESTATED  \"$phrase\" in $hits trunk files:"
    echo "$carriers" | sed 's/^/            /'
    dupes=$((dupes + 1))
    findings=$((findings + 1))
  fi
done
[ "$dupes" -eq 0 ] && grn "  no candidate restatements"

# --- summary ----------------------------------------------------------------
echo
echo "================================================"
if [ "$advisories" -gt 0 ]; then
  echo "$advisories advisory/advisories (size budgets — review, not breach)"
fi
if [ "$findings" -eq 0 ]; then
  grn "CLEAN — $checked structural checks, 0 findings"
  exit 0
else
  red "$findings finding(s) across $checked checks"
  exit 1
fi
