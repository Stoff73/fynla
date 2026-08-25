---
id: W-0483
title: All three Stop hooks are hard-wired to a macOS path and never run on Windows
mission: M-0001-state-truth
owner: build-lead
status: queued
severity: high
surfaces: [web, m]
source: found while running CSJ's specified gates for W-0001, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
constitution_refs: [07-quality-bar, 08-process]
---

## Intent

`.claude/settings.json` registers three Stop hooks by absolute path:

    bash /Users/CSJ/Desktop/fynla/.claude/hooks/tax-hardcode-check.sh
    bash /Users/CSJ/Desktop/fynla/.claude/hooks/design-lint.sh
    bash /Users/CSJ/Desktop/fynla/.claude/hooks/m-parity-check.sh

Each script then repeats the same absolute path internally:

    cd /Users/CSJ/Desktop/fynla || exit 0

On any machine that is not CSJ's Mac, both fail. `design-lint.sh` and
`m-parity-check.sh` exit 0 on the `cd` by design; `tax-hardcode-check.sh` has a
bare `cd` with no guard. Either way the hook produces no findings and the session
ends looking clean.

**These are the mechanical enforcement of Rules 8, 11, 15 and 19, plus the
no-hardcoded-tax-values rule.** On Windows they have been reporting a pass without
ever inspecting a file. That is worse than having no hook: a silent pass reads as a
clean bill of health.

The scripts themselves are correct. Only the paths are wrong.

## A second failure, on every platform

`design-lint.sh` Rule 15 emoji detection shells out to `python3`:

    EMOJI=$(python3 -c "..." 2>/dev/null | head -3 || true)

On this machine `python3` resolves to the Microsoft Store stub, which prints an
install advert to stderr and exits non-zero. `2>/dev/null || true` swallows it and
`EMOJI` is empty, so the check always passes.

**That half of the hook has never caught anything here, and would fail on any box
without `python3` on PATH.**

Proven, not theorised: re-implemented in PHP against the W-0001 working tree, it
immediately found four banned glyphs that the shipped hook passed.

## Acceptance

1. Hooks resolve the repository root at runtime instead of hard-coding it —
   `$CLAUDE_PROJECT_DIR` where the harness supplies it, else
   `git rev-parse --show-toplevel`.
2. The internal `cd` uses the same resolved root, and `tax-hardcode-check.sh` gets
   the `|| exit 0` guard the other two already have.
3. Rule 15 emoji detection no longer depends on `python3`. PHP is already a hard
   dependency of this repository.
4. A deliberately seeded violation of each rule is detected, on Windows and on
   macOS, before this is called done. A hook that cannot demonstrate a catch is not
   verified.
5. `jq` dependency reviewed — the hooks emit their decision through `jq`, which is
   absent on this machine. Detection matters most, but a hook that cannot emit a
   block decision cannot block.
