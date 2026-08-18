# Codex continuity bundle

Captured on 13 July 2026 at 16:10 BST so the online-readiness work can be resumed in another Codex surface without reconstructing the thread.

## Start here

1. Read `CURRENT-STATUS.md` for the exact repository and pull-request state.
2. Read `DEPLOY-TO-DEV-HANDOVER.md` before taking any release action.
3. Read `sessions/SESSION-2026-07-13.md` for the work completed immediately before this bundle was created.
4. Use `plans/programme/2026-07-10-online-readiness-programme.md` as the canonical implementation plan.
5. Read the repository-root `AGENTS.md` before changing code or running a deployment.

## What is preserved

- The six current online-readiness designs, plans, inventory and user-testing reconciliation documents.
- The complete 34-artifact July source corpus registered by the programme, including all July handovers and supporting screenshots.
- Every existing July handover in a dedicated `sessions/existing/` tree.
- Reconstructed session summaries for 10, 11, 12 and 13 July from the pushed commit history.
- The online-readiness audit ledger, source register, release manifest, quality baseline and browser runbook.
- The latest local Playwright report and machine-readable results.
- A live snapshot of pull request 616 and all current checks.

## Repository safety

- Active branch: `codex/online-readiness-plan`.
- Current commit: `838bc14347ffe8f72d66b8db8fec7f2faa1ae0c2`.
- The commit is pushed and exactly matches `origin/codex/online-readiness-plan`.
- The branch is 23 commits ahead of `origin/dev` after a fresh fetch.
- All implementation work was committed before this bundle was created.
- This `codex/` bundle is deliberately local and untracked. It was not added to pull request 616.
- No merge, deployment, database action, cache action or server mutation was performed while creating the bundle.

## Directory map

- `plans/programme/`: canonical current designs and implementation plans.
- `plans/control/`: audit, release and test-governance records.
- `plans/canonical/`: the current unified Fyn contract.
- `plans/source-corpus/`: exact copies of the registered July source artifacts.
- `sessions/`: reconstructed and previously written handovers.
- `evidence/`: pull-request, repository and Playwright state.
- `manifests/`: bundle inventory and source mapping.

Historical May and June rolling files at repository root are intentionally not treated as current state. They remain available in their original repository locations, while this bundle focuses on the online-readiness programme and its registered July inputs.
