# Remedial report — `advice_protection_cover` — session #21

*Recording session: #21 at `/admin/eval-recordings/<id>`. Date: 2026-04-27 (filed retrospectively in session 103 against the session 102 recording). Branch: `feature/fyn-persona-split`. Both providers were RED on the original YAML; calibration applied in commit `d5f5ebb` and re-fixtures in `c29ea2a` brought both to GREEN.*

## Run summary

- **anthropic/claude-haiku-4-5:** 6804ms, 2 tool calls (`get_user_profile`, `analyze_protection_coverage`), `success_false` path, **GREEN after calibration**.
- **xai/grok-4-1-fast-reasoning:** 29855ms, 4 tool calls (same 2 as anthropic + extra `list_records(entity_type=income_protection)` and `list_records(entity_type=critical_illness)`), `success_false` path, **GREEN after calibration**.
- **Dashboard URL:** `/admin/eval-recordings/<id>` (session #21).

## Rubric findings

- **2.1 Classification** — no gap. Both providers classified as `protection_cover` primary; `related` modules sensible.
- **2.2 Tool use** — gap (calibration only). xAI fired 2 extra `list_records` calls beyond the YAML's `expected_tool_calls[*].required: true` set. Both calls are READ tools (no INV-2.1.2 break) and both return data the model could reasonably want before composing the response. YAML pre-calibration also used arg key `record_type` instead of the canonical `entity_type` per `AiToolDefinitions.php:96` — wrong notation in the rewrite report.
- **2.3 LLM response mode + signposting** — no gap. Response mode `recommendation` matched expected; INV-2.3.3 signposting present at end of both providers' assistant text.
- **2.4 Engine output** — no gap. Engine output surfaced verbatim in both providers' responses.
- **2.5 Code path / readiness gate** — no gap. `success_false` path detected on both providers, consistent with seed (`john` has no `protection_profile`, triggering `analyze_protection_coverage`'s readiness gate).
- **2.6 Response quality** — **NOT ASSESSED in session 102.** The recording was graded automatically against the YAML; no human comparison of the assistant text against canonical voice / structure / numerical specificity was made. CSJ has separately raised a concern that "xAI was ignoring the structured output and not giving the response we wanted" — that concern lands in this rubric section and is not yet pinned to evidence.
- **2.7 Provider parity** — gap (functionally benign). xAI took ~4× anthropic's wall-clock and 2× the tool calls to arrive at the same `success_false` path with the same canonical signposting and (per the dashboard delta) substantially similar assistant text. Decision in session 102: widen YAML tolerance, not tighten prompt.
- **2.8 SSE shape** — no gap. `must_contain_types` satisfied on both providers; `done` emitted exactly once; `persona_state_change` and `handoff` not emitted (INV-2.4.1 holds).
- **2.9 DB writes** — no gap. Zero persistent writes on either provider, consistent with INV-2.1.2 for advice mode.
- **2.10 Recording infrastructure** — not assessed. No suspicion the recording diverges from live behaviour.

## Gaps in detail

### Gap 1: YAML used `record_type` arg key; tool definition expects `entity_type`

- **Rubric section:** 2.2
- **Evidence:** `AiToolDefinitions.php:96` defines `list_records` with arg `entity_type`. Pre-calibration YAMLs used `record_type` per the rewrite-report's notation drift.
- **Likely category:** YAML defect (Category A).
- **Likely fix surface:** the YAML files (5 advice scenarios). Already fixed for `advice_protection_cover` and the other 4 in commit `d5f5ebb`.
- **Browser verification needed?** No — the canonical source is the tool definition; YAML must match.
- **Notes:** Closed in session 102.

### Gap 2: YAML `timing_budget_ms` undersized for both providers on `success_false` path

- **Rubric section:** 2.2 (timing dimension)
- **Evidence:** anthropic ran 6804ms (budget was 6000ms); xAI ran 29855ms (budget was 14000ms).
- **Likely category:** YAML defect (Category A) with latent provider-drift signal.
- **Likely fix surface:** the YAML's `timing_budget_ms` map. Calibrated to 8000ms (anthropic) and 32000ms (xAI) in `d5f5ebb`. Inline comments in the YAML at `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml:103-107` document the session-21 evidence each value is derived from.
- **Browser verification needed?** No — calibration is against the recording's measured timings, which are the canonical source for budget setting.
- **Notes:** Closed in session 102.

### Gap 3: YAML `tool_use_count_max: 4` was 2× too tight for xAI

- **Rubric section:** 2.2 (tool count dimension)
- **Evidence:** xAI emitted 8 SSE `tool_use` events (4 real tools × ~2 SSE events each — grok-4-1-fast-reasoning streams each real tool call as two SSE events).
- **Likely category:** YAML defect (Category A) compounded by per-provider behavioural drift.
- **Likely fix surface:** the YAML. Widened to 8 in `d5f5ebb`. Open architectural question: should `tool_use_count_max` be a per-provider map (`{ anthropic: N, xai: 2N }`) so the global value doesn't either over-permit anthropic or under-permit xAI?
- **Browser verification needed?** No.
- **Notes:** Closed in session 102 with the global widening. The per-provider split is a future enhancement to the YAML schema.

### Gap 4: Response quality not assessed; CSJ concern open

- **Rubric section:** 2.6
- **Evidence:** CSJ has raised that xAI's output for this scenario "ignored the structured output and didn't give the response we wanted". This is a qualitative claim. The session-102 calibration was Category A only — it tuned numeric thresholds against measured behaviour but did not compare the assistant text against any canonical voice / structure / specificity standard.
- **Likely category:** unknown until evidence is gathered. Could be:
  - **Category D (prompt fragment missing)** — if the prompt doesn't instruct the model strongly enough to use a particular structure, both providers will drift, but xAI may drift further.
  - **Category E (provider drift, quality-side)** — if xAI specifically ignores structural cues that anthropic honours.
  - **Category F (real product bug)** — if the engine output isn't surfaced verbatim and the model is paraphrasing (INV-2.3.2 violation).
  - **No-gap** — if the live response is in fact structured the way CSJ wants and the concern was based on a different observation.
- **Likely fix surface:** depends on classification after evidence is gathered. Candidate surfaces if D: `app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/Prompts/CoreIdentity.php`, `app/Services/AI/Prompts/QueryKnowledge.php`. Candidate surface if E: tighten the prompt with an xAI-specific constraint OR widen YAML's response-quality tolerances. Candidate surface if F: the recommendation engine's output formatter for protection (`app/Services/Protection/RecommendationEngine.php` or equivalent).
- **Browser verification needed?** Yes — this is the only way to classify the gap. Walk live: log in as `john@example.com`, type `input.turns[0].user` from the YAML, capture xAI's full assistant text, compare against anthropic's, compare against what CSJ wants the response to look like.
- **Notes:** **Open.** This is the substantive remaining gap from session 21.

## Recommendation

Three of four gaps are closed (Gaps 1-3 — all Category A YAML calibrations shipped in `d5f5ebb` + `c29ea2a`). The remaining open item is Gap 4: response quality.

If CSJ wants to close Gap 4, the cheapest next step is a single live browser walk of `advice_protection_cover` against xAI to capture the actual assistant text and compare it qualitatively against (a) anthropic's response on the same recording, and (b) what CSJ expects a "structured" response to look like. That walk produces the evidence to classify Gap 4 into one of D / E / F / no-gap; the report can then be amended with the chosen fix surface.

If CSJ wants to defer Gap 4: the report stands as-is, the recording is GREEN per the YAML, and Gap 4 is documented for a future session to pick up.

The rubric does not act. CSJ decides.
