# Repository snapshot

Captured on 13 July 2026 at 16:10 BST after `git fetch origin`.

## Branch relationship

```text
origin/dev e16ea5f
  + 23 commits
HEAD and origin/codex/online-readiness-plan 838bc14
```

The feature branch exactly matches its remote and is not behind `origin/dev`.

## Feature size

- 193 changed files.
- 21,749 insertions.
- 5,571 deletions.
- 85 changed paths under `tests/`.
- 34 restored source artifacts under `July/`.
- 25 changed paths under `app/`.
- 12 changed paths under `resources/`.
- 12 changed paths under `docs/`.
- 9 changed paths under `scripts/`.
- No new migration or seeder between `origin/dev` and the feature tip.

## Pre-bundle working tree

There were no tracked modifications. Pre-existing untracked categories were:

- Local `.agents/` skill files.
- Local `.codex/` agent, configuration and hook files.
- Root `AGENTS.md`.
- `docs/security/security-review-2026-06-09.md`.
- One Excalidraw Python cache directory.
- `playwright-report/`.
- `test-results/results.json`.

The continuity bundle itself is now an additional untracked `codex/` directory. Nothing was staged.

## Recovery anchors

- The complete implementation is recoverable from the pushed remote branch.
- Pull request 616 preserves the 23-commit sequence and validation history.
- This bundle preserves the planning, session and current-release context without duplicating the application source tree.
