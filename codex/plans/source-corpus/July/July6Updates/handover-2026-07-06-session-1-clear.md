---
type: handover
mode: context-clear
date: 2026-07-06
session: 1
branch: main (docs/tooling) / dev @ 9c9e7d2 (code, PR #612 merge)
---

# Context Clear Handover — 2026-07-06, Session 1

## Immediate state

Session fully wrapped — nothing mid-task. The last deliverable (testing gates added to both new campaign plans) is committed and pushed (`4ba6ba1`); the working tree is clean. All open items below are CSJ decisions, not in-flight work.

## The thread

1. **Tooling overhaul landed** — the previous session's uncommitted 8-improvement batch was committed (`5b8f844` on main) + cherry-picked to dev as **PR #611 (MERGED, dev `4bd1f97`)**: 4 safety hooks (design-lint, prod-guard, env-guard, m-parity), dangerous-command-guard extensions (db:wipe/route:cache/optimize), release + verify-m skills, contributor-memory removal, settings prune. All 16 hook payload tests green. Deliberately skipped: frontend formatter (no Prettier in repo — CSJ dependency decision), hookify rules (bash hooks are fail-closed).
2. **Campaign maps & audits written** — `July/July6Updates/saveTax.md` + `pensionCampaign.md`: full current-state maps (funnel → registration → pull-through → Fyn decision trees → 13-scenario eventuality maps → file inventories), grounded by 5 research agents against dev.
3. **All 15 actionable audit findings FIXED** — **PR #612 (MERGED, dev `9c9e7d2`)**, built in an isolated worktree, 15+2 new tests, full suite **5,506 passed / 30 skips**. Then **browser-tested live on csjones** (Playwright, real xAI): P1 phone deep-link, P2 recap-edit → gap-walk continuation (DB-verified £26,000 edit), P3 /m re-entry + pills, P4 pause-resume at parked step, P6 history skip, S3 step labels, S5 202-queued honest handling (tinker lock trick), S6 utm→signup_source (full funnel registration). Three live-found bugs fixed in the loop: `UserResource` missing `active_campaign`, mid-walk bare-start 409, stale /m store mirror. Post-terminal integrity: completed_at byte-identical, award count 1. Both audit docs carry per-flag `[FIXED — PR #612]` statuses.
4. **Deploy state**: csjones = dev = `9c9e7d2`, fresh /m bundle (manifest → `main-9jtOH7DL.js`), caches re-cached correctly. **Prod untouched — release window now #581–#612.**
5. **New campaign specs + plans written** (CSJ-requested, opus-implementable): `investment-campaign-spec/plan.md` (campaign3/`investmentcheck`) + `estate-campaign-spec/plan.md` (campaign4/`inheritancecheck`), research-grounded (real strategy ids, readiness, tools, /m screens), 4 slices each, 30-item trap tables, **Gates 0–7 testing ladder** (added last, `4ba6ba1`). Key spec facts: investment keeps income+expenditure sections (BLOCKING) + risk-profile synchronous-ensure trap; estate needs ONE new tool (`capture_will_status`) + `/estate`+`/net-worth` in ONBOARDING_NAV_ROUTES + Tier-2 teaser landing flagged.

## Files touched (all committed + pushed)

- main: `5b8f844` (tooling), `66555ff`/`db478be` (audit docs), `61630d8` (campaign specs/plans), `4ba6ba1` (testing gates) — plus this handover commit.
- dev: PR #611 merge `4bd1f97`, PR #612 merge `9c9e7d2` (20 files + 3 follow-up commits).
- Vault: July6Updates mirrored (6 docs); worktrees `fynla-audit-fixes` + `fynla-campaign-research` created and REMOVED (registry pruned). `fynla-coala` + `fynla-fixes` intact, deliberately kept.

## What the next Claude needs to know

- **julycsj3 (id 168) data drift**: Personal Pension now £26,000 (was £25,000 — the P2 live edit); also gained a `has-no-workplace-pension` answer + pension history untouched. Standing user, drift is normal; flag only if CSJ asks.
- **Disposable test user** Audit Tester (id 188, audit.e2e.jul6@example.com) soft-deleted on csjones; one queued test message on conversation 143 left to TTL-expire (harmless).
- **PR #612 had NO formal /code-review pass** — it shipped on tests + live E2E (audit-fix work). Optional: a retrospective review pass if CSJ wants belt-and-braces.
- **Explanatory-output-style plugin** is disabled in user settings but still injected this session — if it appears NEXT session too, the disable isn't taking; investigate then, not before.
- Skipped-by-design audit items stand: S4 unbounded retries, S8 vestigial states, P9 unused funnel keys (documented in the audit docs).
- The campaign plans mandate: if BOTH new campaigns get green-lit, land one fully before starting the other (shared files).

## Pick up from here

Nothing mandated. The open CSJ decisions, in likely priority order:
1. **dev→prod release** — window now **#581–#612** (one migration `users.active_campaign`; full rsync reconcile + corpus + build + m-build per DEPLOY.md + prod-drift memory).
2. **Green-light a new campaign build** — start from `July/July6Updates/investment-campaign-plan.md` OR `estate-campaign-plan.md` (spec first, plan second, gates ladder binding). Confirm campaign names/URLs (DRAFT: `investmentcheck` / `inheritancecheck`) at funnel-build time.
3. **Pensioncheck polish list** (carried): DRAFT copy, OG images, carry-forward blessing, post-terminal affinity durability, `proposed-fyn-refusal-carveout.patch`.
4. Frontend formatter (Prettier stack) — say the word and it gets set up.
