# Deployment report - csjones feature-branch gate

Captured on 13 July 2026 at 17:25 BST.

## Outcome

The online-readiness feature candidate is deployed and live-tested on `https://csjones.co/fynla`.

The server is intentionally running the feature branch before merge, as required by the release workflow. Pull request 616 remains an open draft targeting `dev`; nothing was merged and nothing was deployed to production.

| Field | Value |
|---|---|
| Branch | `codex/online-readiness-plan` |
| Exact deployed commit | `ce74507e62ea568a862dcb3de08d9cd8f1dc141e` |
| Pull request | `616`, draft, targeting `dev` |
| Desktop manifest SHA-256 | `c421376dc8ac55c209d12003ae7b68228f94518c3841422adef1da0042bc97cb` |
| Mobile manifest SHA-256 | `b18e7cd7c621d12c2e9ebbda25894a6e6de26060f2db256cf31d3405b4eb8541` |
| Database migrations | None in the final dashboard correction; no migration was run |
| Production impact | None |

The local and server manifest hashes match exactly. The server checkout has no tracked-file drift.

## Planned versus implemented

| Programme slice | Planned | Current result |
|---|---|---|
| Tasks 1-4 | July source reconciliation, quality tooling, canonical runner and blocking workflows | Implemented and green in continuous integration |
| Tasks 5-7 | Isolated Playwright environment, deterministic desktop/mobile smoke and agent acceptance contract | Implemented and green |
| Tasks 8-10 | Question-specific Fyn gates, repetition guards, completion/stream parity and prompt overlays | Implemented and live-tested on desktop and `/m` |
| Staging gate | Deploy the immutable feature commit before merge and execute both acceptance manifests | Complete against `ce74507` |
| Tasks 10A onward | Remaining user-report, observability, retention, security, financial and release work | Not part of this pull request; still planned |
| Production release | Seven-day soak, rollback rehearsal, `dev` to `main`, production proof | Not started |

Two corrective commits were added during the staging gate:

- `0ca0b46` generates isolated registration-test credentials at runtime instead of keeping a synthetic password in the current source.
- `ce74507` stops the general dashboard requesting the Pro-only trust list. The route remains correctly protected; the dead lower-tier request was removed and covered by a regression test.

## Live acceptance

Both tests were run in the user's visible Google Chrome session at their request. The completed desktop and mobile result tabs were left open.

### Release smoke

- The public staging homepage rendered its real content.
- The Sign in link opened the login form.
- `/m` visibly booted its Fynla iframe.
- The direct `/m/app` pathway rendered the mobile dashboard.
- Repository validator result: `release-smoke: valid`; result record: valid.

### FYN-19079 repetition scenario

The standing quality-assurance user had completed onboarding, no active onboarding step, no campaign, zero goals, one Defined Contribution pension and one retirement profile.

Desktop:

- Exactly one user row and one assistant row persisted.
- Answer length: 1,046 characters.
- Tools dispatched once each: `get_tax_information`, `get_module_analysis`.
- No goals prerequisite gate.
- No repeated paragraph.
- No duplicate tool dispatch.

Mobile `/m`:

- Exactly one user row and one assistant row persisted.
- Answer length: 815 characters.
- Tools dispatched once each: `get_tax_information`, `get_module_analysis`.
- No goals prerequisite gate.
- No repeated paragraph.
- No duplicate tool dispatch.

Both surfaces resolved to the advice state and produced an equivalent single-answer outcome. The repository validator reports `fyn-19079-repetition: valid`; its result record is also valid.

The two run conversations were soft-deleted, temporary sessions and verification records were removed, and the quality-assurance user's original password and counters were restored.

## Automated gates

Green at `ce74507`:

- Logic guard.
- Lint.
- PHP Architecture, Unit, Feature, Integration and Eval lanes.
- Frontend tests.
- Builds.
- Browser smoke.
- Snyk's four security checks.
- Focused dashboard regression: 14 tests passed.
- Desktop and `/m` live acceptance result validation.

## Open release gates and findings

### GitGuardian

GitGuardian is the only red pull-request check. It points to historical commit `4914dfd`, where an isolated end-to-end test contained a synthetic password literal. Current source at `ce74507` no longer contains that value; `0ca0b46` generates the credential at runtime.

Incident: `34784237`. Because GitGuardian scans all 25 pull-request commits, it remains red even after the tip was corrected. Clearing it now requires one explicit decision:

1. Resolve the incident as a synthetic test value in GitGuardian; or
2. Rewrite/squash the feature branch history to remove the historical occurrence.

History was not rewritten because that is a disruptive operation and was not authorised.

### Dependency audit

`npm audit` reports 12 findings in the candidate dependency tree: 1 low, 5 moderate, 5 high and 1 critical. The affected families include Vitest, Vite, `tar`, `serialize-javascript`, `form-data`, DOMPurify, Babel and the Capacitor biometric plugin. Several suggested upgrades are breaking major changes. No automatic dependency rewrite was performed inside this staging deployment. Snyk remains green, so this discrepancy needs a bounded dependency-remediation decision rather than an unreviewed `npm audit fix --force`.

### Pre-existing staging AI audit chain

The staging AI audit-chain verifier was already invalid at row 44 before acceptance (`row_count` 22 at the break). The browser run did not change that state. This is a pre-existing staging-data finding, not a regression introduced by `ce74507`.

### Browser diagnostic

The initial headless WebKit harness timed out because its desktop answer-bubble selector did not recognise the completed response. Visible Chrome proved the application response completed correctly and quickly on both surfaces. Four console errors were emitted by an unrelated installed Chrome extension; Fynla itself emitted zero application console errors.

## Evidence

- Acceptance root: `docs/online-readiness/evidence/ce74507e62ea568a862dcb3de08d9cd8f1dc141e/`
- Visible Chrome captures: `docs/online-readiness/evidence/ce74507e62ea568a862dcb3de08d9cd8f1dc141e/chrome-visible/`
- Release smoke result: `release-smoke-result.json`
- FYN-19079 result: `fyn-19079-repetition-result.json`
- Surface comparison: `evidence/fyn-19079/08-surface-comparison.json`

The evidence is local and intentionally uncommitted. It contains no reusable password or verification code.

## Safe next step

Keep pull request 616 as a draft until CSJ chooses how to clear GitGuardian and decides whether the dependency-audit findings belong in this pull request or a separate remediation branch. After that decision, update the pull-request gate and request explicit merge approval. Do not merge automatically.
