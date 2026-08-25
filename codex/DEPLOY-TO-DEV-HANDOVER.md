# Deploy-to-dev handover

## Outcome at pause

The feature-branch deployment and live staging gate are complete. The server is running exact commit `ce74507e62ea568a862dcb3de08d9cd8f1dc141e`, and desktop plus `/m` acceptance is green.

The feature has not been merged into `dev`. Pull request 616 remains a draft and requires an explicit CSJ merge decision.

Full detail: [DEPLOYMENT-REPORT-2026-07-13.md](DEPLOYMENT-REPORT-2026-07-13.md).

## Completed deployment actions

1. Pushed `ce74507` to `origin/codex/online-readiness-plan`.
2. Built both staging bundles locally with `./deploy/csjones-fynla/build.sh`.
3. Fast-forwarded the csjones checkout to the exact candidate.
4. Uploaded desktop and mobile bundles into isolated directories.
5. Checksum-verified both uploads before activation.
6. Switched bundles while preserving prior hashed chunks.
7. Cleared application, route, configuration and view caches, then rebuilt configuration cache.
8. Confirmed the server commit and both manifest hashes.
9. Ran visible Chrome desktop and `/m` acceptance.
10. Validated both result records with the repository validator.
11. Restored the standing quality-assurance user and temporary sessions.

## Remaining blockers before merge

### GitGuardian

Incident `34784237` points to a synthetic password literal in historical commit `4914dfd`. Current source no longer contains the literal and generates the test credential at runtime. GitGuardian still scans the historical commit.

CSJ must choose one:

- Resolve the incident as a synthetic test value in GitGuardian; or
- Authorise a squash/history rewrite of this feature branch.

Do not rewrite history without that decision.

### Dependency audit

The current candidate reports 12 `npm audit` findings, including 5 high and 1 critical. Several proposed fixes are breaking major upgrades. Snyk passes. Decide whether to remediate in this pull request or a dedicated dependency branch; do not run `npm audit fix --force` casually.

### Pre-existing staging data

The AI audit-chain verifier was already invalid at row 44 before the run. Acceptance cleanup left that state unchanged. Track this as a staging-data repair, not as a release regression from `ce74507`.

## Safe continuation order

1. Read the deployment report and inspect the two Chrome result tabs if desired.
2. Choose the GitGuardian resolution.
3. Choose the dependency-remediation scope.
4. Re-check GitHub status after those two decisions are applied.
5. Ask CSJ for explicit merge approval.
6. Only after approval, mark the pull request ready and perform the protected merge into `dev`.
7. Return the csjones checkout to `dev`, pull the merged commit, rebuild/upload if the merge SHA changes the bundle, and repeat the smoke check.

Pull request 616's description has already been updated to name `ce74507`, the green desktop and `/m` live gate, and the remaining blockers. It remains a draft.

## Evidence references

- Deployment report: `DEPLOYMENT-REPORT-2026-07-13.md`
- Acceptance root: `docs/online-readiness/evidence/ce74507e62ea568a862dcb3de08d9cd8f1dc141e/`
- General result: `release-smoke-result.json`
- Fyn result: `fyn-19079-repetition-result.json`
- Chrome screenshots and comparison: `chrome-visible/`
