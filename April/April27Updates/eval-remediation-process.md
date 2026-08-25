# Eval remedial-report rubric

*Authored 2026-04-27 session 103. Branch: `feature/fyn-persona-split`. Purpose: the rubric below is run against every eval recording. It produces a **remedial report** that names every gap surfaced by the run — across tool, LLM, code, response quality, or recording infra — and points at the fix surface for each. The report is the deliverable. There is no automatic iteration cycle, no scheduled re-records, no cadence. CSJ decides what to act on.*

> **The rubric is a checklist, not a loop.** Run it once per recording. Produce one report. Stop.
>
> Browser verification is not an automated step — it's a tool the rubric points at when a gap is suspected to be real. CSJ runs it (or asks for it) when wanted.

---

## Section 1 — When to run the rubric

Run the rubric every time a recording happens — whether the dashboard shows GREEN or RED. A GREEN delta only means the YAML's expectations were met; the rubric is wider than the YAML and may surface gaps the YAML doesn't cover (e.g. response quality).

Run it once per recording. Produce one report. File it. Move on.

Sources to read before driving the rubric for a given recording:

- The YAML for the scenario (`tests/Feature/Fyn/Eval/scenarios/<category>/<scenario>.yaml`).
- The recording session row at `/admin/eval-recordings/<session_id>` — both providers, both deltas, raw fixture.
- The system-prompt sample at `/admin/eval-recordings/<session_id>/runs/<run_id>/system-prompt`.
- Canonical contract: `April/April24Updates/spec/00-canonical.md`, `April/April24Updates/spec/01-invariants.md`.

---

## Section 2 — The rubric

For each section below, check the recording's evidence against the rubric. If a gap is found, capture it for the report (Section 4). If everything passes, that section's report entry is "no gap".

### 2.1 Classification

- Does `delta.classification.actual.primary` match `delta.classification.expected.primary`?
- Are `related` modules sensible for the user message? Any obvious miss (e.g. "ISA" message classified to `general`)?
- If the message is borderline (mixes two topics), is the chosen primary the more user-meaningful one?

**Likely fix surface if a gap:** `app/Services/AI/QuerySchemas.php::KEYWORD_PATTERNS`. Add a unit test in `tests/Unit/Services/AI/QueryClassifierTest.php` if widening.

### 2.2 Tool use

- Were every `expected_tool_calls[*].required: true` tool actually called?
- Did any `forbidden_tools` fire? (Hard fail if so — INV-2.1.2 break for any AdviceFyn `WRITE_TOOLS` family.)
- Were tool arguments correct (right key names per `app/Services/AI/AiToolDefinitions.php::TOOL_DEFS`, right values per the seed)?
- Did each tool emit the `result_path` the YAML expected (`success`, `success_false`, `kyc_blocked`, `readiness_blocked`, `empty_state`, `happy`)?
- Did the model interpret tool results, or pass engine output through verbatim (INV-2.3.2)?

**Likely fix surfaces:**
- Wrong tool name in YAML → fix the YAML.
- Right tool name, wrong args → fix the model's tool-call shape via prompt (`app/Services/AI/AdvicePromptBuilder.php`) or fix the tool's accepted args (`app/Services/AI/AiToolDefinitions.php`).
- Wrong result_path → fix the tool handler in `app/Services/AI/Tools/<ToolName>.php` or its readiness gate (`app/Services/<Module>/<Module>DataReadinessService.php`).

### 2.3 LLM response mode + signposting

- Does `delta.response_mode.actual` match `delta.response_mode.expected` (per `AdviceFyn::classifyResponseMode`)?
- For `recommendation` mode: is the INV-2.3.3 signposting present at the end of the assistant text?
- For `factual` mode: is the signposting absent?
- Are any forbidden phrases present (`I think you should`, `In my opinion`, `you should consider...` without engine output)?

**Likely fix surfaces:** `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/Prompts/FcaProcessInstructions.php`, `app/Services/AI/Prompts/ComplianceRules.php`. Apply to BOTH providers (single prompt; xAI tools mapped via `XaiToolDefinitions.php`).

### 2.4 Engine output

- For `engine_call_level: holistic|module` scenarios: was the engine invoked at all? (Check `delta.engine_call_actual` and the captured tool results.)
- Was the engine's output surfaced in the assistant text verbatim, or did the model paraphrase?
- For `engine_call_level: factual`: was the engine correctly NOT invoked?

**Likely fix surfaces:** the recommendation engine's output formatter (`app/Services/<Module>/RecommendationEngine.php` or equivalent), the prompt fragment that instructs the model to read engine output (`AdvicePromptBuilder::buildSystemPrompt`).

### 2.5 Code path / readiness gate

- Did the right KYC gate fire (`delta.kyc_state.actual` vs expected)? KYC bypass on a sensitive query is a real bug.
- Did the per-agent profile gate fire (`if (! $user->protectionProfile)` in `ProtectionAgent`, `if (! $profile)` in `RetirementAgent`)?
- Did the response correctly route to a `readiness_blocked` or `kyc_blocked` empty-state when seed data was insufficient?

**Likely fix surfaces:** `app/Services/AI/KycGateChecker.php`, the per-module data readiness service, the agent class's profile-gate line.

### 2.6 Response quality (the YAML often doesn't cover this)

This is the section the rubric makes visible that the YAML's automated assertions can miss.

- Is the assistant text **qualitatively correct** for a real user reading it cold? (Would a financial-planning-aware reader find anything wrong?)
- Is it structured the way you want? (Headings, paragraphs, bullets when needed; not a wall of prose.)
- Does it answer the user's question, or does it sidestep into procedural / system text?
- Does it surface concrete numbers from the user's seeded data, or does it stay abstract?
- Is the tone right (not too hedged, not too directive, neutral on the regulated boundary)?
- Does it match the canonical voice (see `spec/00-canonical.md`)?

**Likely fix surfaces:** `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/Prompts/CoreIdentity.php`, `app/Services/AI/Prompts/QueryKnowledge.php`. Tightening the prompt with concrete shape examples often helps more than rule lists.

### 2.7 Provider parity

For every gap surfaced in 2.1-2.6: does it appear in BOTH providers, or only one?

- Both providers RED on the same gap → product issue. Fix the prompt / tool / engine.
- One provider RED, other GREEN → behavioural drift. Decide:
  - Functionally wrong (write tool fired, signposting omitted, engine output missing) → fix the prompt to force the right behaviour on the misbehaving provider.
  - Cosmetic (extra exploratory tool calls, longer wall-clock, more verbose preamble) → either widen the YAML's per-provider tolerance or tighten the prompt to constrain the divergence. Both are valid; the report names the trade-off.

xAI's `grok-4-1-fast-reasoning` baseline divergences observed so far (extend this table as new ones surface):

| Surface | Anthropic | xAI | Source |
|---------|-----------|-----|--------|
| `success_false` path timing | ~6800ms | ~30000ms | session 102 #21 |
| Real-tool-to-SSE-event ratio | 1:1 | ~1:2 | session 102 #21 |
| Exploratory `list_records` extras | 0 | 0-2 per advice scenario | session 102 #21 |

### 2.8 SSE shape

- `must_contain_types` — every required SSE event type appeared.
- `must_emit_exactly_once` — `done` fired once.
- `must_not_emit` — `persona_state_change` and `handoff` never reached the frontend (INV-2.4.1).

**Likely fix surfaces:** `app/Services/AI/AiChatStreamer.php` (or equivalent), `AdviceFyn::wrapStream` for the inline-capture handoff suppression.

### 2.9 DB writes

- Advice scenarios: zero persistent DB writes (INV-2.1.2). Any new row in `assets`, `protection_policies`, etc. is a hard fail.
- Onboarding scenarios: the rows the state machine's commit handler should have written are present.
- Conversation rows (`ai_conversations`, `ai_messages`) are expected on every turn — those are the chat substrate, not "writes" in the persona-contract sense.

**Likely fix surfaces:** `AdviceFyn::WRITE_TOOLS` filter at `app/Services/AI/AdviceFyn.php:128`, or the relevant onboarding state's commit handler in `app/Services/Onboarding/`.

### 2.10 Recording infrastructure

- Does the recording's captured behaviour match what would happen in a live browser walk of the same prompt under the same seed? (Only a concern if a gap above feels suspicious — i.e. the recording shows a defect that doesn't make sense given the code.)
- Is the fixture file complete (no truncation, all SSE events captured)?
- Is the system prompt captured in `ai_messages.system_prompt` for the assistant message?

**Likely fix surfaces:** `app/Console/Commands/EvalRecordCommand.php`, `app/Services/Eval/*`.

---

## Section 3 — Browser verification options (only when wanted)

When the rubric surfaces a gap and CSJ wants to confirm it's real before treating it as a bug, here's how to walk the same prompt in a live browser. **Not automatic** — only run when CSJ asks for it.

1. `php artisan db:seed --force`.
2. `./dev.sh`.
3. Sign in as the YAML's `seed.user.email` (default `john@example.com` for advice scenarios). Verification code from the DB per CLAUDE.md "Authentication for Testing".
4. Open the AI chat panel. Type the YAML's exact `input.turns[0].user` string. Submit.
5. Capture: SSE event sequence (devtools Network → text/event-stream), assistant text, any DB writes (`SELECT * FROM ai_messages WHERE conversation_id = N ORDER BY id DESC LIMIT 4`), tool calls (`ai_messages.tool_calls`).
6. Compare against the recording's evidence per the rubric section that flagged the gap.

If the gap reproduces live → it's a real product issue.
If the gap appears only in the recording → it's a recording-infra issue (rubric §2.10).
If the gap appears in both but the YAML expectation was wrong → it's a YAML defect; report says "calibrate YAML, no code change".

The browser walk produces evidence for the report. It does not produce a fix.

---

## Section 4 — The remedial report (template)

For every recording, produce ONE report under `April/April27Updates/issues/<YYYY-MM-DD>-<scenario_id>-<short-tag>.md` using this template. The report is the deliverable from running the rubric.

```markdown
# Remedial report — <scenario_id> — session #N

*Recording session: #N at `/admin/eval-recordings/<id>`. Date: YYYY-MM-DD. Branch: <branch>. Both providers: <anthropic green|red> / <xai green|red>.*

## Run summary

- **anthropic/<model>:** <duration>ms, <N> tool calls, <result_path>, <green|red> overall.
- **xai/<model>:** <duration>ms, <N> tool calls, <result_path>, <green|red> overall.
- **Dashboard URL:** /admin/eval-recordings/<id>

## Rubric findings

For each rubric section (2.1-2.10), one bullet:

- **2.1 Classification** — <gap or "no gap">
- **2.2 Tool use** — <gap or "no gap">
- **2.3 LLM response mode + signposting** — <gap or "no gap">
- **2.4 Engine output** — <gap or "no gap">
- **2.5 Code path / readiness gate** — <gap or "no gap">
- **2.6 Response quality** — <gap or "no gap">
- **2.7 Provider parity** — <gap or "no gap">
- **2.8 SSE shape** — <gap or "no gap">
- **2.9 DB writes** — <gap or "no gap">
- **2.10 Recording infrastructure** — <gap or "no gap" — usually "not assessed unless other gaps suspect">

## Gaps in detail

For each non-"no gap" finding, one stanza:

### Gap N: <one-line description>

- **Rubric section:** <2.X>
- **Evidence:** <quoted line from delta.failures, dashboard panel, fixture content, or assistant_text>
- **Likely category:** <YAML defect | classifier | tool/contract | prompt/engine | code path | response quality | provider drift | SSE | DB write | recording infra>
- **Likely fix surface:** <file:line>
- **Browser verification needed?** <yes — to confirm live behaviour matches recording | no — recording evidence is sufficient | not yet decided>
- **Notes:** <any nuance, trade-off, or open question>

## Recommendation

One paragraph for CSJ. Either:

- "No action recommended — all gaps are cosmetic / Category-A YAML calibrations." (and list them), OR
- "Recommend acting on Gap N first, because <reason>. Estimated fix surface: <file:line>. Estimated effort: <small / medium / large>." (and continue for any other recommended actions).

CSJ decides whether and when to act. The report does not act.
```

---

## Section 5 — Worked example

`April/April27Updates/issues/2026-04-27-advice_protection_cover-xai-tool-call-divergence.md` is a remedial report filed retrospectively against session 102 recording #21 of `advice_protection_cover`. It's the canonical first entry of the ledger and the reference shape for all future reports. Read it after this rubric to see the template populated.

---

## Section 6 — Pointer index

| Topic | File |
|-------|------|
| Canonical Two-Fyn contract | `April/April24Updates/spec/00-canonical.md` |
| 35 falsifiable invariants | `April/April24Updates/spec/01-invariants.md` |
| Sprint 1 plan + S1.7 sub-tasks | `April/April24Updates/plan/11-sprint-1-plan.md` |
| Eval rewrite spec (S1.2.l + S1.7) | `April/April27Updates/eval-expectations-rewrite.md` |
| This rubric | `April/April27Updates/eval-remediation-process.md` |
| Remedial-report ledger | `April/April27Updates/issues/*.md` |
| Browser-test rules | `CLAUDE.md` "Testing — CRITICAL" |
| AdviceFyn read-only contract | `MEMORY.md` → `feedback_advice_fyn_is_read_only.md` |

The rubric is a checklist. The report is the deliverable. CSJ decides what to do with the findings.
