#!/usr/bin/env bash
#
# Mission Control — reads the real workforce tree and writes a self-contained
# HTML page. No server, no connector, no OAuth. Open the file in a browser.
#
#   bash workforce/ops/mission-control.sh && open workforce/ops/mission-control.html
#
# Everything it shows is read from disk at generation time. Nothing is invented.

set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1
OPS="workforce/ops"
OUT="$OPS/mission-control.html"

fm() { # file, key -> frontmatter value
  awk -v k="$2" '/^---$/{n++; next} n==1 && $0 ~ "^"k":" {sub("^"k":[[:space:]]*",""); print; exit}' "$1"
}
esc() { sed -e 's/&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g'; }

# ---- board ------------------------------------------------------------------
# No associative arrays — `declare -A` is bash 4+, and macOS ships bash 3.2.
rows=""; c_done=0; c_prog=0; c_review=0; c_blocked=0
for f in "$OPS"/board/*.md; do
  [ -e "$f" ] || continue
  id=$(fm "$f" id); t=$(fm "$f" title); o=$(fm "$f" owner)
  s=$(fm "$f" status); m=$(fm "$f" mission); sf=$(fm "$f" surfaces)
  pa=$(fm "$f" prior_art_checked); ev=$(fm "$f" evidence)
  case "$s" in done) c_done=$((c_done+1));; in_progress|claimed) c_prog=$((c_prog+1));; review) c_review=$((c_review+1));; blocked|gated) c_blocked=$((c_blocked+1));; esac
  gate=""
  [ "$s" = "review" ] && [ "${ev:-}" != "full" ] && gate="evidence incomplete"
  [ -z "$pa" ] || [ "$pa" = "null" ] && [ "$s" != "queued" ] && gate="${gate:+$gate · }no prior-art check"
  rows="$rows<tr><td class=id>$(printf '%s' "$id"|esc)</td><td>$(printf '%s' "$t"|esc)</td><td>$(printf '%s' "$o"|esc)</td><td><span class='p s-$s'>$s</span></td><td class=dim>$(printf '%s' "${sf:-—}"|esc)</td><td class=warn>$(printf '%s' "$gate"|esc)</td></tr>"
done

# ---- missions ---------------------------------------------------------------
mrows=""
for f in "$OPS"/missions/*.md; do
  [ -e "$f" ] || continue
  mid=$(basename "$f" .md)
  tot=$(grep -l "mission: $mid" "$OPS"/board/*.md 2>/dev/null | wc -l | tr -d ' ')
  dn=$(grep -l "mission: $mid" "$OPS"/board/*.md 2>/dev/null | xargs grep -l "^status: done" 2>/dev/null | wc -l | tr -d ' ')
  ttl=$(head -1 "$f" | sed 's/^# *//')
  pct=0; [ "$tot" -gt 0 ] && pct=$(( dn * 100 / tot ))
  mrows="$mrows<div class=m><div class=mh><b>$(printf '%s' "$ttl"|esc)</b><span class=dim>$dn/$tot done</span></div><div class=bar><span style='width:${pct}%'></span></div></div>"
done

# ---- gates / provisioning / gaps -------------------------------------------
glist=""
for f in "$OPS"/gates/*.md "$OPS"/provisioning/*.md; do
  [ -e "$f" ] || continue
  gid=$(fm "$f" id); nd=$(fm "$f" needs); st=$(fm "$f" status); ac=$(fm "$f" action)
  [ "${st:-open}" = "open" ] || continue
  glist="$glist<li><b>$(printf '%s' "$gid"|esc)</b> — $(printf '%s' "${nd:-$ac}"|esc)</li>"
done
[ -z "$glist" ] && glist="<li class=dim>Nothing waiting.</li>"

# ---- trunk health (from the sweep) -----------------------------------------
sweep=$(bash "$OPS/sweep.sh" 2>/dev/null | tail -2 | head -1 | sed 's/\x1b\[[0-9;]*m//g')

# ---- agents -----------------------------------------------------------------
arows=""
for a in .claude/agents/*.md; do
  [ -e "$a" ] || continue
  n=$(awk '/^name:/{print $2; exit}' "$a")
  case "$n" in
    chief-of-staff|quartermaster|cartographer|archivist|build-lead|quality-lead|compliance-lead|intelligence-lead|product-lead|design-lead|growth-lead) tag="workforce";;
    *) tag="specialist";;
  esac
  held=$(grep -l "^owner: $n" "$OPS"/board/*.md 2>/dev/null | wc -l | tr -d ' ')
  arows="$arows<tr><td><b>$(printf '%s' "$n"|esc)</b></td><td class=dim>$tag</td><td>$held</td></tr>"
done

cat > "$OUT" <<HTML
<!DOCTYPE html><html lang=en-GB><head><meta charset=utf-8>
<title>Fynla Workforce — Mission Control</title><style>
:root{color-scheme:light}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Segoe UI",Inter,-apple-system,sans-serif;background:#FAFBFC;color:#0F172A;font-size:13px;padding:22px;line-height:1.45}
.w{max-width:1180px;margin:0 auto}
h1{font-size:19px;font-weight:900;letter-spacing:-.2px}
h2{font-size:11px;font-weight:700;letter-spacing:.9px;text-transform:uppercase;color:#5B6883;margin-bottom:11px}
.sub{font-size:11px;color:#5B6883}
header{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:18px}
section{background:#fff;border:1px solid #E4E9F1;border-radius:10px;padding:14px 16px;margin-bottom:14px}
table{width:100%;border-collapse:collapse;font-size:12px}
th{text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:#5B6883;padding:0 8px 7px 0;border-bottom:1px solid #E4E9F1}
td{padding:7px 8px 7px 0;border-bottom:1px solid #F1F4F9;vertical-align:top}
tr:last-child td{border-bottom:0}
.id{font-weight:700;white-space:nowrap}
.dim{color:#5B6883}
.warn{color:#E83E6D;font-weight:700;font-size:11px}
.p{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;white-space:nowrap}
.s-done{background:#E7F7F1;color:#0E7A5A}.s-in_progress{background:#ECEBFD;color:#3F3BB8}
.s-review{background:#FBF3EA;color:#8A6A3C}.s-blocked,.s-gated{background:#FDEAF0;color:#B32350}
.s-queued,.s-claimed{background:#F1F4F9;color:#5B6883}
.m{padding:8px 0;border-bottom:1px solid #F1F4F9}.m:last-child{border-bottom:0}
.mh{display:flex;justify-content:space-between;align-items:baseline;font-size:12px}
.bar{height:5px;background:#DDE3ED;border-radius:3px;margin-top:6px;overflow:hidden}
.bar span{display:block;height:100%;background:#20B486;border-radius:3px}
ul{list-style:none}li{padding:5px 0;border-bottom:1px solid #F1F4F9;font-size:12px}li:last-child{border-bottom:0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
footer{font-size:10.5px;color:#5B6883;text-align:center;margin-top:8px}
</style></head><body><div class=w>
<header><div><h1>Fynla Workforce — Mission Control</h1>
<div class=sub>Read from <code>workforce/ops/</code> on disk. Nothing here is illustrative.</div></div>
<div class=sub>$(date -u '+%a %d %b %H:%M UTC')</div></header>

<section><h2>Missions</h2>$mrows</section>

<div class=grid>
<section><h2>Awaiting a founder</h2><ul>$glist</ul></section>
<section><h2>Trunk health</h2><p style="font-size:12px">$(printf '%s' "$sweep"|esc)</p>
<p class=sub style="margin-top:6px">Run <code>bash workforce/ops/sweep.sh</code> for detail.</p></section>
</div>

<section><h2>Board</h2><table>
<thead><tr><th style=width:70px>ID</th><th>Title</th><th style=width:120px>Owner</th><th style=width:100px>Status</th><th style=width:130px>Surfaces</th><th style=width:150px>Gate</th></tr></thead>
<tbody>$rows</tbody></table></section>

<section><h2>Agents</h2><table>
<thead><tr><th style=width:200px>Name</th><th style=width:110px>Role</th><th style=width:80px>Items held</th></tr></thead>
<tbody>$arows</tbody></table></section>

<footer>Regenerate: <code>bash workforce/ops/mission-control.sh</code></footer>
</div></body></html>
HTML

echo "Wrote $OUT"
echo "Board: $c_done done · $c_prog in progress · $c_review review · $c_blocked blocked"
