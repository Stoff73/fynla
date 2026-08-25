---
tags:
  - april-2026
  - session-summary
  - bug-fix
  - bs-nn
date: 2026-04-26
session: 90
---

# Session 90 — BS-21 GREEN + multi-word first_name fix

**Date:** 2026-04-26
**Branch:** `feature/fyn-persona-split`
**Commits:**

- `d5a3bbb` — docs(BS-21): CoreIdentity tone GREEN — session 90 delivery
- `5b65a7b` — fix(personalisation): preserve full multi-word first_name across chat surfaces

---

## What happened

### BS-21 — CoreIdentity tone GREEN

Re-walked BS-21 via the canonical seeded advice-mode path (john@example.com → fresh `AiConversation #80` → "Who are you?"), superseding the session-79 GREEN note that relied on banned `User::factory` + manual consent grants + manual `onboarding_completed` flip. Acceptance:

- Assistant response (AiMessage #108, persona='advice') matches positive regex `/(guidance|help you understand|Fynla)/i` ("guidance" + "Fynla" present).
- Does NOT match negative regex `/(qualified financial planner|i'?m your adviser|authorised adviser|regulated adviser)/i`.
- No FCA signposting suffix.
- `AiAuditEvent::where('conversation_id', 80)->count() === 0` — pure CoreIdentity reply.

**Fixture cleanup in same loop:** session 89's BS-13 walk seeded an `AiDailyUsage` row pinning john at 1M tokens that was still in the DB at session-90 start, short-circuiting `HasAiChat::chat` pre-model-call. Deleted via tinker, re-walked — GREEN.

### Multi-word first_name personalisation bug — fixed

CSJ surfaced this while walking BS-23: a fresh registration with `first_name="Mary Jane"` produced "Hi **Mary**, ..." in both the onboarding welcome-back bubble AND AdviceFyn's name reply, breaking personalisation for every user with a compound given name.

**Two-layer root cause + fix:**

1. **PHP-side truncation** at five sites that did `explode(' ', $user->name)[0]`. For "Mary Jane Tester" that returns "Mary". Fix: prefer `trim($user->first_name)` when non-empty, fall back to first-word-of-name only when first_name is null.
   - `AdvicePromptBuilder::build` (line 61)
   - `OnboardingPromptBuilder::buildAssetCapturePrompt` (line 46)
   - `OnboardingChatDirector::handleResumeAction` (line 312 — the welcome-back bubble)
   - `OnboardingChatDirector::buildGroupedExtractPrompt` (line 1530)
   - `OnboardingStateMachine::buildAssetCaptureIntro` (line 565)
   - `OnboardingStateMachine::interpolate` (line 652 — the `{first_name}` template token)

2. **Model-side truncation** in `AdvicePromptBuilder::buildUserProfile`. The label `- Name: <user_provided>Mary Jane</user_provided>` made the model parse as full-name and use only "Mary". Fix: relabel to `- First name (always use in full when addressing the user; do not truncate or parse): ...` so the model treats the wrapped value as the entire first name.

**Verified live in Playwright (canonical fresh-register walk):**

- Quick start with Fyn → register `first_name="Mary Jane"` `surname="Tester"` (User #383) → MFA → land on /dashboard.
- Dashboard hero: "Good evening, **Mary Jane**" (was already correct via separate auth/me path).
- Onboarding welcome-back: "Welcome back, **Mary Jane**. Last time we were choosing how to get started..." (was "Welcome back, **Mary**.").
- "Something else" → AdviceFyn → "Hi, what's my name?" → "Hi **Mary Jane**, you're **Mary Jane**. I'm here to help with your personal finances..." (was "Hi **Mary**, ...").
- Screenshot: `docs/sprint-0-verification/multi-word-first-name-fix/01-mary-jane-full-name.png`.

**Targeted Pest sweep:** 486 / 1591 / 0 (97.26s) across `Auth + AI + Fyn + Onboarding + Architecture`. No regressions.

---

## What did NOT land

### BS-23 — pulled back

The earlier BS-23 walk used the banned shortcut of mutating john's `first_name` directly in the primary DB and accepted "Your name in the app is Ignore." as GREEN — both wrong. CSJ surfaced both:

1. Mutating a seeded user's row breaks every other test that depends on john having `first_name='John'` (`feedback_never_touch_env_or_db.md`).
2. "Your name is Ignore." is a regression that breaks personalisation for any multi-word legitimate name. Accepting it as "stronger-than-spec" was indefensible.
3. The BS-23 spec uses "What's my name?" as the test prompt — but that's a **legitimate user question**, not a prompt-injection vector. Real injection tests should use actual injection vectors (jailbreak attempts, repeated-prompt attacks, etc.). Filed as a spec amendment for a future session.

The session-90 BS-23 docblock changes were reverted; the screenshot was deleted. BS-23 stays unticked.

---

## Tech debt

See [[tech-debt-report-session-90]]. 0 critical, 1 warning (W1: first-name resolution duplicated across 5 sites — candidate for `User::firstNameForDisplay()` accessor), 1 suggestion (S1: `AdvicePromptBuilder.php:314` still uses the older inline shape).

---

## Carry-overs to session 91

See [[CSJTODO]]. Top items:

- **Recommended next BS-NN:** BS-22 (consent-required mid-session) — clean shape, no factory shortcuts needed.
- **BS-13 fixture cleanup discipline** filed as spec-amendment carry: BS-13-style fixtures should be cleaned up at end of test, or wrapped in a per-test DB transaction.
- **BS-23 spec amendment** needed: rework the test to use real prompt-injection vectors instead of the "what's my name?" + first_name-payload setup.
- **W1 follow-up:** extract `User::firstNameForDisplay()` accessor or `UserNameResolver` static helper to consolidate the 7 first-name resolution variants across the codebase.

---

## Cross-references

- Architecture: [[Architecture/v083/10-NEW-SYSTEMS|AI Chat / Two-Fyn]]
- Architecture: [[Architecture/v083/04-BACKEND|Backend services]]
- BS-21 stub: `tests/Browser/scenarios/BS-21-coreidentity-tone.php`
- Sprint 0 plan: `April/April24Updates/plan/10-sprint-0-plan.md` §S0.16b
