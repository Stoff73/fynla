---
type: handover
mode: context-clear
date: 2026-05-18
session: 12
branch: fynPromptRework
trigger: context-handover skill (tripwire ~264k / >97.5% of 250k budget)
---

# Context Clear Handover — 2026-05-18, Session 12

## Immediate state

All work COMPLETE, browser-verified GREEN under unified, committed
(`8bc5f6d`) and pushed. Tree clean, `fynPromptRework` 0/0 vs origin.
PR #335 (fynPromptRework → dev, OPEN) updated and ready for CSJ
review/admin-merge to deploy. CSJ's last instruction: "commit and pr so
we can deploy this branch, then move into the tech debt" — commit+push
DONE, PR #335 serves as the deploy path (no new PR needed).

## The thread

- Auto-resumed handover-11. Ran tech-debt-session on the full PR #335
  delta (carried, interrupted sessions 10/11). Report written to
  `tech-debt-report.md`: 0 critical at audit time, 2 warnings (W1
  PII/bloat, W2 billing), 4 suggestions. Verified-clean items recorded.
- CSJ reviewed: W1 → defer (note for future purge); W2 → "billing MUST
  work, this is the standing law, why is this so hard" (visible
  frustration — Fyn must reach EVERY app surface).
- Diagnosed W2 as a **3-layer** regression, not one deleted block:
  (1) PR #335 deleted `<billing_guidance>` from static FynSystemPrompt
  with no per-turn replacement; (2) `QueryClassifier` step 2 NAVIGATION
  swallowed billing phrasings before step 3 BILLING; (3) `QuerySchemas`
  BILLING too narrow (no bare `billing`/`subscription`).
- Fixed all three, parity-with-legacy, per CSJ's stated mechanism
  (context into the per-turn assembler layer, classification-gated):
  `FynContextAssembler` billing layer (reuses promoted-public
  `AdvicePromptBuilder::isBillingQuery`/`getBillingGuidance` verbatim,
  grouped with the KYC parity layer); `QueryClassifier` billing-beats-
  NAVIGATION carve-out; `QuerySchemas` `/\bbilling\b/` +
  `/\b(?<!isa\s)subscription\b/` (ISA-subscription guarded).
- +3 FynContextAssemblerTest + +7 QueryClassifierTest cases. 44/44
  focused, 292 passed full AI/Fyn. Browser-verified GREEN as
  chris@fynla.org under unified: "show me my invoice" → classified
  `billing` → `<billing_guidance>` in assembled_context →
  get_subscription_status + list_invoices fired → pinned response shape.
- Rejected: re-adding billing to the static prompt (re-bloats cache-hit
  prompt every non-billing turn); ripping out chatNavigationRouter (the
  /settings/subscription redirect is the INTENDED Subscription
  Management CTA from the tool result, not a hijack — confirmed via DB).

## Files touched this session

Code (in `8bc5f6d`):
- `app/Services/AI/Fyn/FynContextAssembler.php` — billing parity layer
- `app/Services/AI/AdvicePromptBuilder.php` — isBillingQuery/getBillingGuidance private→public
- `app/Services/AI/QueryClassifier.php` — billing beats NAVIGATION (step 2)
- `app/Constants/QuerySchemas.php` — BILLING bare-noun patterns
- `tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php` — +3 cases
- `tests/Unit/Services/AI/QueryClassifierTest.php` — +7 cases
- `tech-debt-report.md` — PR #335 full-delta audit

Memory (not git, `~/.claude/.../memory/`):
- NEW `feedback_fyn_reaches_every_surface.md` (the standing law)
- NEW `project_ai_messages_forensic_columns_need_purge.md` (W1 deferred)
- REWROTE `reference_unified_prompt_has_no_billing_layer.md` (now RESOLVED)
- MEMORY.md index updated (+2 entries)

## WIP commit

- None. Work is in proper feature commit `8bc5f6d`, pushed. Tree clean.

## Open decisions

None. CSJ instruction (commit + pr + deploy) actioned: `8bc5f6d` pushed,
PR #335 updated. CSJ to review/admin-merge PR #335 → dev when ready
(do NOT self-approve — `feedback_no_self_approval`,
`feedback_main_via_dev_only`). Deploy is CSJ-gated.

## Pick up from here (auto-continue contract)

CSJ said: "then move into the tech debt." Next session should:

1. **Move into the tech-debt work.** `tech-debt-report.md` (repo root)
   holds the PR #335 audit. Priority items:
   - **C1 (the one actionable now): PR #335 ships a RED test** —
     `tests/Feature/Fyn/Eval/CassetteModelProvenanceTest.php` fails: 11
     cassettes stranded under `xai/grok-4-1-fast-reasoning/` while config
     is `grok-4.3`. Pre-existing, orthogonal to billing. Fix:
     `php artisan eval:record --providers=xai` then delete the stale
     `tests/.../xai/grok-4-1-fast-reasoning/` dir, commit onto
     fynPromptRework (PR #335) before merge. Confirm with CSJ this is
     the intended path (re-record vs delete-only) — it changes recorded
     fixtures.
   - S2/S3/S4 cosmetic suggestions (method-in-v-for, inline synonym map,
     unused catch binding) — low priority, CSJ's call.
   - W1 is DEFERRED (do NOT action — memory
     `project_ai_messages_forensic_columns_need_purge`).
2. Standing: PR #335 awaits CSJ review/admin-merge → dev (deploy path).
   PR #317 (release dev→main) parked on freemium refactor
   (`project_pr317_gated_on_freemium_refactor`).
3. Carried doc backlog (low priority): KycGateChecker delta doc-fix;
   pre-existing `fynlaFeatu*Modules` rename fate (not ours).

## What the next Claude needs to know

- **Do NOT re-verify the billing fix — browser-verified GREEN this
  session** (DB ground truth: conv 36, chris@fynla.org, classified
  billing + both tools + `<billing_guidance>` in assembled_context +
  pinned response). Evidence in this session's transcript.
- The standing law: read `feedback_fyn_reaches_every_surface.md`. If
  it's in the app, Fyn reaches it; unified moves HOW context is
  delivered (FynContextAssembler per-turn layer), never deletes a
  capability. A deleted legacy layer with no assembler replacement is a
  CRITICAL parity regression. Do NOT re-litigate billing with CSJ.
- The chatNavigationRouter `/settings/subscription` redirect on a
  billing query is BY DESIGN (Subscription Management CTA from the
  get_subscription_status tool result, AdvicePromptBuilder:293-296) —
  not a hijack. Don't "fix" it.
- Pest billing/classifier tests run with `DB_DATABASE=fynla_test
  ./vendor/bin/pest` (isolated test DB — never `--env=testing`).
- Dev servers were started this session (:8000 Laravel, :5173 Vite,
  `public/hot` fresh). Do NOT `pkill -f vite` (kills sibling project).
- Worktrees: `tender-bassi-375ee8` on `freemium` (sub-project 2, leave);
  `silly-dubinsky-f02c05` stale 2026-05-12 commit, clean, not in any
  handover — leave (no-auto-delete rule).
- `john@example.com` still has £2,300/mo ExpenditureProfile from
  session-10 (harmless, db:seed-restorable). Tested as chris.

## Branch / deploy state

- Branch: `fynPromptRework`
- Behind origin: 0
- Ahead of origin: 0 (all pushed incl. `8bc5f6d`; this handover commit
  will be +1 until Phase 7 push)
- Deploy status: Not deployed. PR #335 (fynPromptRework → dev) OPEN and
  updated, awaiting CSJ admin-merge. Nothing built/uploaded this session.
