---
type: handover
mode: context-clear
date: 2026-04-21
session: 2
branch: onboardingFyn
---

# Context Clear Handover — 2026-04-21, Session 2

## Immediate state

Just finished producing the Fyn Persona Split PRD (`April/April21Updates/PRD-fyn-persona-split.md`, 404 lines) after a full brainstorm → plan → codebase-audit → amend → PRD cycle. PRD, amended plan, and amended spec are all mirrored to the fynlaBrain vault. User invoked `/session-end context-clear` expecting the next session to continue reviewing / executing the PRD.

## The thread

1. Brainstormed the Fyn persona split (advice vs data_capture) via `superpowers:brainstorming` — decided wide-scope intent-driven personas with structured tool-call handoffs and a new `FynPersonaOrchestrator`.
2. Wrote the design spec (`docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md`) and committed as `48a30e7`. Revised once with the user to expand tool inventory and bring onboarding migration + classifier + chat UI in scope — committed as `11d82e5`.
3. Generated a 40-task implementation plan via `superpowers:writing-plans`, saved at user's request to `April/April21Updates/plan-fyn-persona-split.md` (folder is gitignored — vault-synced instead).
4. Added three more feature areas through user feedback: onboarding UX overhaul (wide chat, profile review pauses, spouse skip, multi-job, retraction), conversation memory, resume-from-where-left-off (flagged as broken), and fact parking for gap-filling follow-ups.
5. Ran the codebase audit per `/prd-writer` skill with `feature-dev:code-explorer` and `feature-dev:code-architect` in parallel. Surfaced 19 conflicts/architectural concerns.
6. Interviewed the user in 3 rolling batches of 3 questions each. Key decisions: keep `OnboardingChatDirector` + `OnboardingPromptBuilder` (don't absorb/delete), use existing `LastingPowerOfAttorney` model (don't create a new one), add columns to `wills` table, create new `FynOnboardingChat.vue` (leave `AiChatPanel.vue` for post-onboarding), action endpoint replaces sentinel-string protocol, reuse existing `QueryClassifier`, drop `OnboardingMemoryExtractor` in favour of parking-as-memory, two-flag set (`FYN_PERSONA_SPLIT` + `FYN_CLASSIFIER_FAST_PATH`).
7. Amended spec (committed `455cba3`) and plan (AMENDMENTS block at top of plan file). Wrote the PRD. Mirrored all three to `fynlaBrain/April/April21Updates/`.

## Files touched (this session)

**Committed on onboardingFyn:**
- `docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md` — three commits: `48a30e7` → `11d82e5` → `455cba3`.

**Gitignored (in `April/April21Updates/`, not tracked by repo — lives in vault):**
- `April/April21Updates/plan-fyn-persona-split.md` — 6,037 lines. Has AMENDMENTS section at the top overriding 19 audit-flagged items in the original 40 tasks.
- `April/April21Updates/PRD-fyn-persona-split.md` — 404 lines, all 9 canonical sections populated.

**Vault mirrors (not a git repo):**
- `fynlaBrain/April/April21Updates/spec-fyn-persona-split-design.md`
- `fynlaBrain/April/April21Updates/plan-fyn-persona-split.md`
- `fynlaBrain/April/April21Updates/PRD-fyn-persona-split.md`

**Ancillary:**
- `deploy/deployRevolut.md` — edited then reverted (user pointed out the edit was wrong; table entry for `c.jones@csjones.co` restored). No net change.
- Local DB: deleted users `c.jones@csjones.co` (id 134) and `slaterjoneschris@gmail.com` (id 138) + orphaned subscriptions / conversations / family members. Production untouched.

## What the next Claude needs to know

1. **The spec is authoritative**, not the plan's original 40 tasks. The plan file has an AMENDMENTS block at the top that overrides conflicting task bodies. Read the spec first, then the amendments, then task bodies for reference only.
2. **`OnboardingChatDirector` and `OnboardingPromptBuilder` stay.** Earlier plan/spec revisions proposed absorbing them into the orchestrator and deleting them — that was reversed after user pushback. The orchestrator handles post-onboarding ONLY. The director is extended in place with new states and features.
3. **`LastingPowerOfAttorney` already exists** with the correct schema. The plan's original Task 10 (creating a new `PowerOfAttorney` model) is void. Only the AI tool definitions + handlers are new.
4. **`QueryClassifier` is the classifier.** No new `FynIntentClassifier` class. Promote `QueryClassifier::classify()` to run at the orchestrator's dispatch entry.
5. **`conversation_id`, not `ai_conversation_id`** — existing column name on `ai_messages`. Plan had this wrong everywhere.
6. **`CoordinatingAgent::chatWithPromptOverride()` has 8 params, not 5.** Tools go in `toolsListOverride`, not `allowedTools`. Existing signature at `app/Agents/CoordinatingAgent.php:98-107`.
7. **Resume flow is broken in production** — user explicitly flagged it. Fix lives in director + new action endpoint (not in orchestrator).
8. **The `April/` folder is gitignored.** Plan and PRD live there and in the vault. Only the spec gets committed to the repo.
9. **User prefers 2-3 questions per rolling interview batch**, not 15 at once. They catch details; bulk Q sets get skimmed.
10. **Browser testing law is absolute** — "tested" means CLICK/FILL/SUBMIT in Playwright. This PRD hasn't been implementation-tested yet (only spec/plan/PRD written).

## Pick up from here

Next session should:

1. Read the spec (`docs/superpowers/specs/2026-04-21-fyn-persona-split-design.md`) and the AMENDMENTS section at the top of the plan (`April/April21Updates/plan-fyn-persona-split.md`) and the PRD (`April/April21Updates/PRD-fyn-persona-split.md`) — in that order.
2. Decide with the user: review the PRD one more time before implementation starts, OR begin execution via `superpowers:subagent-driven-development` (Tasks 1–3: config/fyn.php + CaptureContext + HandoffContract — foundation, no code-reference dependencies).
3. If executing, **branch first**: `git checkout -b feature/fyn-persona-split` off current `onboardingFyn`. Do not execute on `onboardingFyn` directly.
4. First-commit ordering — Task 1 (config flags) and Task 2 (CaptureContext value object) can run in parallel; both are low-risk foundation work with no dependencies.
