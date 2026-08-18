# July Updates Branch and Plan Inventory

**Verified:** 2026-07-10

**Remote refresh:** `git fetch --all --prune`

**Refs inspected:** 61 `origin/*` refs plus local branches and all reachable Git objects

**Production ref:** `origin/main` at `2e8357bef1c453da40e2c1991a462d8914b262e5`

**Development ref:** `origin/dev` at `e16ea5f89ed091fbce5371da2d253e9363e0ce2b`

## Finding

The complete July corpus contains 34 tracked artifacts: 31 Markdown/patch files and three screenshots. At branch tips it exists only on `origin/main` and its symbolic alias `origin/HEAD`. It is absent from `origin/dev` and from `codex/online-readiness-plan`.

No separate remote branch contains a different July Updates corpus. The relevant implementation branches for SaveTax, gamification, milestones, pensioncheck, the 6 July audit fixes, and life-event allocation are all ancestors of `origin/dev`; none is an unmerged hidden implementation branch.

## Plan disposition

| Source | Verified state | Master-programme treatment |
|---|---|---|
| `July3Updates/issues.md` | Fixed by PR #594, merge `3b0828e` | Preserve as user-reported acceptance evidence; rerun SaveTax regressions. |
| `gamification-recs-tasks-map.md` WP-1 to WP-6 | Implemented by PRs #596-#601 | Preserve the original defect contract; verify the merged one-actions model, capture integrity, activity feed, achievements, milestones, and campaign affinity. |
| `wp5c-milestones-spec.md` | Implemented by PRs #603, #605, and #606 | Verify the catalogue, pagination, nudges, and `/m`; close current desktop parity under Rule 19 in the continuation lane. |
| `savetax-recs-gamification-map.md` | Post-WP current-state map | Treat as the SaveTax/action/gamification regression contract. Its stated desktop and outbound-nudge gaps remain explicitly tracked. |
| `pension-campaign-map.md` and `pension-campaign-plan.md` | Implemented by PRs #607-#610; merge `6f965f1` | Do not rebuild. Run fresh funnel/re-entry regressions and close the documented polish/deferred list in the continuation lane. |
| `pensioncheck-patch-notes-technical.md` | Delivery proof for PRs #607-#610 | Use its live findings and deferred list as acceptance inputs. |
| `campaign-blueprint.md` and `campaign-playbook.md` | Shared campaign architecture | Canonical prerequisite for every later campaign. |
| `investment-campaign-spec.md` and `investment-campaign-plan.md` | Not implemented; no matching code or branch found | Execute after the initial production-readiness release as the next isolated campaign release train. |
| `estate-campaign-spec.md` and `estate-campaign-plan.md` | Not implemented; no matching code or branch found | Execute only after the investment campaign has completed its own release train. |
| `saveTax.md` and `pensionCampaign.md` | Current-state maps after PR #612 | Characterisation and browser-regression sources; do not reinterpret them as unimplemented plans. |
| `full-app-audit-2026-07-06.md` | Fix branches `audit-fixes-jul6` and `life-events-allocations` are ancestors of `origin/dev` | Re-verify fixes; carry every unresolved critical/high item into the blocker ledger. |
| July 7 blind-spot audit/spec/plan | Remediation programme not complete | Mapped into the online-readiness blocker tasks, including observability, queue, failures, GDPR, joint authorization, rollover, concurrency, scale, testing, and runtime support. |
| July 7 Fyn map/playbook/spec/plan | Only earlier substrate exists; the July remediation plan is not complete | Map P0 work into the initial blocker lane; add route, corpus, compliance, and eval closure; retain provider expansion as a separate continuation release. |
| `proposed-fyn-refusal-carveout.patch` | Unreviewed historical patch; later map says its core carve-out already shipped | Preserve for provenance only. Never apply it directly. |
| Handovers, user feature notes, and screenshots | Historical evidence | Restore unchanged and register as `evidence_only`; they are not executable plans. |

## Delivered branch proof

These remote branches are all reported by `git branch -r --merged origin/dev`:

- `origin/savetax-campaign-fixes`
- `origin/wp1-capture-integrity`
- `origin/wp2-one-actions-model`
- `origin/wp3-activity-feed`
- `origin/wp4-achievements-tidy`
- `origin/wp5-milestones`
- `origin/wp5b-upcoming-milestones`
- `origin/wp6-savetax-landing`
- `origin/pensioncheck`
- `origin/pensioncheck-b`
- `origin/pensioncheck-c`
- `origin/pensioncheck-fixes`
- `origin/audit-fixes-jul6`
- `origin/life-events-allocations`

The deleted `wp5c-milestones`, `wp5c-ii-uncap`, and `wp5c-iii-nudges` remote branch names are represented by merge commits `2bbb301`, `a4b202f`, and `c836fb9` in `origin/dev`; their work is not lost.

## Complete 34-artifact manifest

```text
July/July1Updates/handover-2026-07-01-session-1.md
July/July3Updates/campaign-blueprint.md
July/July3Updates/campaign-playbook.md
July/July3Updates/gamification-recs-tasks-map.md
July/July3Updates/handover-2026-07-03-session-1-clear-precompact.md
July/July3Updates/handover-2026-07-03-session-2-clear.md
July/July3Updates/issues.md
July/July3Updates/pension-campaign-map.md
July/July3Updates/pension-campaign-plan.md
July/July3Updates/savetax-recs-gamification-map.md
July/July3Updates/screenShots/accountEdit.jpeg
July/July3Updates/screenShots/dateOfBirth.jpeg
July/July3Updates/screenShots/fix1-reg-error-savannah.png
July/July3Updates/wp5c-milestones-spec.md
July/July4Updates/pensioncheck-feature-notes-user.md
July/July4Updates/pensioncheck-patch-notes-technical.md
July/July4Updates/proposed-fyn-refusal-carveout.patch
July/July5Updates/handover-2026-07-05-session-1.md
July/July6Updates/estate-campaign-plan.md
July/July6Updates/estate-campaign-spec.md
July/July6Updates/full-app-audit-2026-07-06.md
July/July6Updates/handover-2026-07-06-session-1-clear.md
July/July6Updates/handover-2026-07-07-session-1-clear.md
July/July6Updates/investment-campaign-plan.md
July/July6Updates/investment-campaign-spec.md
July/July6Updates/pensionCampaign.md
July/July6Updates/saveTax.md
July/July7Updates/blindspot-audit-2026-07-07.md
July/July7Updates/blindspot-remediation-plan.md
July/July7Updates/blindspot-remediation-spec.md
July/July7Updates/fyn-ai-blindspot-map.md
July/July7Updates/fyn-ai-prompting-playbook.md
July/July7Updates/fyn-ai-remediation-plan.md
July/July7Updates/fyn-ai-remediation-spec.md
```

## Integration rule

Gate 0 restores all 34 artifacts to the `dev` line and creates a machine-readable register with one disposition per artifact and one task mapping per executable work package. Delivered plans become regression contracts; outstanding remediation becomes launch work; unbuilt campaigns and provider expansion remain executable continuation release trains. No July plan may disappear merely because its original branch was deleted or its implementation was already merged.
