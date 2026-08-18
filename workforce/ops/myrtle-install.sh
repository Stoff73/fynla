#!/usr/bin/env bash
#
# Installs Myrtle as a background service on macOS, so she runs without a terminal
# window open and restarts if she dies or the Mac reboots.
#
#   bash workforce/ops/myrtle-install.sh          install and start
#   bash workforce/ops/myrtle-install.sh --stop   stop and uninstall
#   bash workforce/ops/myrtle-install.sh --status
#
# Two jobs are installed:
#   1. the listener — every 60 seconds, all day
#   2. the daily brief — 17:30 Europe/London, weekdays

set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1
REPO="$(pwd)"
LA="$HOME/Library/LaunchAgents"
L1="$LA/org.fynla.myrtle.listen.plist"
L2="$LA/org.fynla.myrtle.brief.plist"
mkdir -p "$LA" "$REPO/workforce/ops/log"

case "${1:-install}" in
--stop)
  launchctl unload "$L1" 2>/dev/null; launchctl unload "$L2" 2>/dev/null
  rm -f "$L1" "$L2"
  echo "Myrtle stopped and uninstalled."
  exit 0 ;;
--status)
  echo "listener: $(launchctl list 2>/dev/null | grep -c org.fynla.myrtle.listen) loaded"
  echo "brief:    $(launchctl list 2>/dev/null | grep -c org.fynla.myrtle.brief) loaded"
  echo "--- last 15 lines of listener log ---"
  tail -15 "$REPO/workforce/ops/log/myrtle-listen.log" 2>/dev/null || echo "(no log yet)"
  exit 0 ;;
esac

cat > "$L1" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>org.fynla.myrtle.listen</string>
  <key>ProgramArguments</key>
  <array><string>/bin/bash</string><string>$REPO/workforce/ops/myrtle-listen.sh</string></array>
  <key>WorkingDirectory</key><string>$REPO</string>
  <key>StartInterval</key><integer>60</integer>
  <key>RunAtLoad</key><true/>
  <key>StandardOutPath</key><string>$REPO/workforce/ops/log/myrtle-listen.log</string>
  <key>StandardErrorPath</key><string>$REPO/workforce/ops/log/myrtle-listen.log</string>
  <key>EnvironmentVariables</key>
  <dict><key>PATH</key><string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin</string></dict>
</dict></plist>
PLIST

cat > "$L2" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>org.fynla.myrtle.brief</string>
  <key>ProgramArguments</key>
  <array><string>/bin/bash</string><string>-lc</string>
    <string>cd $REPO &amp;&amp; bash workforce/ops/wf.sh brief &amp;&amp; bash workforce/ops/wf.sh slack C0BQ7N1CE82 "\$(cat workforce/ops/reports/brief-\$(date -u +%Y-%m-%d).md)"</string></array>
  <key>WorkingDirectory</key><string>$REPO</string>
  <key>StartCalendarInterval</key>
  <array>
    <dict><key>Weekday</key><integer>1</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>30</integer></dict>
    <dict><key>Weekday</key><integer>2</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>30</integer></dict>
    <dict><key>Weekday</key><integer>3</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>30</integer></dict>
    <dict><key>Weekday</key><integer>4</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>30</integer></dict>
    <dict><key>Weekday</key><integer>5</integer><key>Hour</key><integer>17</integer><key>Minute</key><integer>30</integer></dict>
  </array>
  <key>StandardOutPath</key><string>$REPO/workforce/ops/log/myrtle-brief.log</string>
  <key>StandardErrorPath</key><string>$REPO/workforce/ops/log/myrtle-brief.log</string>
  <key>EnvironmentVariables</key>
  <dict><key>PATH</key><string>/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin</string></dict>
</dict></plist>
PLIST

launchctl unload "$L1" 2>/dev/null; launchctl unload "$L2" 2>/dev/null
launchctl load "$L1" && launchctl load "$L2" && cat <<EOF

Myrtle is running.

  Listener   every 60 seconds
  Brief      17:30, weekdays, posted to #fyn-brief

  Watch her:  tail -f $REPO/workforce/ops/log/myrtle-listen.log
  Check:      bash workforce/ops/myrtle-install.sh --status
  Stop:       bash workforce/ops/myrtle-install.sh --stop

She only runs while this Mac is awake. A closed laptop is a sleeping colleague.
EOF
