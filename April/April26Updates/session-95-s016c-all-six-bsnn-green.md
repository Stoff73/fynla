---
tags:
  - april-2026
  - sprint-0
  - browser-testing
  - bug-fix
date: 2026-04-26
session: 95
---

# Session 95 — S0.16c CLOSED: All 6 BS-NN re-walks GREEN against post-refactor chat panel

## Summary

Drove the six remaining S0.16c re-walks (BS-01, 02, 04, 06, 07, 10) end-to-end against the post-`ffc9c3f` shared `AiChatPanelShell` body. All GREEN. Two production fixes shipped in same loop during BS-04 after the resume flow broke on Devon's second sign-in (per CSJ correction: "the resume needs to work as many times as needed, even if the user updates records and is in a different spot from the last resume").

S0.16c plan checkbox flipped to `[x]`. Sprint 0 is now complete except for:
- BS-18 third assertion (single post-deploy walk on csjones.co/fynla after the next dev push)
- S0.17 verification rollup

## Walks

| Scenario | User | Conv | Acceptance |
|----------|------|------|------------|
| BS-01 | Laury Greenwood (#449) | #122 | Full path-choice→done walk → /protection terminal, Aviva £300k policy. 13 fresh `s95-*` screenshots covering all 13 states including the documented `/profile` profile_review_family + profile_review_expenditure pauses. |
| BS-02 | rolls up via #449 | n/a | User #449 ↔ User #450 (Angela) bidirectional spouse_id, FamilyMember #223 + #224 with linked_user_id matching, ai_audit_events #432/#433 (capture_spouse_details dispatched + persisted). Fresh /profile Family tab snapshot showing Angela with both Spouse + Account Linked badges. |
| BS-04 | Devon Marsh (#451) | #124 | Full Continue + Something else paths verified across two sign-out / sign-in cycles. AdviceFyn ISA answer post-pause. Two product fixes shipped (see below). |
| BS-06 | Bryony Stoneleigh (#452) | #125 | INV-2.2.6 captured at exact moment of base_personal commit: `parked_facts=NULL`, users.dob+marital set, step advanced to base_spouse. |
| BS-07 | rolls up via #449 | #126 | AdviceFyn answers "Your current net worth is £0..." with zero quick_replies. Confirms AiChatController dispatches to AdviceFyn (not OnboardingDirector) when `onboarding_completed=true && step=null`. |
| BS-10 | rolls up via #449 | #127 | Exact canonical refusal "I'm able to help you with your finances. Medical advice is out of scope." with `AiAuditEvents=0` (QueryClassifier OUT_OF_REMIT short-circuit). |

## Discipline

- All bubble + skip-link clicks via `browser_click` against snapshot refs ONLY.
- MFA digits via `browser_press_key` (each digit individually — never `browser_type` of the whole code).
- Free-text via `browser_type` + submit:true.
- ZERO `browser_evaluate` for any interaction. Only used for read-only Vuex / DOM inspection per `critical_browser_testing_law.md`.

## Bug-fix-in-loop during BS-04

**Symptom:** Devon (#451) signed back in after dependants_detail capture; chat panel showed empty "Hi, I'm Fyn" state instead of the welcome-back greeting. `getOnboardingStatus` returned `conversation_id: null` for conv #124 even though it was the right conversation.

**Root cause:** `OnboardingChatDirector::getOnboardingStatus` and `AiChatController::startOnboarding` both filtered the conversation lookup by `where('title', 'Onboarding')`. The conversation title legitimately gets updated as the conversation evolves (HasAiChat updates from a user message), so the filter started returning null after the first user message. By Devon's second sign-in his conv title was "Mia 8 child, Owen 5 child" — not "Onboarding" — and the resume path silently fell through.

**Fix #1 (3 files):**
- New `AiConversation::scopeOnboarding(Builder)` filtering on `metadata->source = 'fyn_onboarding'` (the immutable flag set at creation).
- `OnboardingChatDirector::getOnboardingStatus` uses `->onboarding()->latest('id')`.
- `AiChatController::startOnboarding` resume branch uses the same scope.

**Fix #2:** `describeStep` only had cases for the early states; users paused at `profile_review_family`, `profile_review_expenditure`, `base_employment_more`, or `base_retirement_date` got the generic "mid-onboarding" fallback. Added all four cases so the welcome-back greeting reads per-state for every saved step.

**Re-verified end-to-end:** Devon's second sign-in fired the welcome-back greeting "Last time we were reviewing your family details" with action_bubbles=true [Continue, Something else]. Click Something else → `onboarding_fyn_step=NULL`, `paused_at_step='profile_review_family'` preserved, "Of course — what can I help you with?" emitted. Free-text "What's the ISA allowance for 2025/26?" routed to AdviceFyn (substantive ISA answer, no onboarding bubbles).

## Pest baseline

```
Tests:    529 passed (1968 assertions)
Duration: 113.62s
```

No regressions. Fixes are additive (new scope helper + new match arm cases).

## Commits

- `dbdaa77` — fix(onboarding): resume conversation lookup pivots on metadata.source, not mutable title
- `6c9e07d` — docs(BS-NN): session 95 S0.16c re-walk delivery notes — all 6 GREEN against shared chat panel body

## Files changed

**Production code:**
- `app/Models/AiConversation.php` — +15 lines (`scopeOnboarding` helper).
- `app/Services/Onboarding/OnboardingChatDirector.php` — +5/-1 lines (scope pivot + 4 describeStep arms).
- `app/Http/Controllers/Api/AiChatController.php` — +4/-1 lines (scope pivot in startOnboarding resume branch).

**Test docs:**
- `tests/Browser/scenarios/BS-{01,02,04,06,07,10}-*.php` — session-95 GREEN delivery notes (no test code change; docblocks only).

**Screenshots (27 total):**
- `docs/sprint-0-verification/BS-01/s95-{01..13}-*.png` (13)
- `docs/sprint-0-verification/BS-02/s95-08-family-tab.png` (1)
- `docs/sprint-0-verification/BS-04/s95-{01..07}-*.png` (7)
- `docs/sprint-0-verification/BS-06/s95-{01..02}-*.png` (2)
- `docs/sprint-0-verification/BS-07/s95-{01..02}-*.png` (2)
- `docs/sprint-0-verification/BS-10/s95-01-out-of-remit-refusal.png` (1)

**Vault-only (gitignored):**
- `April/April24Updates/plan/10-sprint-0-plan.md` — S0.16c → `[x]` with full session-95 closure note.
- `April/April26Updates/CSJTODO.md` — session-95 close summary + all 6 checklist items → `[x]`.

## Test users created this session

| User # | Email | Notes |
|--------|-------|-------|
| 449 | bs01-s95@example.com | Laury Greenwood — fully onboarded (BS-01, BS-07, BS-10) |
| 450 | angela-bs01s95@example.com | Angela — Laury's spouse, auto-created via capture_spouse_details |
| 451 | bs04-s95@example.com | Devon Marsh — paused at profile_review_family (BS-04) |
| 452 | bs06-s95@example.com | Bryony Stoneleigh — at base_spouse post-base_personal (BS-06) |

## Cross-references

- Plan: [[Architecture/v083/04-BACKEND]] for OnboardingChatDirector + AiConversation
- Top laws: [[../../Top Laws/critical_browser_testing_law]] + [[../../Top Laws/feedback_loop_until_correct]] (compliance verified)
- Prior session: [[session-93-bs17-bs23-green]] (BS-17 + BS-23 GREEN, S0.16b closed)
