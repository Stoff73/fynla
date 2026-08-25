#!/usr/bin/env python3
"""Rebuild the Fynla fix-queue page from the board files.

    python3 workforce/ops/fix-queue-page/build.py            # writes the page
    python3 workforce/ops/fix-queue-page/build.py --stamp "2026-08-23 19:28 BST"

Then publish the written file as an Artifact, reusing the SAME file path so it
redeploys to the same URL rather than creating a second page.

THE PAGE IS A SNAPSHOT. It carries the board state at the moment it was built and
says so on screen. It cannot poll anything — it is static HTML on claude.ai with no
access to this repo. So it goes stale the instant a board file changes, and the only
cure is to run this and republish. That happened twice on 2026-08-23: the board was
updated and the page CSJ was looking at was not, which is worse than having no page,
because a stale board reads as a current one.

Lives in the repo rather than a session scratchpad for exactly that reason — a
scratchpad dies with the session and the next inference rebuilds the generator from
scratch, or worse, does not realise it needs to.
"""
import argparse
import glob
import io
import json
import os
import re
import subprocess

ROOT = subprocess.check_output(["git", "rev-parse", "--show-toplevel"], text=True).strip()
HERE = os.path.join(ROOT, "workforce", "ops", "fix-queue-page")
OUT = os.path.join(HERE, "fix-queue.html")

# What "in the queue to be fixed" means. `handoff` is EXCLUDED deliberately: that work
# is done and waiting on quality-lead, and folding it in would inflate the queue with
# things nobody needs to fix. It is shown on the page in its own collapsed section.
TOFIX = ("queued", "blocked", "claimed", "review")
ORDER = {"critical": 0, "high": 1, "medium": 2, "low": 3, "": 4}


def clean(value):
    value = (value or "").strip().strip("\'\"")
    return "" if value in ("null", "[]", "") else value


def read_board():
    rows = []
    for path in sorted(glob.glob(os.path.join(ROOT, "workforce/ops/board/*.md"))):
        text = io.open(path, encoding="utf-8").read()
        matched = re.match(r"^---\n(.*?)\n---\n", text, re.S)
        if not matched:
            continue
        front = {}
        for line in matched.group(1).split("\n"):
            if not line.strip() or line.startswith(" ") or ":" not in line:
                continue
            key, _, value = line.partition(":")
            front[key.strip()] = value.strip()
        intent = ""
        found = re.search(r"^## Intent\s*\n+(.+?)(?:\n\n|\n##)", text[matched.end():], re.S | re.M)
        if found:
            intent = re.sub(r"\s+", " ", found.group(1)).strip()
        rel = os.path.relpath(path, ROOT)
        rows.append({
            "id": clean(front.get("id")) or os.path.basename(path)[:7],
            "title": clean(front.get("title")),
            "status": clean(front.get("status")),
            "severity": clean(front.get("severity")),
            "owner": clean(front.get("owner")) or clean(front.get("claimed_by")),
            "gate": clean(front.get("gate")),
            "blocked_by": clean(front.get("blocked_by")),
            "surfaces": clean(front.get("surfaces")),
            "created": clean(front.get("created"))[:10],
            "mission": clean(front.get("mission")),
            "file": rel,
            "intent": intent[:400],
        })
    return rows


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--stamp", default="", help='e.g. "2026-08-23 19:28 BST"')
    args = parser.parse_args()

    rows = read_board()
    todo = sorted([r for r in rows if r["status"] in TOFIX],
                  key=lambda r: (ORDER.get(r["severity"], 4), r["id"]))
    handoff = sorted([r for r in rows if r["status"] == "handoff"],
                     key=lambda r: (ORDER.get(r["severity"], 4), r["id"]))
    closed = [r for r in rows if r["status"].startswith(("done", "closed"))]

    payload = {
        "todo": todo,
        "handoff": [{k: r[k] for k in ("id", "title", "severity", "owner", "gate")} for r in handoff],
        "meta": {
            "generated": args.stamp or "unstamped",
            "sha": subprocess.check_output(["git", "rev-parse", "--short", "HEAD"], text=True).strip(),
            "branch": subprocess.check_output(["git", "rev-parse", "--abbrev-ref", "HEAD"], text=True).strip(),
            "total": len(rows), "todo": len(todo),
            "handoffCount": len(handoff), "closed": len(closed),
        },
    }

    head = io.open(os.path.join(HERE, "template-head.html"), encoding="utf-8").read()
    tail = io.open(os.path.join(HERE, "template-tail.html"), encoding="utf-8").read()
    io.open(OUT, "w", encoding="utf-8").write(head + "const DATA = " + json.dumps(payload) + tail)

    print(f"{OUT}")
    print(f"{len(todo)} to fix · {len(handoff)} awaiting verification · {len(closed)} closed · {len(rows)} total")


if __name__ == "__main__":
    main()
