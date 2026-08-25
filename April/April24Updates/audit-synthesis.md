# Fyn AI Audit — Consolidated Synthesis (v2 — post three-pass review)

---

## §0 — Canonical two-Fyn contract (source of truth)

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:
- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. This synthesis — and every sprint, rubric dimension, and eval scenario it produces — exists to make that contract observable and falsifiable.

---


**Date:** 24 April 2026
**Inputs:** The four planning docs (archived `INDEX.md`, `fyn-system-map.md`, `fyn-integrated-plan.md`, `enterprise-verdict.md`); the evidence bundle (`audit-evidence.md`, §1-20); five independent reviewers (`ce-best-practices-researcher`, `ce-web-researcher`, `ce-reliability-reviewer`, `ce-cli-agent-readiness-reviewer`, `ce-adversarial-document-reviewer`); three-pass review output (`docs-three-pass-review.md`).
**Output purpose:** Give CSJ the code-grounded picture of what's **correctly planned**, **invalidated by code**, **scope creep / made up**, and **real gaps the docs miss**, so the forthcoming spec is drafted on facts not inherited claims.

**v2 corrections (24 April PM, post three-pass review):**
- §2 #4 retracted: `FynPersonaOrchestrator`'s capture path DOES have entity extractor wiring — see `FynPersonaInvoker::emitGapFillFromCaptureContext`. Rewritten to narrow the gap to coverage breadth (4 of 18+ entity types) and emitter duplication (invoker vs director).
- §2 #5 tool counts corrected + direction inverted: **37 Anthropic / 33 xAI** (not 29/23). `create_holding` + onboarding `capture_*` tools are Anthropic-only; both providers have `list_records` and `set_expenditure`. Anthropic has the richer catalogue, not xAI — this reframes which provider has the parity gap.
- §2 #6 Sprint 0.19 LOC revised: 1,238 LOC deletion (not ~800); +~1,000-1,200 new code; net reduction ~500-800 LOC.
- §2 #9 processor count reframed: 4 disclosed + 4-5 undisclosed, with Anthropic under-disclosed rather than undisclosed.
- §5.2 `create_*` tool count: 17 `fill_form` sites (not 13) — includes modification tools that also return `fill_form`.
- §7.1 #4 "wired into ONE path" retracted — wired into both.
- §8.2 LOC numbers corrected.

---

## 0. Headline

**The four docs are not a safe base for a spec in their current state.** The individual research — system-map §1-20, verdict Part C, integrated-plan §7 T-index — is valuable. But (a) later passes did not flow back into earlier sections, producing 11+ material contradictions; (b) ~22 load-bearing claims carry no evidence; (c) the code reality on `main` and `feature/fyn-persona-split` invalidates five specific claims used to drive Sprint-0 decisions; (d) multiple architectural facts the docs treat as minor are actually reframing-level (all 13 `create_*` tools are form pre-fillers, not DB writes; PS25/22 targeted support went live 6 April 2026; tool catalogue is 23 on Anthropic not 29; there is no SSE abort detection anywhere). A correction pass is required before a spec is drafted. This document is the correction input, not the spec.

---

## 1. What's Correctly Planned (verified against code — proceed as specced)

| # | Claim / task | Evidence anchor | Status |
|---|---|---|---|
| 1 | 10-layer system prompt, 3 static + 7 dynamic | `SystemPromptBuilder.php:51-120` | ✅ verbatim accurate |
| 2 | 22 query types in `QuerySchemas` | `app/Constants/QuerySchemas.php` | ✅ |
| 3 | 9 SSE event types enumerated in §2.5 | `app/Traits/HasAiChat.php` yield sites | ✅ |
| 4 | Per-plan token limits (100k/1M/300k/1M/1.5M/2M) | `app/Traits/HasAiGuardrails.php:30-37` | ✅ |
| 5 | `MAX_TOOL_CALLS_PER_TURN = 5` | `app/Traits/HasAiChat.php:44` | ✅ (but see §5.4 — this is a hidden cap on multi-entity) |
| 6 | `update_record` blocklist is 2 fields | `app/Agents/CoordinatingAgent.php:3134` (was 2489-2490 in v1) | ✅ — Sprint 0.5 allowlist fix is the right direction, effort under-scoped (see §6) |
| 7 | `ConsentService::hasConsent` exists but is NOT called from chat flow | `app/Services/GDPR/ConsentService.php::hasConsent` vs zero call sites in `app/Http/Controllers/Api/AiChatController.php` / `app/Traits/HasAiChat.php` | ✅ — Sprint 0.7 fix is correct |
| 8 | Audit log is file-only (`Log::channel('single')->info('[AI-AUDIT]')`) | `app/Agents/CoordinatingAgent.php:770` (was 705 in v1) | ✅ — tamper-evidence problem real |
| 9 | Stale OpenAI config block (abandoned March migration) | `config/services.php:34-38` | ✅ — Sprint 0.17 delete |
| 10 | Python Agent SDK sidecar is dead code (0 PHP callers, no cron/jobs) | `scripts/fynla_agent/` + `app/Http/Controllers/Api/AgentInternalController.php` — no references via `grep -rn` across `app/`, `routes/`, `config/`, `database/`, `resources/` | ✅ — Sprint 0.16 delete |
| 11 | `AIExtractionService` exists, 965 LOC, stale Anthropic model | `app/Services/Documents/AIExtractionService.php:1-965` with `private const ANTHROPIC_MODEL = 'claude-3-5-haiku-20241022'` at `app/Services/Documents/AIExtractionService.php:19` | ✅ (14-month-stale model — should be Sprint 1, not Sprint 4) |
| 12 | The `CaptureContext` VO (`app/ValueObjects/CaptureContext.php`), `HandoffContract` (`app/Services/AI/HandoffContract.php`), `FynPersonaOrchestrator` (`app/Services/AI/FynPersonaOrchestrator.php:1-415`), `FynPersonaInvoker` (`app/Services/AI/FynPersonaInvoker.php:1-518`), `FynPersonaRegistry` (`app/Services/AI/FynPersonaRegistry.php:1-104`), `DataCapturePromptBuilder` (`app/Services/AI/Prompts/DataCapturePromptBuilder.php:1-110`) all exist on persona-split and are new (not on main) | `git show origin/feature/fyn-persona-split:…` confirms each | ✅ |
| 13 | `ai_conversations.persona_state` + `ai_messages.persona` migrations exist on persona-split | `database/migrations/2026_04_22_000002_add_persona_state_to_ai_conversations.php` and `database/migrations/2026_04_22_000001_add_persona_to_ai_messages.php` | ✅ |
| 14 | `ai_advice_logs.user_data_snapshot` captures income/expenditure/employment/marital at advice time | migration `2026_04_01_150000` + `AiAdviceLog` model | ✅ — but persistence-window implication is missed by verdict C18 (see §4) |
| 15 | Two-Fyn intent is architecturally defensible (vs. three) | `config/fyn_personas.php:1-91` has exactly 2 entries on persona-split (`advice` + `data_capture`); the third "persona" is the onboarding director (`app/Services/Onboarding/OnboardingChatDirector.php:1-1985`), a separate code path | ✅ direction, ❌ framing (see §3.6) |

---

## 2. What's Invalidated by Code (docs contradict reality — fix or delete)

| # | Claim in docs | What code actually says | Severity |
|---|---|---|---|
| 1 | **system-map §21 Q3 + integrated-plan Sprint 1.2:** "Anthropic cache metrics not persisted" | `HasAiChat.php:569-572` persists `cached_tokens` + `cache_hit_rate` to `ai_messages.metadata` JSON (v2: earlier cited lines 467-471 — stale after rebase) | 🟠 Sprint 1.2 is a **no-op**; delete the task |
| 2 | **system-map §21 Q2 + verdict G20 + integrated-plan Sprint 5.3:** "Admin UI for AiAuditController is missing" | `resources/js/components/Admin/AiAudit.vue` exists and is mounted in `AdminPanel.vue` | 🟠 Sprint 5.3 is a hardening task, not a build task; rescope |
| 3 | **CSJTODO + integrated-plan §0:** "persona-split is 72 commits behind main" | `git rev-list --count origin/feature/fyn-persona-split..origin/main` = **179** (v2: v1 stated 178 — one off) | 🔴 Sprint 0.1 rebase effort understated 2.6× |
| 4 | **integrated-plan §5.1:** "multi-entity works end-to-end (Aviva + Vitality) on persona-split" | (v2 correction — v1 of this synthesis incorrectly claimed the orchestrator had no extractor call.) Both paths — the onboarding director AND `FynPersonaInvoker::emitGapFillFromCaptureContext` (via B-1 commit `37b6a4b`) — wire the same `AssetCaptureEntityExtractor`. The real gap is two-fold: (a) the extractor covers only 4 focuses (protection / savings / retirement / investment) with known-provider regex; the other 12+ entity types (goal, life_event, property, trust, will, POA, business_interest, chattel, family_member, liability, estate_gift, holding) get no gap-fill on either path. `FynPersonaInvoker::inferFocusesFromEntityTypes` silently drops them. (b) The emission logic is duplicated between director and invoker (`emitGapFillToolCalls` vs `emitGapFillFromCaptureContext`) — that's what Sprint 0.19 de-duplicates. | 🟠 T14 "solved" claim is narrower-than-stated but not fully false; the user's stated top-priority bug is partially fixed for 4 focuses with known providers; broad coverage is Sprint 1+ work |
| 5 | **system-map §7:** "29 tools" | **v2 correction** — actual counts are **37 Anthropic / 33 xAI**. The "29" figure appears in neither file. Tools present on Anthropic but not xAI: `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `create_holding`. Tools present on both: `list_records`, `set_expenditure`. Direction of the parity gap is **inverted** from v1 of this synthesis — Anthropic has the richer catalogue, not xAI. A runtime provider flip from Anthropic to xAI silently loses 4 tools (including the 3 onboarding capture tools, which matter for onboarding Fyn). | 🔴 Fundamental to the audit — both count and direction were wrong in v1 |
| 6 | **integrated-plan §12.2:** Sprint 0.19 "collapse three-persona → two-persona, 1-2 days incl tests" | `OnboardingChatDirector::handleInlineCaptureTurn` **does not exist** on persona-split (zero matches). It is a **proposed new method**, not a refactor target. Corrected scope (v2, from three-pass review Pass 3): **delete ~1,238 LOC prod** (invoker 518 + orchestrator 415 + registry 104 + DataCapturePromptBuilder 110 + config/fyn_personas 91) + **~1,000 LOC tests**; **add ~1,000-1,200 LOC new prod** (`AdviceFyn` + `handleInlineCapture` + dispatch changes + frontend + new event shapes + rewire `AssetCaptureEntityExtractor` emission into the new path) + **~400-500 LOC new tests**. Net reduction ~500-800 LOC, not the claimed "~800 LOC deletion". Plus a migration to clear `persona_state` for conversations that would otherwise carry stale capturing state post-collapse — not named in the current Sprint 0.19 TODO. | 🔴 Effort estimate ~2-3× low (4-6 days, not 1-2); extractor-emission-rewire and state-migration subtasks are not even named |
| 7 | **integrated-plan §8 header:** "Sprint 0 = 1-2 days, 9 tasks" | Honest per-task effort across 0.1/0.5/0.7/0.8/0.18/0.19: ~8-12 days total | 🔴 Planning-calendar collapse |
| 8 | **integrated-plan §4.2:** "three-persona works on paper — adding a fourth is one config entry + one prompt builder" (usefulness-lens verdict) | §12 then concludes the three-persona direction is wrong and must be collapsed. §4.2 is never rewritten. | 🟡 Internal contradiction unresolved |
| 9 | **enterprise-verdict K3:** "three undisclosed third-party processors" (Anthropic / xAI / Plausible) | **v2 framing — count reworked.** The policy discloses 4 processors in §7: GetAddress.io, Anthropic (scoped to document extraction only), SiteGround, mail.fynla.org. **Undisclosed:** xAI (chat LLM), Meta Pixel (`app.blade.php:80-89`, unconditional, merchant ID `1878962689749080`), AWIN (env-gated; full code integration), Plausible (config-gated). **Under-disclosed:** Anthropic's chat use (§7 mentions Anthropic only for document extraction). So the accurate framing is: **4 disclosed + 4 undisclosed + 1 under-disclosed** = 9 processors touched vs 4 fully covered by the policy. Earlier v1 framing "five processors of which GetAddress is the only disclosed one" was wrong on both ends. | 🔴 Verdict undercounts; Meta Pixel is additionally a PECR Regulation 6 violation (advertising cookies without consent banner) — distinct Article 13-14 gap |
| 10 | **enterprise-verdict C14:** "Privacy Policy ↔ code alignment" | Direct quotes from `resources/js/views/Public/PrivacyPolicyPage.vue`: §5 (line 111): *"We do not share health data with any third party."* §7 (line 132): *"**We do not use third-party analytics or tracking services.**"* §7 contradiction is conclusive (Meta Pixel + AWIN + Plausible reality). §5 contradiction depends on whether `health_status` / `smoking_status` reach Anthropic/xAI via the system prompt — `RetirementActionDefinitionService.php:1606` and `DecumulationPlanner.php:184` surface the data; a per-field trace through `AdvicePromptBuilder::buildFinancialContext` is needed to prove the flow. | 🔴 §7 is a hard contradiction, Regulatory-authority referral risk; §5 is a conditional contradiction pending the trace |
| 11 | **system-map §1.1 happy-path flow** shows chat routes straight through `CoordinatingAgent::chat()` | Per §26 architecture correction, capture turns should route via `OnboardingChatDirector`. §1.1 was not updated. A reader paging only to §1.1 gets the pre-correction wrong flow. | 🟡 Internal consistency |
| 12 | **verdict Part F Critical list** + **Part K12 Critical count** (14) + **Part L4 count** (16) + **Part M4 count** (13) + **INDEX.md** (13/16) | Three different Critical counts across passes; the INDEX freezes a mid-pass snapshot | 🟠 Agent-ingestibility failure |
| 13 | **verdict grade** "D+ (45/100)" | Rubric for 21 dimensions → 45/100 is **not published**. Grade is author judgment, not reproducible. | 🟠 Either publish rubric or replace number with qualitative statement |

---

## 3. What's Made Up / Assumptions Stated as Fact

Items the docs state authoritatively that a neutral reader cannot verify and that the author did not verify either.

| # | Claim | Basis actually used | What's missing |
|---|---|---|---|
| 1 | "Multi-entity works end-to-end on persona-split" | Single manual test of ONE focus (protection) via ONE path (onboarding director) with ONE provider (xAI). 12 of 13 entity types untested. | Browser-test matrix (13 rows acknowledged missing in §3.4 but doc still labels P1 as "working") |
| 2 | "C6 Article 9 health data flows to LLM" | Field existence (`ProtectionPlanService.php:243`) + `buildFinancialContext` touching module analysis | **Full trace per field**. Evidence bundle §11 explicitly labels C6 "PARTIALLY VERIFIED". Verdict carries it as Critical without that qualification. |
| 3 | "Python sidecar is dead code" | `grep` absence of PHP callers | **CSJ direct confirmation** required — no external worker, no non-repo cron, no ad-hoc script. Sprint 0.16 currently says "unless CSJ confirms" — this unlock is absent from Sprint 0 but Sprint 0.16 is already "1 hour" scoped to deletion. |
| 4 | "Anthropic has Article 28 DPA on file" | Public policy wording | **Contract check** — verify with commercial / legal, not from code |
| 5 | "xAI is UK-adequate / has IDTA + UK Addendum" | Not addressed | **Unverifiable from code**. xAI Corp. (Nevada) — if no transfer mechanism exists, Sprint 0.4 "add xAI to Privacy Policy" paper-over is insufficient. |
| 6 | "The three-persona build was wrong" | CSJ's stated intent, session 69 | **PRD reconciliation** — `PRD-fyn-persona-split.md` (vault) is treated as "Authoritative" per integrated-plan §3.3. Did the PRD specify 3 or 2? If 3, the PRD needs amending, not the code. If 2, the code drifted — git blame the drift. Without this, "CSJ said two" is a thin basis for a 2-3 day code change. |
| 7 | "The enterprise grade is 45/100" | 21 dimensions → undisclosed weighting → a number | **Published rubric** or qualitative replacement |
| 8 | "Acceptable multi-entity under-fill rate" | Not stated anywhere | **Target threshold**. Regex extractor covers 4 focuses / ~40 providers — the rest silently return `[]`. Without a target, Sprint 1 eval harness can't tell you if the fallback is adequate. |
| 9 | "Sprint 0.19 is cheaper than completing three-persona" | Not established | **Effort comparison**. Three is what's built & tested; two requires new code + rewiring + new tests + revalidate extractor fires from the new path. Not obviously cheaper. |
| 10 | "Tests pass after rebase (2,448 + 1 flake carried forward)" | CSJTODO claim; no post-rebase run | **Actual rebase result**. 178-commit drift over `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php` makes broken tests likely. |

---

## 4. Scope Creep — items that do not belong in Fyn AI scope

Part M of the verdict corrected scope (LPA KPI dropped, app-wide trackers dropped, model currency dropped, three-persona → two-persona). **The correction did not propagate uniformly through upstream sections.** Residue:

| # | Residual item | Where it survives | What to do |
|---|---|---|---|
| 1 | T18 "Privacy Policy ↔ code alignment" in touchpoint index | integrated-plan §7 | Delete OR rename as "Fyn-AI-specific disclosures" narrow |
| 2 | T24 "Plausible event emission (`chat_opened`, `chat_message_sent`)" | integrated-plan §7 | Retain — Fyn-AI-specific signals |
| 3 | T25 "FCM push notifications" | integrated-plan §7 | Delete — app-wide |
| 4 | Sprint 4 task 4.22 "Privacy Policy update" | integrated-plan §8 | Keep narrow if Fyn-AI-specific (xAI disclosure, health data flow); drop if app-wide |
| 5 | §9 open questions 16-25 touch app-wide subjects | integrated-plan §9 | Prune to Fyn-AI |
| 6 | Verdict Part F Critical C11/C12/C13/C14/C15 mix | verdict Part F | Renumber to Part M5's 13 Criticals list so one consistent count exists |
| 7 | Pass 2 "Challenge 6" gaps M25-M36 (accessibility, age verification, children's data, vulnerable customer, etc.) | verdict Pass 2 | Part M should have pruned these; didn't |
| 8 | Critical count 9 / 10 / 14 / 16 / 13 across passes | verdict §K8, §K12, §L4, §M4, INDEX.md | Pick one number (§M4: 13) and rewrite everything upstream |

---

## 5. Real Gaps the Docs Miss (new findings from reviewers — should be in the spec)

These are reliability, regulatory, architectural, and agent-readiness gaps that the four docs do NOT name. Grouped by reviewer lens.

### 5.1 Regulatory — NEW since docs were written

- **FCA PS25/22 "targeted support" live 6 April 2026.** Creates a NEW regulated category between guidance and full advice; explicitly designed for AI-assisted consumer guidance. Mandatory labelling, segment-disclosure, conduct standards. Fyn's current "guidance not advice" framing is consistent with targeted support conceptually — but if Fyn makes segment-based suggestions after 6 April 2026 without targeted-support authorisation, it is **operating outside the regulatory perimeter**. Verdict C1's "no FCA analysis" framing is out of date in a load-bearing way. Sources: [PS25/22 FCA](https://www.fca.org.uk/publications/policy-statements/ps25-22-consumer-pensions-investment-decisions-rules-targeted-support), [Freshfields analysis](https://www.freshfields.com/en/our-thinking/blogs/risk-and-compliance/fca-unveils-near-final-rules-for-targeted-support-in-pensions-and-retail-investme-102lzft), [Hogan Lovells](https://www.hoganlovells.com/en/publications/targeted-support-update-fca-publishes-nearfinal-rules-on-new-form-of-advice).

- **"Rule of Two" lethal trifecta (Meta + Simon Willison, Nov 2025).** A system satisfying all three of (A) processes untrusted input, (B) has access to private/sensitive data, (C) can change state or communicate externally requires human oversight. Fyn's advice persona satisfies all three. Architectural mitigation required, not filter-based. Docs grade LLM01 as 🟠 High; should be Critical for a regulated UK product. Source: [Simon Willison on Rule of Two](https://simonwillison.net/2025/Nov/2/new-prompt-injection-papers/).

- **CJEU "special category by inference" doctrine (2024).** Financial data from which health/disability status can be inferred becomes Article 9. A retirement projection that adjusts for smoking status is intentional inference. Fyn's current consent model does not address this. Source: [Inside Privacy analysis](https://www.insideprivacy.com/eu-data-protection/special-category-data-by-inference-cjeu-significantly-expands-the-scope-of-article-9-gdpr/).

### 5.2 Architectural — fundamental re-framing

- **Fyn is not an "agent" by Anthropic's own taxonomy.** Anthropic's *Building Effective Agents* (Dec 2024) defines agents as "systems where LLMs dynamically direct their own processes". Fyn has deterministic pre-classification, fixed prompt layers, and a capped loop — it is a **routing workflow feeding an orchestrator-workers pattern with two workers (advice, data_capture) and handoff tools**. Calling it an agent invites over-compliance with agent-specific hardening guidance and under-applies ACI (agent-computer interface) guidance, which is where Fyn's actual weakness lives. Source: [Anthropic post](https://www.anthropic.com/engineering/building-effective-agents).

- **17 of the tool handlers in `app/Agents/CoordinatingAgent.php` return `['action' => 'fill_form', ...]` — they pre-fill forms, they do not write to DB** (lines 1510, 1549, 1595, 1742, 1809, 1887, 2018, 2065, 2132, 2165, 2205, 2244, 2861, 2923, 2978, 3021, 3142)**.** (v2 correction — v1 of this synthesis said "13" based on counting only `create_*` tools; the accurate count via `grep "'action' => 'fill_form'"` is 17, including modification tools like `update_will`, `update_power_of_attorney`, plus `create_holding`, `create_trust`, `create_family_member`, `create_business_interest`, `create_chattel`.) The frontend POSTs to the standard module API. Consequences the docs never surface:
  - Tool `description` strings lie to the model ("Create a savings account…")
  - The model's next-iteration context never receives a real `entity_id`, so it cannot chain `create_property → create_mortgage` with any grounded reference
  - If the user closes the form modal client-side, Fyn told them "I've created X" but X does not exist
  - `[AI-AUDIT]` fires on `Tool executed` for something that did not execute (see `audit-evidence.md §18`)
  - `ai_advice_logs.tools_called` records the tool name as if it succeeded
  - The error path "form closed / validation failure / network failure during form submit" has no Fyn-side feedback loop — the assistant context does not know the fill failed, so the next turn may suggest actions against a record that does not exist
  
  The regulatory exposure on C3 (`update_record` over-exposure) is **narrower than the verdict implies** — the LLM has direct DB-write authority only for `update_record`, `delete_record`, `update_profile`, `set_expenditure`. All other create paths route through existing form-submit APIs with their own validation and audit trails. But the UX truth story needs fixing in the tools themselves.

- **SSE has no abort detection anywhere** (verified: `grep -rn "connection_aborted\|ignore_user_abort" feature/fyn-persona-split -- 'app/'` → zero matches)**.** No `connection_aborted()` check, no `ignore_user_abort(true)` guardrail, no idempotency-key for retries. Users billed for turns they never saw. `persona_state` can be persisted mid-turn with a subsequent crash leaving state stuck in `capturing` with no `pending_advice_question`. This is the single biggest reliability gap for a streaming chat product and not flagged anywhere in the four docs.

- **Token-budget race via `Cache::remember($cacheKey, 300, …)` at `app/Traits/HasAiGuardrails.php:221`.** Two concurrent SSE requests land 200ms apart, both read cached usage, both pass the check, both run. `invalidateDailyUsageCache` runs after. Pro user at 1.95M/2M can be pushed to 2.95M. Billing correctness defect. `throttle:20,1` does not help — budget is per-day.

- **Provider cache coherence race.** `Cache::forever('ai_provider', …)` in `app/Http/Controllers/Api/AdminController.php` — if admin toggles between turns in the same conversation, new request rebuilds the prompt with Anthropic `cache_control: ephemeral` markers but may hit xAI which does not understand them. Silent cache misses or 400s.

### 5.3 Agent-readiness — from the CLI agent-readiness reviewer

- **Tool catalogue divergence** (v2 correction — inverted from v1): **Anthropic 37 tools, xAI 33.** `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `create_holding` exist only on Anthropic. `list_records` and `set_expenditure` are on both. A runtime provider flip from Anthropic to xAI silently loses 4 tools, including the 3 onboarding capture tools — meaning **onboarding Fyn depends on Anthropic being the active provider, or it silently breaks.** Not named in v1.
- **Four tool-result schemas** (`{blocked}`, `{error}`, `{warning}`, `{action:'fill_form'}`). `is_error: true` set only for the `error` path. Model often proceeds on `warning` as if the record existed.
- **`update_record.fields` is `additionalProperties: true`** — xAI `strict: false`. Model can submit any key; `array_intersect_key` silently drops unknowns; audit logs success. Worse than the 2-field blocklist because the blocklist at least fails visibly. **Sprint 0.5 must also make the schema strict.**
- **Tool-call history is summarised before the model sees next turn.** `app/Traits/HasAiChat.php::summariseToolInput` (line 719) / `summariseToolResult` (line 749) strips fields; the summarised payload is referenced in `buildMessageHistory` (line 679). The model never sees created-entity IDs, validation errors, or route paths across turns. Observable failure: "Fyn forgets what it just created".
- **`MAX_TOOL_CALLS_PER_TURN = 5` is global, not per-kind.** A user with 6 ISAs blows the budget — the 6th `create_savings_account` never runs. Reads eat writes' share. This is the **real root cause** of multi-entity, not just "the model drops a tool call". Fix: separate budgets for reads (5) vs writes (10) when classifier type is `data_entry`.
- **10-item parity gap**: document upload (AIExtractionService exists but not exposed as a tool), spouse linking, assumption configuration, Monte Carlo trigger, risk questionnaire, holding delete (enum missing in `delete_record`), `create_will` / `create_power_of_attorney` declared in persona allow-list but not registered in tool-definition classes, goal-complete / goal-abandon.
- **Tool descriptions written for humans, not the model.** `update_record` vs `update_profile` for income — no exclusion clause. `create_asset` vs `create_chattel` — both cite collectibles. The model guesses.
- **Preview-mode prompt tells the model it can "add records"** but the tool list is read-only. Preview personas regularly get "I'll add that to your plan now" with no corresponding creation.

### 5.4 Reliability — concrete failure modes

- **No per-provider timeout parity.** `app/Services/AI/XaiClient.php:64` sets `'timeout' => 120`; Anthropic path in `app/Traits/HasAiChat.php:287-305` uses SDK defaults (v2: v1 cited 238-252 — stale). Asymmetric failover.
- **`AIExtractionService` is synchronous, with no wrapping Job and no retry.** (v2 expanded from v1.) Every exception → `Document::STATUS_FAILED`. There is NO `ExtractDocumentJob` / `ProcessDocumentJob` in `app/Jobs/` (verified: `ls app/Jobs/ | grep -i extract` → zero matches) — the service runs inside the web request, blocking a FPM worker for up to 120 seconds on the provider call. A 30-second xAI 502 storm loses every concurrent upload with no retry infrastructure. PDF size caps: a 15 MB cap exists for **scanned PDFs only** (`app/Services/Documents/AIExtractionService.php:31` = `MAX_SCANNED_PDF_SIZE`, enforced at `app/Services/Documents/AIExtractionService.php:783`); **text-based PDFs have no cap** — a 50 MB PDF can OOM the worker. Sprint 4 minimum: wrap in Job with `$tries` + `backoff`; add text-PDF size cap.
- **Gap-fill double-insert on retry.** User says "Aviva and Vitality", SSE drops, user retries same message. LLM emits Aviva, extractor gap-fills Vitality again. First-attempt Vitality may or may not have saved. No server-side dedup against existing records for gap-fill. **Double-counted protection coverage** in a regulated financial product.
- **Audit-log write can fail silently.** `Log::info` swallows I/O errors. Tool effect persists, audit line absent. Verdict C7 frames as tamper-evidence only; availability angle missing.
- **Audit-log truthfulness problem** (v2 new — see `audit-evidence.md §18`). The `[AI-AUDIT]` line at `app/Agents/CoordinatingAgent.php:770` and `ai_advice_logs.tools_called` at `app/Traits/HasAiChat.php:612` both fire BEFORE the frontend form submit. If the user closes the modal / form validation fails / network drops, the audit trail records tool execution for records that do not exist. Distinct from tamper-evidence (C7) and `update_record` over-exposure (C3). Belongs in Sprint 0.18 scope.
- **`app/Traits/HasAiChat.php::generateTitle` sends raw user text to LLM + persists to `ai_conversations.title`** with no sanitation — only `mb_substr` truncation at `app/Traits/HasAiChat.php:704`. No `strip_tags`, no HTML escape. XSS surface in sidebar + `AiAudit.vue`; prompt-injection vector. Flagged in system-map §21 Q8; Sprint 0.24 adds the sanitation task.
- **Handoff-contract failure mode** (v2 new — see `audit-evidence.md §19`). `app/Services/AI/FynPersonaInvoker.php` silently stays in capturing state on a malformed handoff payload (wrong arg shape, wrong tool-name casing, partial JSON from SSE chunk boundary). No validator in `app/Services/AI/StructuredResponseValidator.php` for the handoff payload. User loops until `capture_max_turns` (6) fires the timeout. Add validation + `handoff_malformed` SSE event.
- **Throttle `20/min` breaks voice input on mobile.** `VoiceInputButton.vue` re-submits partials; 30-second voice session can fire 10-20 messages. 429 handled with generic error in `aiChat.js`.

### 5.5 Prior-art gaps

- **Multi-entity SOTA (2025-2026) is list-return extractor tools with strict JSON schema.** Instructor `Iterable[T]`, Pydantic AI `list[Entity]`, OpenAI Structured Outputs `strict: true`, Anthropic `input_schema` with `additionalProperties: false`. Failure rates in production: 0.4% vs ~32% for unstructured. Sources: [OpenAI](https://openai.com/index/introducing-structured-outputs-in-the-api/), [Instructor](https://python.useinstructor.com/), [Anthropic tool use](https://docs.anthropic.com/en/docs/build-with-claude/tool-use), [constrained decoding benchmarks](https://brics-econ.org/constrained-decoding-for-llms-how-json-regex-and-schema-control-improve-output-reliability). See §7 for recommended fix shape.

- **Persona-split pattern maps cleanly onto OpenAI Agents SDK / LangGraph supervisor.** Fyn's `delegate_to_capture` / `capture_complete` is **supervisor-with-two-workers**. But Fyn's "strip from SSE and intercept" pattern is **not documented in any major framework** — it has no prior-art validation. Chunk-boundary failure risk if the handoff tool name splits across SSE chunks.

- **SEC Rule 17a-4 (2023 amendment) cryptographic audit-trail alternative + AuditableLLM (MDPI, Jan 2025) hash-chain framework** are the established prior art for tamper-evident LLM audit trails. Sprint 0.18 "1 day" to "begin AI DB audit migration" is under-scoped by 5×; realistic is 5-7 days including chain design + HMAC signing + retention + erasure-compatibility + weekly integrity-check job.

### 5.7 Canonical gaps — where the current branch violates §0

Applying the canonical §0 to the current branch reveals six places where code and contract diverge. Every one of these must be closed by the spec.

1. **Visible handoff leak.** `persona_state_change` SSE event (`app/Services/AI/FynPersonaOrchestrator.php::personaStateChangeEvent`, lines 382-388) + capturing pill + input-placeholder swap (`resources/js/store/modules/aiChat.js:511-516`; `resources/js/components/Shared/AiChatPanel.vue`) directly violate §0's "user never sees the handoff". Must be removed.
2. **Bubbles during inline capture.** Onboarding Fyn's bubbles are a visible signal of mode (emitted via `quick_replies` SSE events from `app/Services/Onboarding/OnboardingChatDirector.php`). If Advice Fyn hands off to Onboarding Fyn for a post-onboarding inline capture and that capture emits `quick_replies` SSE events, the user sees bubbles appear mid-advice-chat — visible switch. `handleInlineCapture` (proposed method) must emit conversational prompts only, never `quick_replies`.
3. **Missing billing / subscription tools.** §0's invoice example requires `get_subscription_status`, `list_invoices`, `get_current_plan` — none exist in `app/Services/AI/AiToolDefinitions.php` or `app/Services/AI/XaiToolDefinitions.php` (verified: zero `'name' => 'get_subscription_status'` matches across either file). Catalogue is 37 Anthropic / 33 xAI today; spec adds 3 → 40/36 post-parity.
4. **No memory surfacing in prompt builders, and no cross-conversation index.** §0 says Onboarding Fyn "does not ask about information already entered" and resurfaces info "at the right time". Current prompt builders (`app/Services/AI/AdvicePromptBuilder.php`, `app/Services/AI/Prompts/DataCapturePromptBuilder.php`, `app/Services/Onboarding/OnboardingPromptBuilder.php`) don't enumerate a known-facts block; the LLM is left to infer what's known from context. Result: repeated asks are possible. Spec must require:
    - **Known-facts block** in every prompt builder, populated from the authoritative DB state (`users.*`, `family_members`, linked module tables) + the current-turn parked facts (`ai_conversations.onboarding_parked_facts`) + the current-conversation message history (`ai_messages` already in context).
    - **Conversation index** — a new DB surface summarising each conversation with `summary`, `topics`, `entities_mentioned`, `intents_stated`. Either new JSON columns on `ai_conversations` or a separate `ai_conversation_summaries` table. Written at conversation end (STATE_DONE transition) or via an observer on each user message. Fyn queries the index via a cheap `search_conversation_index` tool (new) **only when** the known-facts block and current-conversation history are both silent on the required fact. This replaces the "intent memory" fourth store from earlier drafts — simpler, lower write volume, explicit retrieval trigger, and makes cross-conversation surfacing in-scope via the index rather than requiring full-history scan.
5. **No resume-summary strings on state records.** §0 says Onboarding Fyn picks up "where we left off" with context ("family details"). `app/Services/Onboarding/OnboardingStateMachine.php` state records do not carry a `resume_summary` field today (verified: `grep -n "resume_summary" feature/fyn-persona-split -- 'app/Services/Onboarding/'` → zero matches). Spec must add one per state.
6. **Entry-point → journey mapping unspecified.** §0 says journeys are mapped "where they enter onboarding from". Branch supports `?from=fyn` but does not pre-select a journey from entry source. Spec must define the `entry_source → journey_id` map (`from=retirement → retirement journey`, `from=savings → budgeting journey`, etc.) and state whether pre-selection skips `STATE_PATH_CHOICE`.

### 5.6 Terminology / framing

- "**Three-persona architecture**" vs "**two-persona**" is a misnamed argument. On persona-split, `config/fyn_personas.php` has exactly 2 entries (`advice` + `data_capture`). The "third persona" the docs describe is the **onboarding director** — a completely separate code path invoked by `AiChatController` before `FynPersonaOrchestrator` is reached. The correct reframing: *the onboarding director and the `data_capture` persona are two implementations of the same concern (conversational data capture). CSJ's ask is to delete `data_capture` and route orchestrator capture turns into the director*. This reframing matters because it reveals Sprint 0.19 isn't a persona collapse — it's an **entry-point consolidation** that couples the orchestrator to the director's capture machinery, including `AssetCaptureEntityExtractor` rewiring (not named anywhere in the current Sprint 0.19 description).

---

## 6. Sprint 0 Honest Re-estimate

Integrated-plan header: "1-2 days". Honest per-task, building on the five reviewers:

| Task | Doc says | Honest | Rationale |
|---|---|---|---|
| 0.1 Rebase | 2-4 hrs | **0.5-1 day** | 178-commit drift (not 72), 8+ overlapping files |
| 0.2 Pest run post-rebase | 30 min | **0.5 day** | Probable test failures from rebase; cost to triage |
| 0.3 Close PR #214 | 5 min | 5 min | ✅ |
| 0.5 `update_record` per-entity allowlist | 1 day | **2 days** | 15+ entities × ~10 updateable fields ≈ 150 permissions + tests + make schema strict |
| 0.6 `delete_record` confirmation | 4 hrs | 4 hrs | ✅ |
| 0.7 ConsentService runtime check | 2 hrs | **0.5 day** | Check itself 2 hrs, plus UX design for "consent withdrawn mid-conversation" |
| 0.8 Sanitise user-controlled prompt fields | 4 hrs | **1 day** | Regex 4 hrs + structural `<user_provided>` wrapping per OWASP Cheat Sheet + tests |
| 0.16 Remove Python sidecar | 1 hr | 1 hr | ✅ (after CSJ confirmation) |
| 0.17 Remove OpenAI stale config | 5 min | 5 min | ✅ |
| 0.18 AI DB audit migration | 1 day | **5-7 days** | Hash-chain append-only table + HMAC signing + retention policy + erasure pattern + weekly integrity-check job, per SEC 17a-4 / AuditableLLM |
| 0.19 Collapse three-persona | 1-2 days | **2-3 days** | New `handleInlineCaptureTurn` method + rewire `AssetCaptureEntityExtractor` into the new path + tests for the inline multi-entity round-trip |

**Honest total: 12-17 days of engineering work.** Not 1-2.

Additional Sprint 0 tasks the reviewers recommend adding:

- **0.20 SSE abort detection + idempotency key** (2-3 days) — biggest reliability gap in the system.
- **0.21 Token-budget atomic check-and-increment** (1-2 days) — billing correctness.
- **0.22 Provider-swap write lock** (1 day) — cache coherence.
- **0.23 Gap-fill dedup key against DB** (0.5 day) — close the retry double-insert vector.
- **0.24 `generateTitle` sanitation** (2 hrs) — close the LLM01 / XSS vector.
- **0.25 Rebase-friendly `AppLayout.vue` conflict strategy** (document only) — don't start 0.1 blind.

**Realistic Sprint 0: ~4 working weeks for one engineer.**

---

## 7. Multi-Entity — the User's Top-Priority Pain Point

The user's stated concern ("Multi-entity capture does not work, never has, but it needs to.") is **load-bearing**. Synthesis across reviewers:

### 7.1 Why it's broken (four reasons — all independent)

1. **Single-entity schema shape biases the model.** Each `create_*` tool's `input_schema` is flat with single-valued properties. The model infers "pick the most salient entity" from schema shape. A `{policies: [...]}` shape would signal batch intent.
2. **Global `MAX_TOOL_CALLS_PER_TURN = 5` is eaten by reads.** Classifier attaches required read tools (`get_recommendations`, `list_goals`, etc.) that run before the writes. For a "data_entry" classification with 6 user-mentioned entities, the budget is exhausted before the writes complete.
3. **Multi-entity instruction lives in prompt layer (`FcaProcessInstructions.php`)** — (v2 correction: v1 mis-attributed this to `ComplianceRules.php`.) Verified on-branch: the multi-entity affordance *is* in the tool descriptions ("You MAY call this tool multiple times in the same turn when the user mentions multiple items of this type") after Phase A/B/C of the multi-entity-capture plan. What remains: the **flat single-entity schema shape** still biases the model even when the description invites repetition. Batch-shaped tools (`capture_protection_policies(policies: [...])`) would close this the rest of the way.
4. **Regex extractor is a symptom-level fix, wired into BOTH paths but covering only 4 focuses.** (v2 correction: v1 claimed "wired into ONE path"; it is wired into both onboarding director AND `app/Services/AI/FynPersonaInvoker.php::emitGapFillFromCaptureContext` at lines 251-300.) Covers 4 focuses (protection / savings / retirement / investment) of 18+ entity types. Provider list is ~40 — unknown providers silently return `[]`. Under-fill is accepted. On persona-split, the 4-focus limit means 12+ entity types (goal, life_event, property, trust, will, POA, business_interest, chattel, family_member, liability, estate_gift, holding) still rely on raw LLM emission with no safety net. `FynPersonaInvoker::inferFocusesFromEntityTypes` silently drops unsupported entity types before even attempting extraction.

### 7.2 Recommended fix (combining reviewer recommendations)

**Structurally:**

- **Add batch-shaped extractor tools alongside singular ones.** `capture_protection_policies(policies: [...])`, `capture_savings_accounts`, `capture_pensions`, `capture_investment_accounts`. Strict JSON schema with `additionalProperties: false`. Each tool persists all items or emits all fill_form events. Matches OpenAI / Instructor / Pydantic AI convention and Anthropic's native parallel tool use.
- **Split tool budgets:** 5 reads + 10 writes per turn when classifier type is `data_entry`. Keeps the guardrail while letting writes breathe.
- **Move the per-entity instruction** from `ComplianceRules.php` to each `create_*` tool's `description` field: *"If the user mentions multiple policies, emit this tool once per policy; do not merge."* Per-decision salience.
- **Wire `AssetCaptureEntityExtractor` into `FynPersonaOrchestrator::runCaptureTurn`** as a secondary defence-in-depth control. Log `gap_fill_rescued=true` as a G1 eval-harness KPI. Retire when gap-fill fire rate < 2%.
- **Add dedup key to gap-fill.** Before synthesising a gap-fill for "Vitality £100k", query existing policies where `(user_id, provider, policy_type_group, created_at_within_24h)` to avoid retry double-insert.
- **Extend coverage to the other 12 entity types** — goals, family, life events, properties+mortgages, trusts, wills, POAs, business interests, chattels, liabilities, estate gifts, holdings.

**Evaluation:**

- Seed 30-50 golden conversations in Sprint 1 (not Sprint 2). Cover the 22 query types × 5 preview personas × 6 multi-entity scenarios × 3 prompt-injection strings × 2 handoff flows. Mocked-LLM regression + weekly real-provider run.

---

## 8. CSJ decisions (answered 24 April 2026)

1. **FCA posture — GUIDANCE ONLY.** No targeted-support authorisation, no full-advice classification. Sprint 4 C1 reframes from "commission FCA authorisation analysis" to "guidance-hygiene audit + external legal opinion for the guidance posture". The phrase *"You think like a qualified financial planner"* in `CoreIdentity.php` is misaligned with this posture and must be rewritten in Sprint 1 (not Sprint 4). Every advice-type response must signpost to regulated advice. Targeted-support (PS25/22, live 6 April 2026) is noted as a later option but not on the current roadmap.

2. **Persona count — TWO, per canonical §0 above.** The full behavioural contract is §0 (top of this document). Code-level realisation:

   - **Onboarding Fyn** (code: `OnboardingChatDirector` promoted + extended). Bubble-driven state machine for the onboarding flow. Multi-line grouped-extract turns. Direct-writes to DB via existing services (`SpouseLinkingService`, `SavingsAccountService`, etc.). Persists state in `users.onboarding_fyn_step/path/selection/context` + `ai_conversations.onboarding_parked_facts`. Resumes on reconnect with natural-language greeting + Yes/No bubble (requires NEW `resume_summary` field per state record). Has memory: prompt builders inject a `<known_facts>` block sourced from `users.*`, `family_members`, linked module tables, and parked facts. ONLY code path allowed to mutate user data.

   - **Advice Fyn** (code: NEW `AdviceFyn` class). Read-only. Fetches user data via read tools (`list_*`, `get_module_analysis`, `get_recommendations`, `get_tax_information`, `get_subscription_status` [NEW], `list_invoices` [NEW], `get_current_plan` [NEW]). Routes interpretive queries through the recommendation engine (`orchestrateAnalysis` for holistic / cross-module; single-agent `analyze()` for module-scoped; `NetWorthService::getOverview` or equivalent for pure-factual). Emits structured `advice_response` SSE event projecting `orchestrateAnalysis` + `HolisticPlanner` output. Never mutates DB state. When data is missing, emits `delegate_to_capture` → Onboarding Fyn's `handleInlineCapture` runs silently → control returns to Advice Fyn → original query re-runs with fresh data.

   - **Handoff invisibility.** `persona_state_change` SSE event + capturing pill + input-placeholder swap REMOVED. Inline-capture turns emit conversational prompts (NOT `quick_replies`). `capture_complete` may remain but styled as a normal assistant bubble (no capture-mode badge).

   - **DELETE on persona-split:** `FynPersonaOrchestrator`, `FynPersonaInvoker`, `FynPersonaRegistry`, `DataCapturePromptBuilder`.

   - **KEEP:** `HandoffContract` (constants), `CaptureContext` VO.

   - **NEW:** `AdviceFyn` class; `OnboardingChatDirector::handleInlineCapture()` method with full orchestrator-state logic (cancel, timeout, preview short-circuit, state persistence, entity-extractor emission); `resume_summary` field on state records; `<known_facts>` block injection in both prompt builders (sourcing DB + parked facts + current-conversation history); 3 billing/subscription tools; conversation-index DB surface (`summary`, `topics`, `entities_mentioned`, `intents_stated` — either JSON columns on `ai_conversations` or a new `ai_conversation_summaries` table) + observer/job to populate it + `search_conversation_index` tool for Fyn to query.

   **Revised LOC scope (v2, from three-pass review Pass 3):** delete ~1,238 LOC prod + ~1,000 LOC tests; add ~1,000-1,200 LOC new prod + ~400-500 LOC new tests; **net reduction ~500-800 LOC** (not the "~800 LOC deletion" claimed in v1). Two Fyns in code matching the two Fyns in the mental model. A third persona is YAGNI.

   **Critical subtasks not named in v1:**
   - Move `AssetCaptureEntityExtractor` emission logic from `FynPersonaInvoker::emitGapFillFromCaptureContext` into `handleInlineCapture` — otherwise B-1's post-onboarding gap-fill silently regresses.
   - Migration to clear `persona_state` for in-flight capturing-state conversations (see `audit-evidence.md §20`).
   - Remove all visible-handoff UI surfaces per §5.7 #1 + #2.
   - Add 3 billing/subscription tools per §5.7 #3 + `audit-evidence.md §22`.
   - Add `<known_facts>` block builder per §5.7 #4.
   - Add `resume_summary` field per state per §5.7 #5.
   - Define `entry_source → journey_id` map per §5.7 #6.

3. **Multi-entity accuracy/recall — 95% baseline, tunable up.** Starting floor: 95% entity-count recall and 95% field precision per focus (protection, savings, retirement, investment, mortgage). Non-tunable hard-fail floors on top of that: 100% entity validity (persisted records must pass full `FormRequest` validation), 100% monetary value accuracy (no £ drift), 100% cross-entity consistency (no field-bleed between entities in the same message), 0% fabrication (extractor never invents fields). Every Sprint 1 eval run publishes a **per-tool scorecard** showing validity / recall / precision / value accuracy / consistency / fabrication independently — CSJ reviews the scorecard and raises any floor at will via `config/fyn_eval.php`. Thresholds can never be lowered without an explicit CSJ-signed commit message. Full rules + escalation path in `fyn-rubrics.md` §B under "Validity, accuracy, recall — what each means" and "The cost-benefit envelope". **Expected Sprint 2 ratchet:** mortgage → 100%/100%, protection + savings → 98%/98%, add 12 remaining entity types at 90% baseline.

4. **Python sidecar — DELETE.** Confirmed by code walk: no PHP callers anywhere (`grep -rn` across `app/`, `routes/`, `config/`, `database/`, `resources/`; no `Process::run`/`exec`/`shell_exec`/`proc_open`; no Procfile/systemd/supervisor/scheduler). Uses regular `anthropic` Messages SDK, NOT `claude-agent-sdk` — the "Agent SDK" label in system-map §24 was misleading. Three patterns worth harvesting as spec ideas (not dependencies): Pydantic-equivalent output validation, task-type-specific prompts, externalised PreToolUse hook. None require keeping the Python code. Sprint 0.16 proceeds as a 1-hour deletion.

5. **Local-first — UNAMBIGUOUS.** Nothing deploys to `csjones.co/fynla` or `fynla.org` until 100% verified on `localhost:8000`. This means: every sprint's Pest suite passes locally, every UI change browser-tested locally, every rubric-B eval run passes locally. Sprint 3 "deploy to dev" is conditional on local verification first, not concurrent with it. Corollary: the audit-synthesis's "deploy gate sequencing" question (was §8 #5) is resolved — nothing blocks dev deploy except the local verification gate.

6. **Terminology — doesn't matter to CSJ.** The spec will use "routing workflow → orchestrator-workers pattern" where literature references matter (e.g. citing Anthropic best-practices), and "Fyn" / "Onboarding Fyn" / "Advice Fyn" everywhere else. Consistency more important than label.

7. **Rubric — BUILD IT.** See companion file `fyn-rubrics.md`:
   - **Rubric A:** Enterprise Assessment, 10 dimensions × 5 levels = 40-point score. Reproducible. Current Fyn scores **4/40 — 🔴 Pre-launch**. Projected post Sprint 0+1: **~17/40 — 🟠 Limited beta**.
   - **Rubric B:** Eval Harness, 65 golden conversations in Mode 1 (mocked, CI gate) + Mode 2 (real provider, weekly). Per-metric thresholds published; PR blocked on regression.
   - Both rubrics replace the undisclosed D+(45/100) grade. "Improvement" is only real if it moves a rubric-A level or a rubric-B metric.

---

## 9. Recommendations for the correction pass (before drafting the spec)

1. **Canonical facts pass.** Write a one-pager with every fact that contradicts the existing docs (see §2 and §5.2). Every subsequent doc revision retracts the contradicting sentence. Suggested inclusions: 178-commit drift, cache metrics ARE persisted, `AiAudit.vue` exists, tool count is 23 on Anthropic / 29 on xAI, `handleInlineCaptureTurn` is NEW code, multi-entity broken post-onboarding, all `create_*` tools are form-prefillers.
2. **Scope pass.** Apply Part M's rule (app-wide vs Fyn-AI) uniformly. Prune T18/T24/T25 in touch-point index; prune Sprint 4.22; pick ONE Critical count and enforce it.
3. **Effort honesty pass.** Rewrite Sprint 0 envelope from "1-2 days" to "3-4 weeks". Move 0.5/0.8/0.18/0.19 to Sprint 1 if user prefers smaller sprints; or just size Sprint 0 honestly. Add 0.20-0.25.
4. **Grade rubric pass.** Either publish 21-dim weighting or replace number with qualitative statement.
5. **Terminology pass.** Rewrite every "agent" occurrence to distinguish the LLM workflow from the abstract agent concept. Specifically: `AIExtractionService` is a tool; `CoordinatingAgent` is a dispatcher (misnamed); `FynPersonaOrchestrator` is a supervisor; advice/data_capture are workers; `delegate_to_capture` is a handoff primitive. Use one consistent lexicon.
6. **Multi-entity pass.** Promote multi-entity from "embedded in Sprint 2" to a first-class Sprint 1 structural fix (batch-shaped tools + split budget + per-tool description + gap-fill rewiring + extractor coverage extension).
7. **Agent-ingestibility pass.** Fix T-index numbering (T1-T25 consecutive, no gaps), retire stale Sprint 4 task numbers (4.23/4.24/4.26/4.27 remnants), pick one architecture statement and propagate through §1.1 + §4.2 + §6.3 + §12 + §N uniformly.
8. **Risk register pass.** Add contingencies: "if rebase fails", "if FCA position comes back advice", "if xAI terms change", "if power-user blows budget at launch", "if collapse breaks onboarding".

9. **Canonical-contract pass (v2, new).** The spec must explicitly resolve each of these open decisions surfaced by running the canonical §0 against the branch:
   - **A1** Remove `persona_state_change` SSE event and all capturing-mode UI.
   - **A2** `handleInlineCapture` MUST NOT emit `quick_replies`.
   - **A3** `capture_complete` bubble styled as normal assistant message.
   - **M1** Memory = three DB-backed stores + one index, not four stores (per-user clarification). The three stores: (a) authoritative user DB state (`users.*`, `family_members`, linked module tables); (b) current-turn parked facts (`ai_conversations.onboarding_parked_facts`); (c) current-conversation message history (`ai_messages`). The index: `ai_conversations` per-conversation summary (`summary`, `topics`, `entities_mentioned`, `intents_stated`). Prompt builders inject known-facts from stores (a)+(b)+(c). The index is queried ONLY when the known-facts block is silent on the required fact.
   - **M2** Surfacing trigger = "the fact is an input to the current query's engine call", not arbitrary LLM judgement. Retrieval order: DB → parked facts → current conversation → index. Each layer falls through to the next only when empty.
   - **M3** Cross-conversation memory IS in MVP scope via the index (cheap scan + load-as-needed). What's out of scope: any "always load last N conversations" / full-history-replay behaviour. Index-based retrieval only.
   - **R1** Onboarding Fyn resume greeting + Yes/No bubble on reconnect.
   - **R2** `resume_summary` field added to each `OnboardingStateMachine` state record.
   - **R3** Advice Fyn does NOT auto-greet; user initiates.
   - **J1** 4 journey options remain (budgeting, goals, protection, retirement).
   - **J2** Explicit `entry_source → journey_id` map; pre-selection skips `STATE_PATH_CHOICE`.
   - **S1** Closed list of IN-remit query types; out-of-remit policy = polite decline + human-support pointer.
   - **S2** 3 billing/subscription tools added (`get_subscription_status`, `list_invoices`, `get_current_plan`).
   - **S3** Advice Fyn has two response modes: factual (bypass engine) vs recommendation (engine wrapping required).
   - **S4** Tax optimisation routes through `TaxOptimisationAgent::analyze`.
   - **S5** Interpretive language in Advice Fyn responses must map to engine output fields; enforced via eval regex.
   - **H1** Advice Fyn uses `DataReadinessService` (existing) to identify missing inputs before emitting `delegate_to_capture`.
   - **H2** Capture phase uses same conversational register as Advice Fyn (no "switching to capture mode" preamble).
   - **H3** Capture failure modes specified: 3 attempts → graceful degraded response; abandon → timeout; multi-field partial → loop.
   - **W1** `AdviceFyn` tool list excludes every DB-mutating tool by construction.
   - **W2** `create_what_if_scenario` (analytics artefact) allowed; other writes not.
   - **E1** Engine call scoped by query type: `holistic_*` / `cross_module_*` → `orchestrateAnalysis`; module-scoped → single-agent `analyze()`; factual → bypass engine.
   - **E2** Direct-write services invalidate the holistic cache to ensure Advice Fyn picks up fresh data mid-conversation.
   - **E3** `advice_response` SSE event shape is a projection of `orchestrateAnalysis` + `HolisticPlanner` output; no new engine invented.
   - **U1** System-level emissions (token limit, consent, preview, maintenance) exempt from the handoff-invisibility rule.

---

## 10. What happens next

The spec CSJ intends to draft should be built on:

1. This synthesis document (consolidated facts + reviewer findings).
2. The evidence bundle `audit-evidence.md` (code-grounded ground truth with file:line anchors).
3. CSJ's answers to §8's seven questions.
4. A corrected copy of the four planning docs (corrections from §2, §3, §4 applied).

Without (3) and (4), the spec will inherit the same load-bearing errors the reviewers found. Drafting the spec now would repeat the plan → execute → broken cycle named in `memory/feedback_breaking_frustration_cycle.md`.

---

*Synthesis prepared 24 April 2026. Sources: audit-evidence.md §1-17; five reviewer reports (web, best-practices, reliability, agent-readiness, adversarial-document); direct code reads across `main` and `feature/fyn-persona-split`. Every file-level claim anchored to path + line. Every external source cited with URL.*
