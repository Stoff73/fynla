#!/usr/bin/env bash
#
# wf — the workforce command. Emits events, claims items, moves them.
#
# The event log was the gap everything else waited on: liveness monitoring, the
# daily brief and mission control all read it, and nothing wrote to it.
#
#   wf log <agent> <item> <event> [note]   append an event
#   wf claim <item> <agent>                claim (blocks without a prior-art check)
#   wf move <item> <status> [note]         change status, logs it
#   wf handoff <item> <from> <to> <note>   requires a non-empty note
#   wf brief                               generate today's daily brief
#   wf status                               what is where, right now

set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1
OPS="workforce/ops"
LOG="$OPS/log/$(date -u +%Y-%m).jsonl"
mkdir -p "$OPS/log"

now() { date -u +%Y-%m-%dT%H:%M:%SZ; }
# Board filenames carry a slug after the id — `W-0490-colon-paths-....md`. Only
# five of the 302 items are bare `W-NNNN.md`, so the literal path this used to
# build missed every item raised since, and `wf move` answered "no such item"
# for all of them. Glob on the id and take the first match; ids are unique
# (guarded by tests/Feature/Workforce/BoardItemsAreWellFormedTest.php).
item() { local m; m=$(ls "$OPS/board/$1".md "$OPS/board/$1"-*.md 2>/dev/null | head -1); echo "${m:-$OPS/board/$1.md}"; }
fm()  { awk -v k="$2" '/^---$/{n++;next} n==1 && $0 ~ "^"k":" {sub("^"k":[[:space:]]*","");print;exit}' "$1"; }
setfm() { # file key value
  awk -v k="$2" -v v="$3" '/^---$/{n++} n==1 && $0 ~ "^"k":" {print k": "v; next} {print}' "$1" > "$1.t" && mv "$1.t" "$1"
}
emit() { # agent item event [extra-json]
  printf '{"ts":"%s","agent":"%s","item":"%s","event":"%s"%s}\n' \
    "$(now)" "$1" "$2" "$3" "${4:+,$4}" >> "$LOG"
}

cmd="${1:-status}"; shift 2>/dev/null || true

case "$cmd" in

log)
  emit "${1:-unknown}" "${2:--}" "${3:-progress}" "${4:+\"note\":\"$4\"}"
  echo "logged: $3"
  ;;

claim)
  f=$(item "$1"); a="$2"
  [ -f "$f" ] || { echo "no such item: $1"; exit 1; }
  pa=$(fm "$f" prior_art_checked)
  if [ -z "$pa" ] || [ "$pa" = "null" ]; then
    echo "BLOCKED: $1 has no prior-art check."
    echo "  charter.md §11 — nothing is built before prior art is checked."
    echo "  Six sources; outcome must be none, route or extend."
    echo "  Record it, then: wf claim $1 $a"
    emit "$a" "$1" "claim_blocked" '"reason":"no prior-art check"'
    exit 1
  fi
  setfm "$f" owner "$a"; setfm "$f" status claimed
  emit "$a" "$1" "claimed"
  echo "$a claimed $1"
  ;;

move)
  f=$(item "$1"); s="$2"
  [ -f "$f" ] || { echo "no such item: $1"; exit 1; }
  o=$(fm "$f" owner)
  if [ "$s" = "review" ]; then
    ev=$(fm "$f" evidence)
    [ "$ev" = "full" ] || echo "WARNING: $1 -> review with evidence='${ev:-none}'. 08-process.md §2 requires a full pack."
  fi
  setfm "$f" status "$s"
  emit "$o" "$1" "$s" "${3:+\"note\":\"$3\"}"
  echo "$1 -> $s"
  ;;

handoff)
  f=$(item "$1"); from="$2"; to="$3"; note="${4:-}"
  [ -n "$note" ] || { echo "BLOCKED: a handoff needs a note. An item cannot move to handoff with an empty one (FORMATS.md)."; exit 1; }
  d="$OPS/handoffs/$1"; mkdir -p "$d"
  n="$d/${from}-to-${to}-$(date -u +%Y-%m-%d).md"
  { echo "# $1 — $from → $to"; echo; echo "## Done"; echo "$note"; echo
    echo "## Not done, and why"; echo "_state before closing_"; echo
    echo "## What you need that isn't obvious"; echo
    echo "## Assumptions I made"; } > "$n"
  setfm "$f" owner "$to"; setfm "$f" status handoff; setfm "$f" handoff_to "$to"
  emit "$from" "$1" "handoff" "\"to\":\"$to\",\"note\":\"$n\""
  echo "handed $1 to $to — complete $n"
  ;;

brief)
  out="$OPS/reports/brief-$(date -u +%Y-%m-%d).md"
  ship=""; mov=""; need=""
  for f in "$OPS"/board/*.md; do
    [ -e "$f" ] || continue
    id=$(fm "$f" id); t=$(fm "$f" title); s=$(fm "$f" status); o=$(fm "$f" owner)
    case "$s" in
      done)   ship="$ship- $id $t\n";;
      review) need="$need- $id $t — awaiting judgement\n";;
      gated|blocked) need="$need- $id $t ($s)\n";;
      *)      mov="$mov- $id $t — $o\n";;
    esac
  done
  gates=$(ls "$OPS"/gates/*.md "$OPS"/provisioning/*.md 2>/dev/null | wc -l | tr -d ' ')
  ev=$(wc -l < "$LOG" 2>/dev/null | tr -d ' '); ev=${ev:-0}
  { echo "# Daily brief — $(date -u '+%A %d %B %Y')"
    echo; echo "**Shipped**"; printf "%b" "${ship:-- nothing\n}"
    echo; echo "**Moving**"; printf "%b" "${mov:-- nothing in flight\n}"
    echo; echo "**Needs you**"; printf "%b" "${need:-- nothing\n}"
    echo "- $gates open in gates/ and provisioning/"
    echo; echo "**Watch**"
    echo "- Event log: $ev entries this month"
    [ "$ev" -eq 0 ] && echo "- **Nothing is emitting events.** Observation is blind."
    echo; echo "**Read**"
    echo "_Generated from board state at $(now). Not composed by pausing work._"
  } > "$out"
  emit "chief-of-staff" "-" "brief" "\"file\":\"$out\""
  echo "wrote $out"
  ;;

slack)
  # wf slack <channel-name> <message>   — posts AS MYRTLE via the bot token.
  # Never echoes the token. Fails loudly rather than silently posting as CSJ.
  ch="${1:-}"; shift 2>/dev/null || true; msg="${*:-}"
  [ -n "$ch" ] && [ -n "$msg" ] || { echo "usage: wf slack <channel> <message>"; exit 1; }
  tok=$(grep -m1 '^SLACK_BOT_TOKEN=' .env 2>/dev/null | cut -d= -f2- | tr -d '\047\042\015')
  if [ -z "$tok" ]; then
    cat <<'MSG'
BLOCKED: no SLACK_BOT_TOKEN in .env.

Without it the only route is the Slack connector, which authenticates as CSJ —
so the message would appear under his name and could be mistaken for his
decision (gap G-0001).

Set it up: workforce/UNBLOCK.md §7. Eight minutes, once.
MSG
    emit "chief-of-staff" "-" "slack_blocked" '"reason":"no bot token; would post as CSJ"'
    exit 1
  fi
  resp=$(curl -sS -X POST https://slack.com/api/chat.postMessage \
    -H "Authorization: Bearer $tok" \
    -H 'Content-Type: application/json; charset=utf-8' \
    --data "$(printf '{"channel":"%s","text":%s,"username":"Myrtle"}' \
      "$ch" "$(printf '%s' "$msg" | sed 's/\\/\\\\/g; s/"/\\"/g; s/$/\\n/' | tr -d '\n' | sed 's/^/"/; s/$/"/')")" 2>&1)
  if printf '%s' "$resp" | grep -q '"ok":true'; then
    emit "chief-of-staff" "-" "slack_posted" "\"channel\":\"$ch\""
    echo "posted to $ch as Myrtle"
  else
    echo "FAILED: $(printf '%s' "$resp" | sed 's/xoxb-[A-Za-z0-9-]*/xoxb-REDACTED/g')"
    emit "chief-of-staff" "-" "slack_failed" "\"channel\":\"$ch\""
    exit 1
  fi
  ;;

monday)
  # Monday plan — the commitment record Friday is measured against.
  out="$OPS/reports/monday-$(date -u +%Y-%m-%d).md"
  { echo "# Monday plan — $(date -u '+%d %B %Y')"; echo
    echo "## Carried forward"
    for f in "$OPS"/board/*.md; do [ -e "$f" ] || continue
      s=$(fm "$f" status); [ "$s" = "done" ] && continue
      echo "- $(fm "$f" id) $(fm "$f" title) — $s"; done
    echo; echo "## This week's commitment"
    echo "_Friday is computed against this list, item by item. Every item appears"
    echo "Friday as done, not done, or descoped. There is no fourth option._"
    echo; echo "## Decisions expected"
    for f in "$OPS"/gates/*.md "$OPS"/provisioning/*.md; do [ -e "$f" ] || continue
      [ "$(fm "$f" status)" = "resolved" ] && continue
      echo "- $(fm "$f" id) — $(fm "$f" needs)$(fm "$f" action)"; done
  } > "$out"
  emit "chief-of-staff" "-" "monday_plan" "\"file\":\"$out\""
  echo "wrote $out"
  ;;

friday)
  # Friday delta — computed against Monday's plan. "Not done" is never summarised.
  mon="$OPS/reports/monday-$(date -u -v-4d +%Y-%m-%d 2>/dev/null || date -u -d '4 days ago' +%Y-%m-%d).md"
  out="$OPS/reports/friday-$(date -u +%Y-%m-%d).md"
  { echo "# Friday delta — $(date -u '+%d %B %Y')"; echo
    [ -f "$mon" ] && echo "_Computed against $mon_" || echo "_No Monday plan found — drift cannot be measured this week._"
    echo; echo "## Done"
    for f in "$OPS"/board/*.md; do [ -e "$f" ] || continue
      [ "$(fm "$f" status)" = "done" ] && echo "- $(fm "$f" id) $(fm "$f" title)"; done
    echo; echo "## Not done, and why"
    echo "_Never summarised. Three items slipping for one reason is a pattern;"
    echo "\"we didn't get to a few things\" is not._"
    for f in "$OPS"/board/*.md; do [ -e "$f" ] || continue
      s=$(fm "$f" status); case "$s" in done) continue;; esac
      echo "- $(fm "$f" id) $(fm "$f" title) — **$s** — reason: _state it_"; done
    echo; echo "## Drift"
    echo "_Work done this week that was not in Monday's plan. Not automatically bad —"
    echo "bugs and triage belong here. Named so the ratio stays visible._"
    echo; echo "## Trunk amendments proposed"
    echo; echo "## Interview batch"
    echo; echo "## Gap register"
    for f in "$OPS"/gaps/*.md; do [ -e "$f" ] || continue
      [ "$(fm "$f" status)" = "open" ] && echo "- $(fm "$f" id) [$(fm "$f" severity)] $(fm "$f" agent)"; done
  } > "$out"
  emit "chief-of-staff" "-" "friday_delta" "\"file\":\"$out\""
  echo "wrote $out"
  ;;

status|*)
  printf '%-8s %-46s %-16s %s\n' ID TITLE OWNER STATUS
  for f in "$OPS"/board/*.md; do
    [ -e "$f" ] || continue
    printf '%-8s %-46.46s %-16s %s\n' "$(fm "$f" id)" "$(fm "$f" title)" "$(fm "$f" owner)" "$(fm "$f" status)"
  done
  echo
  echo "events this month: $(wc -l < "$LOG" 2>/dev/null | tr -d ' ' || echo 0)"
  echo "open gates:        $(ls "$OPS"/gates/*.md 2>/dev/null | wc -l | tr -d ' ')"
  ;;
esac
