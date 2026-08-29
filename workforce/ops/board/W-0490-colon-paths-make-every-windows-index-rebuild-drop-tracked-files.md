---
id: W-0490
title: Two tracked PNGs contain a colon, so every index rebuild on Windows aborts and silently drops tracked files
mission: M-0001-state-truth
owner: build-lead
reviewers: [quality-lead]
status: done
closed: 2026-08-29
severity: high
surfaces: [web, m, ios]
source: found when a dev merge dropped CLAUDE.md, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
constitution_refs: [07-quality-bar, 00-precedence]
---

## Intent

Two tracked files contain a colon in their path:

    August/bugs/ios:17August/img1.png
    August/bugs/ios:17August/img2.png

NTFS cannot represent a colon in a filename, so these can never be checked out on
Windows. Git therefore reports them as deleted in every working tree on the
platform, and **aborts on them during any index rebuild**:

    error: invalid path 'August/bugs/ios:17August/img1.png'
    fatal: make_cache_entry failed for path 'August/bugs/ios:17August/img1.png'

That abort is not contained. It leaves the index **truncated**, and entries sorting
after the invalid path never get written.

## It has already cost a file, and not a small one

Merging `origin/dev` on 2026-08-25 produced merge commit `8adb60e8c` whose tree was
missing `CLAUDE.md` and `CSJTODO.md` — **both of which were present in both
parents**:

| Commit | Has `CLAUDE.md` |
|---|---|
| `775e2d9a1` (ours) | yes |
| `3de6395ef` (dev) | yes |
| `8adb60e8c` (merge) | **no** |

`CLAUDE.md` is precedence rank 1 under `00-precedence.md` §1 — the document that
governs everything touching the codebase. It was dropped with no error, no
conflict, and no mention in the merge summary beyond a `delete mode` line easily
read as intentional.

The damage was compounded by the index being left inconsistent: `git ls-files`
listed both files while `git diff --cached` reported nothing staged, and `git add`
appeared to succeed while changing nothing. Only `git cat-file -p 'HEAD^{tree}'`
told the truth. **A developer trusting `git status` would have pushed the loss.**

Restored in `f4e77e52e`. The recovery required
`git -c core.protectNTFS=false reset` to rebuild a coherent index first.

## Why this is high and not low

- It is silent. There is no conflict marker and no failure exit code on the merge.
- It targets whatever sorts after `August/` in tree order, which includes
  `CLAUDE.md`, `CSJTODO.md`, and every top-level file after them.
- It fires on ordinary operations — merge, pull, reset, checkout — not on anything
  a developer would think twice about.
- Every Windows contributor is exposed on every pull, indefinitely.

## Acceptance

1. The two files renamed to a path without a colon — `August/bugs/ios-17August/`
   matches the sibling folders and is the obvious candidate. The images themselves
   are bug screenshots and worth keeping; this is a rename, not a deletion.
2. A check that the repository contains no path with a character Windows forbids
   (`< > : " | ? *`), so the next one is caught when it lands rather than after it
   eats a file. `git ls-files` piped through a pattern is enough; it does not need
   to be clever.
3. Verified by a clean clone plus `git reset` on Windows completing without
   `make_cache_entry failed`.
4. Anyone who has merged into a Windows worktree since these files landed should
   check `git cat-file -e HEAD:CLAUDE.md` on their branch before pushing. **This
   may have happened before and gone unnoticed** — that has not been investigated.

## Not fixed here

The rename is deliberately left for its own change. Renaming tracked files that
cannot be checked out on this platform needs doing from a machine that can hold
them, or with `git mv` driven purely through the index, and getting that wrong on
the very paths that break the index is how this item gets worse rather than better.

## Resolution — 2026-08-28

**Acceptance 1 — done.** `August/bugs/ios:17August/` renamed to
`August/bugs/ios-17August/` via `git mv`, matching the sibling folders. Both
screenshots kept. The one prose reference to the old path
(`August/August17Updates/iOSBugs/BUG-02-pension-capture-and-projections.md:8`)
was updated; the two quotations inside this item are left as they were, because
they are transcripts of the failure and changing them would make the evidence
describe a path that never broke anything. `git ls-files | grep ':'` now returns
nothing.

**Acceptance 2 — done.** `tests/Feature/Workforce/RepositoryPathsSurviveAWindowsCheckoutTest.php`
greps `git ls-files` for `< > : " | ? *`. It is a pattern, not anything clever,
exactly as this item asked. **Mutation-tested rather than trusted for going
green:** a tracked `probe:colon.txt` was staged and the guard failed; with it
removed the guard passes. A guard written against a repository that is already
clean proves nothing until it has been shown to redden.

**Acceptance 3 — I COULD NOT TEST THIS.** It requires a clean clone plus
`git reset` on Windows, and there is no Windows machine in this environment. The
rename is verified only insofar as no forbidden character remains in the index.
This is the sole reason the item is `gated` rather than `done`.

**Acceptance 4 — carried, not discharged.** Nobody has audited past merges for
earlier silent drops. The reliable query, for anyone who has merged into a
Windows worktree, is a path-scoped one:

    git diff --diff-filter=D --name-only origin/dev...HEAD

An unrestricted `git status` reports clean over exactly this failure, so the
check used after the first occurrence — file on disk and tracked — is a false
negative.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`gated`.

- **Delivered by:** Phailanx/Stoff73
- **Evidence:** merged in #718,#733; commit `9cee8a3b9` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
