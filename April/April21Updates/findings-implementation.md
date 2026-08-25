# Implementation Findings Log

Running log of issues discovered during implementation of the Fyn Persona Split + Onboarding UX Overhaul. Entries timestamped. Fixes linked to commits.

Branch: `feature/fyn-persona-split` off `onboardingFyn`
Started: 2026-04-21 session 3

---

## 2026-04-21 — Phase 3: Prompt size measurement

Spec + PRD claim DataCapturePromptBuilder produces "~500 tokens vs 1,600 for AdvicePromptBuilder." Actual measurement (empty user, minimal CaptureContext):

- DataCapturePromptBuilder: 11,229 chars (~2,800 tokens)
- OnboardingPromptBuilder::buildAssetCapturePrompt: 11,336 chars (~2,800 tokens) — identical layering

Both include CoreIdentity (~8,500 chars) + ComplianceRules + their specific instructions. The 500/1,600 figures are aspirational.

**Real token win is still substantial** — AdvicePromptBuilder adds user_profile + financial_context (full orchestrateAnalysis aggregation) + existing_records + data_completeness + review_due + knowledge + required_tools + kyc_result + module_context. Empirically ~15,000-40,000 chars depending on user data. So the split saves ~4-10K tokens per data-capture turn, not 1.1K as spec suggests.

**Action:** no code change needed. Spec/PRD accuracy-only — note for follow-up doc sync. The architectural benefit (skipping orchestrateAnalysis + existing records build + KYC check for data-only turns) is the load-bearing win, not the prompt string length.

---

## 2026-04-21 — Phase 4 bug: LPA `source` enum truncation

Caught during smoke test of `handleCreatePowerOfAttorney`. First attempt used `source => 'ai_chat'` which triggered `SQLSTATE[01000]: Warning: 1265 Data truncated for column 'source'`.

`database/migrations/2026_03_16_100001_create_lasting_powers_of_attorney_table.php:22` defines:

```php
$table->enum('source', ['created', 'uploaded'])->default('created');
```

Only two valid values. AI-captured LPAs are semantically "created" (the user described the LPA and Fyn structured it), so the correct value is `'created'`. Fix shipped in same commit.

Lesson: before inserting into unfamiliar tables, check the migration for enum constraints. MySQL 8 truncates silently with a warning unless `sql_mode=STRICT_*` is set; our dev mysql is strict-ish but the warning raises only through `DB::raw`, not Eloquent — so the `Data truncated` error surfaces only on the actual insert.

## 2026-04-21 — Phase 5 finding: cross-provider tool drift (pre-existing, out of scope)

Integrity test for FynPersonaRegistry surfaced that three tools are present in only one provider's tool definitions:

- `list_records` — defined in XaiToolDefinitions (`analysisTools()`) only; not in AiToolDefinitions. Handler exists in CoordinatingAgent dispatcher at line 717.
- `create_holding` — defined in XaiToolDefinitions (`additionalCreationTools()`) only; not in AiToolDefinitions.
- `set_expenditure` — defined in XaiToolDefinitions (`expenditureTools()`) only; not in AiToolDefinitions.

This is pre-existing drift — users on the Anthropic provider cannot use these tools even though the handlers exist. Out of scope for this PRD, but worth a follow-up ticket. The registry integrity test uses the UNION of both providers to remain provider-agnostic; as long as at least one provider exposes the tool schema, the registry is considered valid.

**Action:** file follow-up task to mirror these tools into `AiToolDefinitions.php`. Not blocking persona-split work.

---

## 2026-04-21 — Pre-existing test flakiness (not a regression)

Full unit-test run after Phase 5/6/7 commits reported two failures:
1. `Tests\Unit\Services\Risk\AutoRiskCalculatorTest` — `risk_profiles.risk_level` enum truncation on `medium_high` insert
2. `Tests\Unit\Agents\SavingsAgentGoalsTest > SavingsAgent goal recommendations > recommends increasing contribution…`

Both pass in isolation (ran each alone, 0 failures). Both fail in certain run orders. CSJTODO and AMENDMENTS §A-pre already call out "enum type-truncation carry" from `AutoRiskCalculatorTest` as a known issue. Not a regression from this PRD's changes — confirmed by stashing my WIP and re-running, which produces the same transient failure signature.

**Action:** not fixing in this PRD. Flag for separate test-isolation work. The persona-split work does NOT touch `risk_profiles`, `SavingsAgent`, or anything they depend on.

Re-verified after Phase 13: full suite reports 3 pre-existing flakes:
- `Tests\Unit\Services\Risk\AutoRiskCalculatorTest` (risk_profiles enum truncation)
- `Tests\Feature\InvestmentModuleTest > Risk Profile Management…` (same enum)
- `Tests\Feature\Estate\WillBuilderApiTest > pre-populate…` (faker middle_name non-determinism — pre-populate concatenates first_name + middle_name + surname, test fixture expects only first + surname, but User::factory() sometimes assigns a random middle_name that makes the JSON compare fail). Passes in isolation / specific orderings.

None of these are regressions — all three pass in isolation against my branch and they existed before the persona split.

**Final test posture:** 2,350 tests pass, 3 pre-existing flakes, 9,282 assertions executed. Flakes documented here; not fixing in this PRD.

---

## 2026-04-21 — Phase 4 bug: LPA `status` enum drift in tool schema

Second enum truncation in `handleUpdatePowerOfAttorney` when called with `status = 'revoked'`. Tool schema allowed `['draft', 'registered', 'revoked']` but migration only defines `['draft', 'completed', 'registered', 'uploaded']`. No `revoked` in the DB enum.

Dropped `'revoked'` from both tool schemas (AiToolDefinitions + XaiToolDefinitions) and from `Rule::in(...)` validation in both handlers. Kept only `draft` and `registered` — the two LPA-capture lifecycle values an AI tool sensibly sets. `completed` and `uploaded` are reserved for the Will Builder / document-upload flows and not exposed to Fyn.

Spec reference: amended spec §New tools required correctly listed `draft` and `registered`; the `revoked` value crept in from a plan task body without cross-check against the LastingPowerOfAttorney migration. Good example of why plan task bodies should never be authoritative over the amended spec.

---
