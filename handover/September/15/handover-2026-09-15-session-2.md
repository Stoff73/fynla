---
type: handover
mode: session-end
date: 2026-09-15
session: 2
repo: fynla
branch: dev (main checkout); work lives in a worktree on feat/savetax-property-capture-form
---

# Session Handover — 2026-09-15, Session 2

## Where things stand

Two releases went to prod today from this session (main `fd5089acc` then `357e44e4b`): the refusal re-run + backstops (#854), the Save Tax property step with ISA cap counting and the rule 6 clause (#855), and then the property-capture repair (#857: multi-property extractor, gate segmenting, refused writes surfaced, null tenure trap, invented co-owner dropped, Free plan holds two properties). Live, CSJ's two-property sentence saved the buy to let and asked for the home's share; the share turn was proven in the director replay tests but the live re-test on csjones was polluted by an earlier failed attempt and then abandoned when CSJ changed direction. **CSJ's decision (17:01 BST): stop parsing free text for onboarding and capture property through a structured form in the chat.** That feature is specced, planned, and five of its twelve tasks are implemented, reviewed and pushed on `feat/savetax-property-capture-form` (tip `c7216000a`). The backend is complete except the controller wiring (Task 6); nothing is on csjones yet.

## Priorities for the next session

1. **Resume the SDD loop at Task 6** — the plan is `docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md`; the ledger, briefs and reports are copied to `handover/September/15/sdd-property-form/` (the live copies sit in the worktree's git-ignored `.superpowers/sdd/2026-09-15-savetax-property-capture-form/`). Use `superpowers:subagent-driven-development`: one implementer per task, a reviewer per task, fix rounds with scoped re-reviews. **CSJ's condition: check and take responsibility for every subagent's work** — read the diff yourself before dispatching the reviewer. Task 6 = controller reads `X-Fynla-Forms`, passes the flag and `form` through send/queue/stream/start (`task-6-brief.md`). Then 7–8 (web store, service, `FynCaptureForm.vue`, chat panel), 9–10 (`/m` mixin, api.js, component in both chat templates), 11 (build both bundles, deploy branch to csjones, **verify in CSJ's Chrome via claude-in-chrome, never headless** — web then `/m` per the `verify-m` skill, plus a native-style curl proving no `capture_form` without the header), 12 (PR to dev). Final whole-branch review on the most capable model, then `finishing-a-development-branch`.
2. **Rulings already made (carry into dispatches):** Ruling 1 — form state/validation/payload logic lives ONCE in `resources/mobile/utils/captureFormState.js` as an exported Vue mixin used by both `FynCaptureForm.vue` files (precedent: `FynQuickReplies.vue:58` imports `renderFynText` from the mobile bundle); Ruling 2 — two one-line template insertions in `Dashboard.vue`/`MobileChrome.vue` stand, the duplicated message loop is pre-existing debt for the PR body; Ruling 3 — request validation filters `CaptureForms::rules()` to submitted kinds (done, 2dfd7d802); Ruling 4 — Tasks 3+4 were one dispatch (done); Ruling 5 — empty-kind guard in `handleFormTurn` (done, a31df0697). Full text in the ledger.
3. **BLOCKED ON CSJ — the eleven mapping PRs #829–#839** (rebased stack, csjones gate results from 2026-09-15 morning). Still parked; CSJ has not said whether to resume the merge.
4. **Parked by CSJ:** native iOS renderer for the form ("I want to see how the forms work before we do anything with iOS"); the iPhone registration bounce; the web Retirement actions block; the typed-property share turn was never re-verified live on a clean csjones account (the replay tests cover it; CSJ moved on).
5. `tech-debt-session` and `vault-sync` were NOT run at session end (CSJ asked for the handover straight after Task 5). Adjacent items noticed today: the model writes `joint_owner_name` from placeholders (now dropped by the gate unless it appears as a proper noun); `/m` message-loop template duplicated in two files; `ai_messages.metadata` JSON column reorders keys (tests compare with `toEqual`).

## Context to load

- `docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md` — the twelve tasks with full code; Tasks 1–5 done, 6–12 remain. Read Tasks 6–12 only.
- `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md` — the binding spec (scope: Save Tax `campaign_property` only, web + `/m`, native untouched via `X-Fynla-Forms`).
- `handover/September/15/sdd-property-form/progress.md` — the SDD ledger: every ruling, every review finding, deferred minors, per-task completion lines. Trust it over memory.
- `handover/September/15/sdd-property-form/task-6-brief.md` — the next dispatch's requirements (and `task-7-brief.md` … `task-12-brief.md` alongside).
- `.claude/skills/subagent-driven-development` (plugin path: `~/.claude/plugins/cache/claude-plugins-official/superpowers/6.3.0/skills/subagent-driven-development/`) — the loop, its scripts (`sdd-workspace`, `task-brief`, `review-package`) and the three prompt templates.
- `/Users/CSJ/.claude/projects/-Users-CSJ-Desktop-fynla/memory/project_release_2026_09_15.md` — today's release facts (needs the second release and #857 appended — see Completed).

## Completed this session (all on prod unless stated)

- Morning: rule 6 "an answer is never an attack" clause + snapshot (4ccde3036); PR #855 (property step, ISA cap counting, no advice during onboarding, dividends column) and #854 merged; release #856 → main `fd5089acc`, prod deployed with the dividend migration and corpus; c.jones purged.
- Afternoon: PR #857 (`fix/property-capture-multi-entity`): `PropertyPhrasings` (one property vocabulary), extractor multi-property parse with per-entity ownership and rent/mortgage phrasings (money regex must start and end with a digit — a bare comma read as £0), gate segmenting by property declaration and `both/all/each` group clause, `capture_write_result` from gap-fill so refused writes are asked about and rescued next turn, `PropertyNormaliser` omits null tenure (NOT NULL trap), gate drops a co-owner name not written as a proper noun, Free property cap 1 → 2 (data migration `2026_09_15_170000` + seeder + tier tests). Batch 2766 passed / 0 failed. Release #858 → main `357e44e4b`, prod deployed (backup `~/release-backups/2026-09-15h/`), c.jones (user 707) purged again.
- Evening (branch only, NOT deployed): spec + plan committed; Tasks 1–5 of the form plan: `CaptureForms` (3b4e2e29d), request accepts `form` (9444913a4 + 2dfd7d802), corpus form turn (b47a8ab5d), director emits `capture_form` (39b4731b7), director `handleFormTurn` + `advanceAfterCapture` extraction + empty-kind guard (4b3e942eb + a31df0697). Each task reviewed by a subagent and read by the controller; all review findings fixed and re-reviewed.
- Memory: `project_release_2026_09_15.md` written and indexed (covers the first release; append #857/#858 and the form decision).

## Verification state

- Prod: home and `/m` 200 after both releases; migrations ran; `fyn:procedural:validate` lists the onboarding workflow active; log clean since deploy.
- csjones: on `dev` at `e4afd0d1c` (#857 merged), config cached. Test user 396 (`gate-0915f@example.com`, `MapTest2!`) parked at `campaign_property` with one buy-to-let row from the last live run.
- Form branch at `c7216000a`: PropertyCaptureFormTurnTest 9/9, CaptureFormsTest 10/10, SendAiChatMessageFormRequestTest 6/6, PropertyCaptureLiveSentenceTest 4/4, CaptureRefusalRetryTest 3/3, golden-master/section tests green per task reports. NOT run: any batch across families, Vitest, anything live.
- Not verified: the typed share reply on a clean csjones account (replay tests only); native untouched by design.

## Decisions and dead ends

- **CSJ 17:01:** free-text onboarding capture is the wrong shape; a structured form in the chat, same store, no model. Extractor/gate/backstop stay as fallback and for other steps. Scope: Save Tax property only. Native waits until CSJ has seen the form. Required fields carry an asterisk. Approach A (server-driven schema, one event, two renderers) approved over reusing the app modal or hand-built per-surface forms.
- **CSJ 16:36:** Free plan holds two properties (done, on prod).
- **CSJ 16:31/16:54/16:56:** never test headless — use CSJ's Chrome (claude-in-chrome) so they can watch; do not spend time on the chat path once the form direction is set.
- **CSJ 14:45–14:48:** reporting DB rows as "what Fyn said" without checking what was streamed is a lie by their definition. Check the stream filter and history filter before describing what the user saw. Feedback drafted via /feedback.
- Dead ends: the live "50%" retries on user 396 were polluted by an earlier attempt's rows (the evidence window prepends a stale "50%"); the gate passes the same evidence locally — the discrepancy was never resolved because CSJ redirected. Do not resume that investigation unless asked.
- Plan defects found by reviewers and ruled on: Task 2's `present` rule on the absent kind (Ruling 3); Task 3 before Task 4 would break typed tests (Ruling 4); empty-kind advance (Ruling 5); JSON key order in persisted metadata (test-only `toEqual`).

## Things that will bite you

- **The worktree is in the session scratchpad** (`/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint`) and may not survive. If it is gone: `git worktree prune` then `git worktree add <path> feat/savetax-property-capture-form` from the main checkout (never switch the main checkout off `dev`); copy `.env` and symlink or copy `vendor/` as before; re-create the SDD workspace with `scripts/sdd-workspace PLAN` and copy `handover/September/15/sdd-property-form/*` into it so the ledger's first line names the plan and completed tasks are skipped.
- The Bash permission classifier blocks plain ssh writes to prod; use the `ssh-fynla` MCP for prod commands and `rsync -azRc` for uploads. csjones ssh works with `~/.ssh/fynlaDev` once CSJ has `ssh-add`-ed it (they did today).
- A guard hook blocks any Bash command whose TEXT contains the forbidden artisan words, even inside a heredoc — write such documents with the Write tool.
- `X-Fynla-Forms` does not exist anywhere yet; only Task 6 adds it. Until Tasks 6–10 land, the property step is a `form` turn in the corpus but every client still gets the typed prompt (no header) — safe, and why nothing is deployed.
- `emitTurnForState` is now public; `handleUserMessage` has a sixth `?array $form` param.
- Never run foreground Pest while a background Pest run is going (test DB collision).

## Tech debt deferred

- `tech-debt-session` not run. From reviews: `CaptureForms` trailing-zero trimming duplicated between `pounds()` and the share format; `toolInputs()` reads `current_value` without a null guard; no test for the `$schema === null` form-branch fallback; global Pest helpers `formStepUser`/`formConversation`; `'selection' => 'savetax'` hardcoded in the failure-path `recordProgress`; the empty-kind guard skips `recordProgress` while the partial-failure branch calls it. All in the ledger as deferred minors for the final review.
- `/m` message-loop template duplicated in `Dashboard.vue:321` and `MobileChrome.vue:141` (pre-existing; Task 10 adds one line to each).

## Branch and deploy state

- Main checkout: `dev` at `e4afd0d1c` (== origin/dev; main `357e44e4b` is the same tree). Pre-existing dirty files not from this session (excalidraw diagrams, workforce logs, brief reports) left untouched.
- Feature branch: `feat/savetax-property-capture-form` at `c7216000a`, pushed, no PR yet.
- Prod: main `357e44e4b`. csjones: dev `e4afd0d1c`. Nothing from the form branch deployed anywhere.
