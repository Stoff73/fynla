---
id: W-0491
title: All three Stop hooks are hard-wired to a macOS path and never run on Windows
mission: M-0001-state-truth
owner: build-lead
status: done
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

## Fixed 2026-08-25 — commit aa16e9ae1

Done in the same commit that raised it, because W-0001 could not be evidenced
while the gates that should have caught it were inert.

| Acceptance | State |
|---|---|
| 1. Root resolved at runtime | Done — `${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel)}` in all three |
| 2. Internal `cd` uses it; `tax-hardcode-check` guarded | Done — all three now `cd "$ROOT" \|\| exit 0` |
| 3. Rule 15 check off `python3` | Done — PHP, which is already a hard dependency |
| 4. Seeded violation caught on Windows **and macOS** | **Windows only. See gap.** |
| 5. `jq` dependency reviewed | Done — replaced, see below |

### What was done beyond the stated acceptance

**`jq` removed entirely.** Criterion 5 asked for a review; the review found it
fatal rather than cosmetic. On the first Windows run every rule was detected and
then discarded, because the verdict was emitted through `jq -n` and `jq` is not
installed — the hook printed nothing and exited 0, indistinguishable from a pass.
A new `.claude/hooks/lib-json.sh` provides `json_field`, `json_emit` and
`json_emit_block` in PHP, and both hooks source it.

**Untracked files are no longer skipped.** All three hooks read only
`git diff --name-only HEAD` plus `--cached`, so a brand-new file was invisible —
precisely where new violations arrive. Now unions in
`git ls-files --others --exclude-standard`. Found while testing: the seeded probe
was not detected at all until it was staged.

**Findings deduped.** A staged edit appeared in both diff lists, so every finding
was reported twice. `sort -u`.

### Proven, not assumed

A probe was seeded carrying one violation of each rule and each hook was run
against it:

- `bg-amber-500` caught (Rule 8/11)
- `#ff8800` in a `<style>` block caught (Rule 11)
- an emoji caught (Rule 15) — by the new PHP check; the old `python3` one passed it
- `ISA_ALLOWANCE: 20000` and a hardcoded tax year caught (tax-hardcode-check)
- `m-parity-check` emitted its Rule 19 notice correctly

Probes then removed and all three re-run against the real tree: clean.

**The repaired Rule 15 check immediately found four banned glyphs in W-0001's own
write-up** — a person silhouette, a rightwards arrow, a target and a graduation
cap — which the shipped hook had passed. Fixed in the same commit. That is the
clearest evidence the check was previously inert.

### Gap

**Acceptance 4 is half met. Verified on Windows only — I COULD NOT TEST ON macOS.**
The runtime-root change is the platform-sensitive part and it is the one still
unproven on the platform the hooks used to work on. `git rev-parse --show-toplevel`
and `$CLAUDE_PROJECT_DIR` are both portable, and PHP replaces the dependency that
was least portable, so the expectation is that macOS is unaffected or improved —
but that is reasoning, not evidence. **Someone on the Mac should seed one
violation and confirm a block before this moves past `review`.**

- 2026-08-31 build-lead: **CLOSED — the macOS gap is closed by running it, not by reasoning about
  it.** The 2026-08-25 note left acceptance 4 half met — *"Verified on Windows only — I COULD NOT
  TEST ON macOS"* — and asked for someone on the Mac to seed one violation and confirm a block.
  Done today on macOS 24.6.0, `chore/board-verification-31-august`.

  A probe was seeded carrying one violation of each rule (`bg-amber-500`, `#ff8800` inside a
  `<style>` block, a target emoji, `ISA_ALLOWANCE = 20000` and a hardcoded `2025/26`), the three
  Stop hooks were run against it, and all three fired:

  - **`design-lint.sh`** emitted `{"decision":"block", ...}` naming all three hits separately —
    banned colour token (Rule 8/11), hardcoded hex in `<style>` (Rule 11), **and the emoji
    (Rule 15), which is the check the old `python3` implementation passed silently**.
  - **`tax-hardcode-check.sh`** emitted its hardcoded-tax-values warning.
  - **`m-parity-check.sh`** emitted the Rule 19 notice, naming the changed desktop file.

  Probes removed; all three re-run against the clean tree and silent.

  **Acceptance 5 is answered by the same run:** `design-lint.sh` emitted a real `decision: block`
  payload, so the absence of `jq` is not stopping a block decision from being emitted here.
  Root resolution is `${CLAUDE_PROJECT_DIR:-$(git rev-parse --show-toplevel)}` with the
  `|| exit 0` guard on all three, so the platform-sensitive change is now proven on both
  platforms.
