# Plan — Multi-entity capture across all Fyn tools

**Date:** 22 April 2026
**Branch:** `feature/fyn-persona-split` (off `onboardingFyn`)
**Author:** Claude (session 2)
**Status:** AWAITING USER SIGN-OFF — no code touched

---

## 1. Context

Browser testing of the Fyn onboarding flow on 21 April surfaced that when a user mentions multiple items of the same module in a single message during `asset_capture`, Fyn's LLM emits only ONE `create_*` tool call and drops the rest.

Verbatim example (21 Apr, protection module):

> User: *"I have Aviva life insurance £300,000 and Vitality critical illness £100,000."*
> Fyn: *(acknowledges both in text, creates only the Aviva life insurance policy)*
> DB: one row in `life_policies`, zero rows in `critical_illness_policies`.

FR-M14 previously tightened the assets/liabilities path in `OnboardingPromptBuilder::assetCaptureInstructions`. The rest of the tool surface is still leaky.

## 2. Root cause

Two competing signals inside the LLM input contradict each other:

| Source | Phrase | Effect |
|---|---|---|
| Prompt builders (`OnboardingPromptBuilder` line 102, `DataCapturePromptBuilder` line 82) | *"when the user mentions multiple holdings/records in a single message, emit one tool_use block per record"* | Says **emit multiple** |
| ~15 tool descriptions in `XaiToolDefinitions` + `AiToolDefinitions` | *"Call this tool IMMEDIATELY. IMPORTANT: Do NOT call any other creation tools in the same turn."* | LLM reads this as **do not call this tool twice either** |
| `create_family_member` description | *"For multiple children, call this tool ONCE per child in separate turns."* | Directly contradicts multi-entity |
| `FcaProcessInstructions::getDataCreationGuidance` (advice path only) | *"you MUST call the appropriate tool"* (singular) | Reinforces single-emission on the `FYN_PERSONA_SPLIT=false` fallback |

When the signals conflict, the LLM defaults to the tool-description interpretation (it trusts the tool metadata more than the system prompt) and emits one call.

## 3. Goal

Every create_* / update_* / capture_* tool, in both LLM providers and both persona paths, must permit multi-entity emission in a single turn:

- **Within-tool** (primary): one user message mentioning N items of the same type → N tool_use blocks of the same tool in the first response.
- **Cross-tool** (secondary, rarer): one user message mentioning items that span tools → multiple distinct tool_use blocks in the first response. User decision: do it in one turn, not two steps — the re-route through the orchestrator is token- and latency-expensive.

No re-routing. No "call it again next turn". No deferred capture.

## 4. Scope

### In scope

- `app/Services/AI/XaiToolDefinitions.php` — Grok tool descriptions
- `app/Services/AI/AiToolDefinitions.php` — Anthropic tool descriptions
- `app/Services/Onboarding/OnboardingPromptBuilder.php` — onboarding asset_capture prompt
- `app/Services/AI/Prompts/DataCapturePromptBuilder.php` — post-onboarding capture prompt
- `app/Services/AI/Prompts/FcaProcessInstructions.php` — advice path single-emission bias (FYN_PERSONA_SPLIT=false fallback)
- Browser test matrix covering all 10 modules listed in CSJTODO
- Mocked-LLM feature tests for regression protection

### Out of scope

- Frontend form-fill queue behaviour — **audit only**. If the queue cannot safely serialize cross-tool bursts, that becomes a follow-up PR.
- `CoordinatingAgent` routing logic — unchanged.
- Any tool schema change (parameter shapes, enums, required fields) — prompt/description only.
- `FYN_PERSONA_SPLIT` flag state — leave as-is (OFF by default).

## 5. Investigation tasks (do first, before any edit)

- [ ] **I1.** Grep both definition files for every create_*/update_*/capture_*/set_* tool and produce a single table: tool name, line range, current "do not call other tools" phrasing (verbatim), module, variability (does wording differ per tool?).
- [ ] **I2.** Verify the frontend tool-call queue (the `OnboardingChatDirector.php:1431` comment says "the frontend queue, which still applies"). Find the queue in `resources/js/store/modules/aiChat.js` or wherever `tool_use` events are consumed. Confirm it can: (a) serialize N calls to the same tool, (b) serialize M calls across different tools, (c) handle tool-interleaved-with-content streams. Record findings in `findings.md`.
- [ ] **I3.** Check whether any of the following block multi-entity at a NON-prompt layer: `KycGateChecker`, `StructuredResponseValidator`, `AdviceReviewService`, `HasAiChat::chat`. If any silently de-dupes tool_use blocks, flag as a separate task.
- [ ] **I4.** Confirm persona-split `DataCapturePromptBuilder::captureInstructions` is actually reaching the LLM for post-onboarding delegate_to_capture flows. Read `FynPersonaInvoker` to trace system-prompt composition.
- [ ] **I5.** For `create_property` specifically: confirm the page-stay constraint. Can two `create_property` calls be queued safely (second form-fill opens after first commits)? If yes, the exclusion can be loosened to navigate/analysis tools only.

## 6. Implementation phases

### Phase A — Tool description cleanup

Single source of truth; touch both definition files. Keep wording identical between the two files where the tool exists in both.

- [ ] **A1.** Remove `"IMPORTANT: Do NOT call any other creation tools in the same turn."` from every tool listed in I1 output.
- [ ] **A2.** For tools that have a genuine constraint (property form-stay, create_holding's "use create_investment_account if new account"), replace with precise, positive phrasing:
  - Property: *"Do NOT call `navigate_to_page` or analysis tools in the same turn — those interrupt the form fill. You MAY call `create_property` multiple times for multiple properties."*
  - Holding: *"If the user is adding holdings to a new account, prefer `create_investment_account` with the holdings parameter. Otherwise `create_holding` is fine and MAY be called multiple times."*
- [ ] **A3.** Fix `create_family_member` — remove *"For multiple children, call this tool ONCE per child in separate turns"*. Replace with: *"When the user mentions multiple family members in one message, call `create_family_member` once per member in your first response."*
- [ ] **A4.** Add a one-sentence multi-entity affordance to every create_* tool description, standardised wording: *"You MAY call this tool multiple times in the same turn when the user mentions multiple items of this type."*
- [ ] **A5.** Verify no tool currently relies on single-emission for a correctness reason (e.g. a race condition, an observer side-effect). If found, record as risk.

### Phase B — Prompt builder strengthening

- [ ] **B1.** `OnboardingPromptBuilder::assetCaptureInstructions`: promote the multi-entity rule to the TOP of the instructions block, above "YOUR SINGLE JOB". Replace "multiple holdings" with module-generic language. Add an inline example appropriate to the current focus (e.g. for protection focus: *"User: 'I have Aviva life £300k and Vitality critical illness £100k' → first response: two tool_use blocks."*).
- [ ] **B2.** `DataCapturePromptBuilder::captureInstructions`: mirror B1's wording. Include a CROSS-tool example too, since the data_capture persona is unrestricted by focus: *"User: 'I have a Halifax ISA £10k and Aviva life insurance £300k' → first response: `create_savings_account` + `create_protection_policy` in the same assistant turn."*
- [ ] **B3.** Keep the FR-M14 off-script guardrail wording intact — the multi-entity rule is ADDITIVE to it, not a replacement.

### Phase C — Advice path (FYN_PERSONA_SPLIT=false fallback)

- [ ] **C1.** `FcaProcessInstructions::getDataCreationGuidance`: soften *"you MUST call the appropriate tool IN YOUR VERY FIRST RESPONSE"* to *"you MUST call the appropriate tool(s) IN YOUR VERY FIRST RESPONSE — one tool_use block per item when the user mentions multiple"*.
- [ ] **C2.** Adjust the `WRONG / RIGHT` example block to include a multi-entity case: *"RIGHT: User: 'I have two houses, main residence £400k and a BTL £250k' → YOU CALL create_property TWICE → both forms queue → 'Both properties recorded. Anything to add?'"*

### Phase D — Verification

Every module gets a browser test AND a mocked-LLM feature test.

#### D1. Browser test matrix — localhost:8000, Playwright, fresh user reset + db:seed before each

| # | Module | Message | Expected tools fired | Expected DB |
|---|---|---|---|---|
| 1 | Protection | "I have Aviva life insurance £300k and Vitality critical illness £100k" | create_protection_policy × 2 | 1 life_policy + 1 critical_illness_policy |
| 2 | Investment accounts | "I have an HL SIPP £50k and a Vanguard stocks & shares ISA £15k" | create_investment_account × 2 | 2 investment_accounts |
| 3 | Investment holdings | "In my SIPP I hold Apple £5k and Microsoft £8k" | create_holding × 2 | 2 holdings in the same account |
| 4 | Retirement | "I have a workplace DC pension £80k with Aviva and a SIPP £120k with HL" | create_pension × 2 | 2 dc_pensions |
| 5 | Savings | "I have a Halifax ISA £10k and a Nationwide saver £5k" | create_savings_account × 2 | 2 savings_accounts |
| 6 | Estate (property) | "I own my main residence £400k and a BTL £250k" | create_property × 2 | 2 properties |
| 7 | Estate (liabilities) | "£200k mortgage and £10k car loan" | create_mortgage + create_liability | 1 mortgage + 1 liability |
| 8 | Estate (gifts) | "I gifted £5k to my daughter last year and £3k to my son in 2023" | create_estate_gift × 2 | 2 gifts |
| 9 | Family | "I have a daughter Emily age 8 and a son James age 5" | create_family_member × 2 | 2 family_members |
| 10 | Goals | "I want to save £50k for a house deposit by 2030 and £30k emergency fund" | create_goal × 2 | 2 goals |
| 11 | Life events | "Wedding in June 2027 and baby due December 2027" | create_life_event × 2 | 2 life_events |
| 12 | Expenditure (baseline) | "Rent £1500, utilities £300, groceries £400" | set_expenditure × 1 (multi-category) | 1 expenditure_profile updated |
| 13 | Employment (multi-job) | Confirm `STATE_BASE_EMPLOYMENT_MORE` loop still works for 2 jobs | update_profile × 2 (two loop passes) | 2 employment rows |
| 14 | Cross-tool (rare) | "I have an ISA £10k and life insurance £300k" | create_savings_account + create_protection_policy | 1 savings + 1 policy |

**Note on row 14 (cross-tool):** This is the test-and-see case. We do NOT pre-emptively defer it based on the I2 queue audit. Run the live browser test regardless of what the queue audit suggests — the LLM might emit both tool calls and the frontend might handle them fine, or it might not. Only after observing the live behaviour do we decide:
- Both tool calls fire, both rows persist → done, row 14 passes.
- LLM emits both but frontend drops/mangles one → record verbatim evidence, open a follow-up PR for the frontend queue fix, but keep the prompt/tool-description changes in this PR.
- LLM only emits one → prompt layer still leaky; tighten Phase B wording and re-test in the same session.

The point is to learn from real behaviour, not to assume.

Each row must have:
- a Playwright conversation transcript (verbatim, in `browser-test-multi-entity.md`),
- a DB query output confirming the expected rows exist with the right values,
- a screenshot if the UI shows record cards.

#### D2. Feature tests — mocked xAI

Add tests under `tests/Feature/Fyn/MultiEntity/` that stub the xAI client to return multi-tool_use assistant messages. Assert:
- every tool call fires its handler,
- DB rows persist,
- the director/orchestrator doesn't short-circuit after the first call.

One test per row in D1 (14 total). Use existing `XaiClientFake` if present; otherwise build a minimal stub.

#### D3. Regression guardrail

- [ ] Full onboarding walkthrough retest (Path A protection, Path B savings) — the profile-review + multi-job + spouse skip paths from session 1 must still pass.
- [ ] Persona-split post-onboarding six scenarios retest.

### Phase E — Commit + hand back

- [ ] Commit per phase on `feature/fyn-persona-split` with descriptive messages (`fix(fyn): multi-entity — tool description cleanup` / `fix(fyn): prompt builders` / `fix(fyn): advice path` / `test(fyn): multi-entity browser + feature matrix`).
- [ ] Push to origin.
- [ ] Update `CSJTODO.md`: mark top priority DONE, re-order Gate 1 deploy to top.
- [ ] **Do NOT merge-back or deploy.** User decides next-step gating.

## 7. Risks

| Risk | Mitigation |
|---|---|
| Frontend queue can't handle cross-tool bursts | I2 audit up front is a heads-up only; row 14 is run LIVE regardless and we learn from observed behaviour (see D1 note on row 14) |
| Observer side-effects fire wrong order when tools are batched | I3 audit; add explicit serial-processing in the director if needed |
| Loosening tool descriptions causes LLM to spam tools unrelated to user intent | Mitigated by the capture-turn "single job" framing which stays intact; tests D1 include negative cases (one item → one tool, zero items → acknowledgement only) |
| Mocked xAI feature tests drift from live xAI behaviour | Mocked tests guard STRUCTURE (multiple tool_use blocks parsed, all handlers called); live browser tests (D1) validate END-TO-END behaviour. Both layers required. |
| FcaProcessInstructions softening breaks advice-turn KYC gating | C1/C2 change wording only; no logic change. Run the full Pest suite after Phase C (2,361 tests must stay green) |

## 8. Non-goals

- We are NOT rewriting the tool-call dispatcher, the observer pipeline, or the SSE stream contract.
- We are NOT changing any tool's parameter schema.
- We are NOT touching `OnboardingStateMachine` or journey routing.
- We are NOT altering KYC gates, advice review, or plan generation.
- We are NOT version-bumping.

## 9. Acceptance

This plan is complete when:

1. All 14 rows in D1 pass with verbatim Playwright transcripts + DB evidence in `browser-test-multi-entity.md`.
2. All D2 feature tests pass in CI (`./vendor/bin/pest tests/Feature/Fyn/MultiEntity/`).
3. All D3 regression walkthroughs pass.
4. Full Pest suite green (2,361 tests, no new flakes).
5. `CSJTODO.md` top-priority block removed; handover reflects the fix is ready for Gate 1 deploy.
6. User signs off on the browser evidence.

## 10. Questions parked

- Mobile parity — deferred per PRD §7 (not in this fix).
- Will create path currently routes through advice not data_capture (CSJTODO line 92) — tracked separately, not here.
- DOB slashed-date parser quirk (CSJTODO line 94) — tracked separately.

---

**Next action on user sign-off:** start Phase I (investigation), then Phase A. Commit each phase separately. Phase D (browser tests) happens last, after all three code phases are in.
