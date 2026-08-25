---
id: W-0486
title: A preview write-block site does not check the bypass-preview-mode ability
mission: M-0001-state-truth
owner: build-lead
reviewers: [security-reviewer]
status: queued
severity: high
surfaces: [web, m]
source: surfaced by the Architecture suite while running W-0001's gates, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`tests/Architecture/PreviewBlockSitesCheckBypassTest` fails:

    it every preview write-block site checks the bypass-preview-mode token ability

The test exists precisely to guarantee every preview write-block consults the
bypass ability. It is red on `origin/dev`, so at least one site does not.

**This is an authorisation check, not a formatting rule**, which is why it carries
a security reviewer. Preview users are seeded personas and CLAUDE.md Rule 1 keeps
them isolated from real users. A write-block that does not consult the ability is
either blocking something it should permit or permitting something it should
block. Which of the two has not been established, and establishing it is the first
task here — not writing a fix.

Not caused by W-0001: the Architecture suite was run with those changes stashed and
gave identical results (4 failed, 173 passed, 4001 assertions).

## Acceptance

1. The offending site or sites identified by name.
2. The direction of the defect established — over-blocking or under-blocking —
   before any fix is written.
3. Fixed, with `PreviewBlockSitesCheckBypassTest` green.
4. `security-reviewer` run on the diff. Authorisation changes are in its remit.
5. Verified in a browser as a preview persona, on web and `/m`.

## Also red on the same run, untriaged

`OnlineReadinessDocumentsTest`, `StoreBoundary\InvestmentAccountStoreBoundaryTest`
and `StoreBoundary\MortgageStoreBoundaryTest` also fail on `origin/dev`. They are
outside this item and need their own triage.
