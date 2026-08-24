# The fix-queue page

A visual read of `workforce/ops/board/` — what is still waiting to be fixed, grouped by
severity, with the blocked, gated and unowned items surfaced.

## Refresh it

```
python3 workforce/ops/fix-queue-page/build.py --stamp "$(date '+%Y-%m-%d %H:%M %Z')"
```

Then publish `workforce/ops/fix-queue-page/fix-queue.html` as an Artifact, **reusing the
same file path** so it redeploys to the existing URL instead of creating a second page.

## It is a SNAPSHOT, and that is the thing to remember

The page is static HTML hosted on claude.ai. It cannot reach this repo, so it carries the
board state at the moment it was built and nothing else. The stamp under the heading says
when that was.

**It therefore goes stale the moment a board file changes.** On 2026-08-23 the board was
updated twice while the published page was not, and CSJ was looking at a page that read as
current. That is worse than having no page at all.

**So: whenever you change board items, rebuild and republish in the same breath.** Not at
the end of the session — the gap is where the wrong impression lives.

## What counts as "to fix"

`queued`, `blocked`, `claimed`, `review`.

`handoff` is deliberately excluded from the queue and shown in its own collapsed section:
that work is done and waiting on quality-lead, and counting it would inflate the queue with
things nobody needs to fix. `done` and `closed_*` are counted only in the footer.

## Files

| | |
|---|---|
| `build.py` | Reads every board file's frontmatter and the first paragraph of its `## Intent` |
| `template-head.html` | Everything before the embedded data — styles, markup, fonts |
| `template-tail.html` | Everything after — the rendering and filtering script |
| `fix-queue.html` | The generated page. Regenerated on every run; edit the templates, not this |

Design follows `fynlaDesignGuide.md`: Fynla palette, severity as a hot-to-cool ramp with no
amber (Rule 8), no icons (Rule 15), no scores (Rule 12).
