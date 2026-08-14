#!/bin/bash
# Double-click this to open Mission Control.
# Everything after this is buttons in a browser — no more commands, ever.

cd "$(dirname "$0")" || exit 1
PORT=8899

echo "==============================================="
echo "  Fynla Workforce — Mission Control"
echo "==============================================="
echo

if ! command -v php >/dev/null 2>&1; then
  echo "  ✗ PHP not found. It ships with macOS or comes with your Laravel setup."
  read -n 1 -s -r -p "Press any key to close..."; exit 1
fi

grep -q '^SLACK_BOT_TOKEN=xoxb-' .env 2>/dev/null \
  && echo "  ✓ Slack bot token present" \
  || echo "  ! No Slack bot token — Myrtle can't speak yet (workforce/UNBLOCK.md §7)"
command -v claude >/dev/null 2>&1 \
  && echo "  ✓ claude CLI found" \
  || echo "  ! claude CLI not found — Myrtle can't think without it"
echo

# Free the port if a previous run is still holding it.
lsof -ti tcp:$PORT 2>/dev/null | xargs kill 2>/dev/null

echo "  Starting Mission Control on http://localhost:$PORT"
php -S 127.0.0.1:$PORT -t workforce/ops/ui >/dev/null 2>&1 &
SERVER=$!
sleep 1

if ! kill -0 $SERVER 2>/dev/null; then
  echo "  ✗ Server failed to start."
  read -n 1 -s -r -p "Press any key to close..."; exit 1
fi

open "http://localhost:$PORT"

cat <<EOF

  ✓ Mission Control is open in your browser.

  Everything is a button now:
    Start Myrtle · Stop · Check Slack now
    Daily brief · Monday plan · Friday delta · Run sweep
    Approve or hold any gate
    Post to Slack as Myrtle
    Move any work item

  Leave this window open — closing it stops the control panel.
  (Myrtle herself keeps running once started; she is a separate background job.)

EOF

wait $SERVER
