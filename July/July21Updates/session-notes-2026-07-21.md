---
tags:
  - july-2026
  - session-notes
  - ios-native
date: 2026-07-21
---

# Session Notes — 2026-07-20 → 21 (one continuous session, context-clear wrap)

Back to [[July Index]] | Handover: [[handover-2026-07-21-session-1-clear]]

## Arc

CSJ ruled the earlier iOS parity work "on the wrong path": the native app is a
**copy of `/m`** — transcribe from `resources/mobile/` source and verify
against the live `/m` rendering. This session executed that ruling: full
shell/dashboard/drawer/Fyn transcription, audit P0/P1 remediation, the
CSJ-directed milestone-banner fix on BOTH surfaces, and the module sweep
through ten screens with the late dark-m-hero correction. All work committed
AND pushed (CSJ lifted the push freeze: "lets commit then continue").

## Commits — pkg7 branch `codex/ios-package7-platform-release` (worktree)

- a269963 feat(ios-native): transcribe shell, dashboard, drawer and Fyn from /m source
- 2ed2b00 fix(ios-native): consent surface honours the no-toggle contract
- 353e6c7 fix(ios-native): audit P1 remediation — tolerant decodes, honest failure states, CI guardrail
- dab1dc6 test(api): pin the Apple webhook named limiter without the Apple bridge
- 8ce839c feat(ios-native): shared /m MobileChrome shell + Income, Expenditure, Achievements sweep
- fdc99d7 docs(ios): ledger — sweep block 1 dispositions
- 9cba413 test(ios-native): sanction the shell hamburger symbol (/m md-hamburger)
- 472b7a3 fix(ios-native): milestone banners sit below the level card (CSJ direction)
- 18ed389 feat(ios-native): /m-styled screen state card (raspberry Try again pill)
- 9473162 feat(ios-native): Net Worth transcribed from /m (sweep)
- 8c94237 feat(ios-native): Savings transcribed from /m (sweep)
- e9ba4e6 feat(ios-native): Investments transcribed from /m (sweep)
- d8d7b30 feat(ios-native): Protection transcribed from /m (sweep)
- 93d250b feat(ios-native): Goals transcribed from /m (sweep)
- 00aa4a1 feat(ios-native): Estate transcribed from /m (sweep)
- 776a70b feat(ios-native): Retirement transcribed from /m + dark m-hero correction (sweep)
- f277af4 docs(ios): ledger — sweep block 2 dispositions

## Commits — main repo `codex/savetax-allowance-ctas`

- 2772831 fix(m): milestone banners sit below the level card (CSJ direction)
  — **needs CSJ build + deploy to show on csjones**
- 61c487a docs(session): context-clear handover 2026-07-21-session-1

## Verification evidence

- 338 unit tests green bar the 6 pre-existing local StoreKit reds (green in CI).
- Five journey UI tests green after every block (shell fixtures,
  level-wheel→achievements, Fyn conversation, Fyn→bug-report full submit,
  drawer→settings Face ID) on the Fynla iPhone SE simulator.
- ParityScreenshotTests capture harness added; live `/m` reference
  screenshots taken on csjones (login + MFA via tinker) and matched.
- Two real app defects found by verification and fixed: header/hero phantom
  gradient swallowed hero taps; Fyn-cover dismiss/navigate race.

## Record of truth

Parity ledger (dispositions incl. the six /m-parity KEEPs):
`codex/plans/ios/2026-07-20-native-m-parity-ledger.md` on the pkg7 branch.

## Next

The **"NEXT JOBS — Left in the Sweep"** list in
[[handover-2026-07-21-session-1-clear]] — starting with Tax Strategy, then
Holistic Plan, then the six sub-pages.
