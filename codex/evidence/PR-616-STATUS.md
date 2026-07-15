# Pull request 616 status

Captured from GitHub on 13 July 2026 after commit `ce74507` was pushed, deployed and recorded in the pull-request description.

## Pull request

- Title: Online readiness: quality gates and Fyn parity
- URL: [github.com/Stoff73/fynla/pull/616](https://github.com/Stoff73/fynla/pull/616)
- Source: `codex/online-readiness-plan`
- Target: `dev`
- State: open draft
- Review: required
- Live staging gate: passed at `ce74507`
- Description: updated with the exact deployed commit, visible Chrome evidence and remaining blockers

## Check results

| Check | Result |
|---|---|
| Logic Guard - Dashboard and Onboarding | Pass |
| Quality Gate - lint | Pass |
| PHP tests - Architecture | Pass |
| PHP tests - Unit | Pass |
| PHP tests - Feature | Pass |
| PHP tests - Integration | Pass |
| PHP tests - Eval | Pass |
| Frontend tests | Pass |
| Builds | Pass |
| Browser smoke | Pass |
| Snyk security checks | Pass |
| GitGuardian security checks | Fail |

GitGuardian incident `34784237` reports a generic password in historical commit `4914dfd`, file `tests/E2E/auth/registration.spec.js`. The value was a synthetic isolated-test credential. Current source at `ce74507` generates it at runtime, but GitGuardian scans all 25 pull-request commits.

## Current release evidence

- Server and feature branch: `ce74507e62ea568a862dcb3de08d9cd8f1dc141e`.
- Desktop manifest hash matches the local staging build.
- Mobile manifest hash matches the local staging build.
- Visible Chrome desktop acceptance: pass.
- Visible Chrome `/m` acceptance: pass.
- `release-smoke` manifest and result: valid.
- `fyn-19079-repetition` manifest and result: valid.
- Quality-assurance cleanup: pass.

## Required decision

The pull request must remain draft until CSJ chooses whether to resolve the GitGuardian incident as a synthetic test value or authorise a branch-history rewrite. Dependency-audit scope also needs a decision before merge readiness is declared.
