#!/usr/bin/env bash
#
# UserPromptSubmit hook. CSJ 2026-09-27: put the working rules and the list of
# work in front of Claude on EVERY prompt, so it is worked in order and nothing
# drifts. CSJ 2026-09-30: the list is todoCurrent/TODO.md (the persistent,
# ordered list CSJ edits and answers decisions on). This shows its current
# item in full, the next three items, and every open DECISION (CSJ). Without
# the list it falls back to the newest handover's "## Priorities" section.

REPO="/Users/CSJ/Desktop/fynla"
TODO="$REPO/todoCurrent/TODO.md"

rules="STANDING RULES (CSJ 2026-09-27, enforced every prompt):
1. Work todoCurrent/TODO.md IN ORDER. The current item is the first one under \"To do\" not crossed off. Do not start anything not on it unless CSJ names it in this message.
2. One job per session: the job CSJ names. Anything found while working goes under the current item as a \"Found:\" line, not into the session.
3. Do not ask questions the code, spec, memory or list already answer. Decide from the evidence and do the work. An unanswered question never parks a whole item.
4. NEVER run a full test suite locally (a hook blocks it). Run only the test files the change touches. CI runs the rest.
5. Read MEMORY.md feedback before acting. Fix defects found in the path; do not report them for later.
6. Done items are crossed off with date and evidence, never deleted. CSJ edits the list and answers decisions on it: anything CSJ wrote there is the answer; record it as such."

if [ -f "$TODO" ]; then
  # Open items: numbered "N. [ ]" lines under "## To do", each with its
  # indented sub-lines. Item 1 of the output is the current item.
  open_items="$(awk '
    /^## To do/ { in_todo = 1; next }
    in_todo && /^## / { in_todo = 0 }
    !in_todo { next }
    /^[0-9]+\. \[ \]/ { n++; keep = 1; print "@@ITEM@@"; print; next }
    /^[0-9]+\. \[x\]/ { keep = 0; next }
    keep && /^[[:space:]]+- / { print; next }
    /^[^[:space:]]/ { keep = 0 }
  ' "$TODO")"

  current="$(printf '%s\n' "$open_items" | awk '/^@@ITEM@@/{c++; next} c==1')"
  next3="$(printf '%s\n' "$open_items" | awk '/^@@ITEM@@/{c++; next} c>=2 && c<=4 && /^[0-9]+\./' | cut -c1-240)"
  decisions="$(printf '%s\n' "$open_items" | grep 'DECISION (CSJ)' | sed 's/^[[:space:]]*//' | cut -c1-240)"

  list="CURRENT LIST: todoCurrent/TODO.md

CURRENT ITEM:
${current:-Nothing open on the list.}

NEXT:
${next3:-None.}"
  if [ -n "$decisions" ]; then
    list="$list

OPEN DECISIONS (CSJ):
$decisions"
  fi
  context="$rules

$list"
else
  latest="$(find "$REPO/handover" -name 'handover-*.md' 2>/dev/null \
    | awk -F/ '{print $NF "\t" $0}' | sort | tail -1 | cut -f2)"
  priorities=""
  if [ -n "$latest" ]; then
    priorities="$(awk '/^## Priorities/{on=1; next} on && /^## /{exit} on' "$latest" | head -60)"
  fi
  context="$rules

CURRENT HANDOVER: ${latest#$REPO/}
$priorities"
fi

jq -n --arg c "$context" '{hookSpecificOutput: {hookEventName: "UserPromptSubmit", additionalContext: $c}}'
