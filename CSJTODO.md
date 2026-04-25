# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 77 (Sprint 0 Tasks 0.12 + 0.13 + 0.14 complete)*
*Previous session: 25 April 2026 — session 76 (Sprint 0 Task 0.11 reliability bundle complete)*

---

## Session 77 (25 April late-afternoon) — Sprint 0 Tasks 0.12 + 0.13 + 0.14 complete

### Completed

#### Sprint 0 Task 0.12 — Hash-chain audit — **DONE** (`1d61a47`)

Replaces the file-channel `[AI-AUDIT]` `Log::channel('single')` line at `app/Agents/CoordinatingAgent.php:777` with an append-only, tamper-evident `ai_audit_events` table. Per INV-2.10.2 + INV-2.5.4.

- [x] **Migration** `database/migrations/2026_04_25_000013_create_ai_audit_events_table.php` per INV-2.10.2 schema (id, user_id, conversation_id nullable, tool_name, operation enum read|write|handoff|classify, status enum dispatched|persisted|failed|stripped, input_summary JSON, result_summary JSON, entity_type, entity_id, prev_hash CHAR(64), row_hash CHAR(64), signed_at, signature CHAR(64), created_at). Indexes on (user_id, created_at), (tool_name, status), and row_hash.
- [x] **Model** `app/Models/AiAuditEvent.php` — append-only, no `updated_at`, casts for JSON fields, FKs on user + conversation.
- [x] **Service** `app/Services/AI/AuditChainService.php` — `append/verifyChain/verifySignature` methods. `append` uses `DB::transaction` + `lockForUpdate` for serialised writes. SHA-256 row_hash over a 9-field payload (`prev_hash + JSON-serialised payload + signed_at toIso8601String`). HMAC-SHA256 signature on `config('app.ai_audit_hmac_key')` keyed value. `verifyChain()` walks via `cursor()` so memory is constant regardless of chain length, returns `chain_valid + tip_hash + row_count` on success or `chain_valid:false + broken_at + row_count` on first mismatch.
- [x] **Artisan command** `app/Console/Commands/AiAuditVerifyChainCommand.php` — `php artisan ai:audit:verify-chain` emits the canonical JSON, exit 0 on green / exit 1 on broken.
- [x] **Retention job** `app/Jobs/AiAuditRetentionJob.php` — weekly Sunday 04:00. 7-year window for `operation='write'` rows + `tool_name='get_recommendations'`, 2-year window for everything else. **Deletes (not pseudonymises) — class docblock explains why mutating in place would break the hash chain. Pseudonymisation belongs in a separate read-only export view if/when GDPR-style PII removal is needed for older rows.**
- [x] **Three append sites** in `CoordinatingAgent::executeTool` — dispatched at entry, persisted/failed at completion (decided by `error` key presence), failed in each catch — wrapped in `appendAuditEvent()` which swallows append failures into `Log::warning` so the chain is forensic, not load-bearing on the chat path.
- [x] **`executeTool` signature** gained optional `?int $conversationId = null`. `HasAiChat::handle` passes `$conversation->id`. `OnboardingChatDirector` parking-hydration call site (line 965) also passes it. The two gap-fill call sites at lines 1747 + 2148 leave it null because `$conversation` isn't in scope there — schema permits null, deferred (see suggestions).
- [x] **Operation classifier** — `create_*|update_*|capture_*|delete_record|set_expenditure` → `write`, `delegate_to_capture|capture_complete` → `handoff`, default → `read`. The `classify` operation is reserved for QueryClassifier audits in Sprint 1.
- [x] **Input/result summarisers** — `summariseInput` truncates strings >200 chars and replaces nested arrays with `[N items]` placeholders. `summariseResult` keeps the canonical outcome flags (`success/created/error/error_type/message/blocked/reason/action/requires_confirmation`) and drops large nested arrays.
- [x] **Schedule** `app/Console/Kernel.php` — weekly retention Sun 04:00, weekly verify-chain health-check Sun 04:30.
- [x] **HMAC config** `config/app.php` adds `ai_audit_hmac_key` falling back to `APP_KEY` when `AI_AUDIT_HMAC_KEY` env is unset (production must override). `.env.example` adds the env var.
- [x] **Admin endpoints** `app/Http/Controllers/Api/AiAuditController.php` adds `chain` (paginated read with user_id/status/operation filters) and `verifyChain` endpoints. `routes/api.php` adds `GET /api/admin/ai-audit/chain` + `/chain/verify` inside the existing `permission:admin.access` group.
- [x] **Admin UI** `resources/js/components/Admin/AiAudit.vue` — added a tab switcher and a chain-view tab with filters (operation, status, user_id), a paginated table showing tool / op / status / entity / hash / when, a status-badge column, and an integrity banner showing `Chain valid · N rows` or `Chain broken at row #N` with a Re-verify button. `aiAuditService.js` extended with `getChain` + `verifyChain` methods.
- [x] **Tests** `tests/Feature/Audit/{HashChain,HmacSigning,ChainTamperDetection,RetentionPseudonymisation}Test.php` — 14 cases (3 hash-chain growth, 2 HMAC signing + key rotation, 4 tamper-detection paths, 5 retention boundaries). All green.

#### Sprint 0 Task 0.13 — CoreIdentity guidance-only framing + FCA signposting — **DONE** (`05e7525`)

Per INV-2.10.1 + INV-2.3.3.

- [x] **`CoreIdentity.php` rewrite** — `<identity>` block reframes Fyn as a UK personal-finance guidance tool that helps the user understand their finances, explore options, and surface engine outputs. Explicitly states "You do NOT give personalised regulated financial advice". `<scope>` reworded to drop "personal financial planner" framing. Security / personality / response-format blocks kept (with British spelling / £ / signposting cue folded into personality). Docblock also rephrased to avoid the banned phrases (the architecture grep covers comments).
- [x] **FCA signposting layer** — `AdvicePromptBuilder::buildFcaSignpostingBlock(?array $classification)` is a new gated layer using `QuerySchemas::isAdviceType($primary)`. Fires for all 19 `ADVICE_TYPES`, skips `general` / `data_entry` / `navigation` / missing / unknown. The layer instructs the LLM to end its response with the exact sentence `"For regulated advice personal to your circumstances, speak to a qualified financial adviser."` on its own line, and explicitly NOT to include it on factual-only / out-of-remit / mid-paragraph turns.
- [x] **Architecture test** `tests/Architecture/CoreIdentityFramingTest.php` — 2 cases (banned-phrase absence + guidance framing presence). Uses `__DIR__`-relative path because `base_path()` isn't bootstrapped for the Architecture suite.
- [x] **Feature tests** `tests/Feature/Fyn/FcaSignpostingTest.php` — 5 cases (signposting on every advice type + absence on factual / bypass / missing / unknown classifications).

#### Sprint 0 Task 0.14 — Out-of-remit canonical refusal — **DONE** (`04a99fa`)

Per INV-2.3.4.

- [x] **`QuerySchemas::OUT_OF_REMIT` constant** — outside `ADVICE_TYPES` (so the S0.13 signposting suffix doesn't fire on refusals), `BYPASS_TYPES`, `FACTUAL_TYPES`. Empty MODULE_MAP / IMPLICIT_RELATED entries for consistency.
- [x] **`QueryClassifier` extension** — 4th detection step `detectOutOfRemitTopic` runs AFTER advice keyword matching but BEFORE route fallback. Patterns are narrow and labelled into 4 buckets — `Medical advice`, `Legal advice`, `Emotional support`, `General knowledge` — phrased to drop into the canonical refusal sentence directly. `detected_topic` is returned as a 4th key on the classification array.
- [x] **`AdviceFyn::handle` early-return** — converted to a true generator (`yield from` instead of `return`) so the early-return path can yield `content` + `done` directly. On `OUT_OF_REMIT`, persists user + assistant messages (because `chatWithPromptOverride` would normally do this and we're bypassing it), yields `['type' => 'content', 'text' => "I'm able to help you with your finances. {$detectedTopic} is out of scope."]`, yields `['type' => 'done']`, returns. Constructor gets a new `QueryClassifier` dependency.
- [x] **Tests** `tests/Feature/Fyn/OutOfRemitTest.php` — 8 cases (4 topic categories with exact refusal-string + zero tool_use events + persisted user+assistant messages + financial-keyword override "I'm depressed about my pension pot — am I on track for retirement?" still routes to `retirement_readiness` not OUT_OF_REMIT + isolated classifier assertion).

#### Test results (cumulative session-77)

- AI / Fyn / Onboarding / Audit / Architecture / Unit-Constants / Unit-Services-AI sweep: **495 / 495 passing (1943 assertions, 0 failures)**.
- Note: focused sweep, not the full Pest suite. Full-suite verification deferred to Sprint 0 verification rollup (S0.17).
- One new table live and migrated: `ai_audit_events`.
- One new artisan command: `php artisan ai:audit:verify-chain`.
- Two new scheduled jobs: `AiAuditRetentionJob` Sunday 04:00 + `ai:audit:verify-chain` Sunday 04:30.

#### Plan + spec status (Sprint 0)

`April/April24Updates/plan/10-sprint-0-plan.md` ticked through S0.14 with delivery notes per task. `April/April24Updates/spec/10-sprint-0-plan.md` reference-only, never edited per project convention.

```
✓ S0.1  Rebase onto main
✓ S0.2  Delete OpenAI sidecar
✓ S0.3  Two-Fyn collapse
✓ S0.4  Remove visible-handoff UI
✓ S0.5  17 fill_form → direct-write (a-q)
✓ S0.6  Billing tools (3, parity 40/40)
✓ S0.7  update_record allowlist + strict schema
✓ S0.8  delete_record two-phase confirmation
✓ S0.9  Consent runtime check
✓ S0.10 User-content sanitisation + structural separation
✓ S0.11 Reliability bundle (6 sub-steps)
✓ S0.12 Hash-chain audit                            ← session 77
✓ S0.13 CoreIdentity rewrite + FCA signposting      ← session 77
✓ S0.14 Out-of-remit canonical refusal              ← session 77
☐ S0.15 Coverage-gap tests (7 invariants)
☐ S0.16 Browser harness + 20 Playwright scenarios
☐ S0.17 Verification rollup + Rubric-A re-score
```

### NOT Done — Outstanding for next session

- [ ] **Sprint 0 Task 0.15 — Coverage-gap tests for 7 small invariants** ← start here next session.
  - INV-2.2.4 (resume after disconnect)
  - INV-2.2.5 (entry-source journey map) + tiny `config/onboarding.php` + `AiChatController::startOnboarding` edits
  - INV-2.2.6 (parked-facts flush)
  - INV-2.4.3 (capture_complete styling matches normal content bubble)
  - INV-2.6.1 (read completeness — list_records returns full count)
  - INV-2.6.2 (get_recommendations completeness)
  - INV-2.7.4 (preview-mode tool catalogue parity)
  - 7 single tests + 1 architecture test + 2 tiny code edits. Should be a fast session if existing test patterns hold.

### Context for Next Session

Sprint 0 compliance + audit floor is in. Next is the coverage-gap tests (S0.15) which closes out the Sprint-0 invariant test surface, then the browser harness + 20 Playwright scenarios (S0.16), then the verification rollup (S0.17). After Sprint 0 closes, Sprint 1 opens with the eval harness + memory model + `<known_facts>`. Work continues on `feature/fyn-persona-split` — do NOT merge to `dev` until S0.17 has Rubric-A re-scored 13–15/40.

---

## Outstanding — Tech Debt (deferred from session 77 audit)

From session-77 tech-debt-report.md (0 critical, 1 warning, 3 suggestions):

- [ ] **W1 — `summariseInput` records tool input verbatim on the chain.** PII (DOB, email, postcode) lands in `ai_audit_events.input_summary` for tools like `capture_personal_details` / `update_profile` / `update_record`. Strings >200 chars are truncated but anything below that lands raw. Admin-only access bounds the exposure but redaction belongs in Sprint 1's prompt-injection / sanitisation theme. Add a per-tool field redaction list inside `summariseInput` BEFORE the value reaches the chain row (e.g. `capture_personal_details.date_of_birth → 'YYYY-MM-DD'` masked, `update_profile.email → hash(email)`, `*.postcode → first 3 chars only`). **Critical to do BEFORE the chain accumulates real production data — once written, redacting later breaks every subsequent hash.**
- [ ] **S1 — `appendAuditEvent` swallows all `Throwable`.** Intentional (chain is forensic, not load-bearing on the chat path) but the catch-all hides a real bug if `AuditChainService::append` ever starts failing. Add an alert path: increment a `cache()` counter or fire a `LogAlertEvent` so the weekly `ai:audit:verify-chain` health check can surface append-failure counts in its JSON output. Sprint 1.
- [ ] **S2 — Onboarding gap-fill call sites pass `conversation_id = null`.** `OnboardingChatDirector` lines 1747 (`emitGapFillToolCalls`) and 2148 (inline-capture gap-fill) don't have `$conversation` in scope, so they pass null implicitly when calling `executeTool`. Result: 10-30% of audit rows for onboarding-stage users land with `conversation_id = NULL`, which weakens the admin chain-view "by conversation" navigation for those rows. Thread `AiConversation $conversation` through both methods. Small mechanical change. Sprint 1.
- [ ] **S3 — Pre-existing: `border-3` in `AiAudit.vue` spinners.** Tailwind's default config doesn't expose `border-3` — only `border-1`, `border-2`, `border-4`, `border-8`. The class is a no-op so the spinner ring renders with default 1-pixel border instead of the intended thicker ring. Design system rule #12 specifies `border-4`. The original file had this pre-S0.12; my new chain-tab spinner copied the pattern. Replace both occurrences with `border-4`. One-line cleanup commit; not blocking.

## Outstanding — Tech Debt carried from earlier sessions

From session 76 tech-debt-report.md:

- [ ] **S1 (session 76)** — Duplicated provider-resolution lookup in `AdminController::getAiProvider` reproduces the conditional from `HasAiGuardrails::getAiProviderForLoop`. Extract to a `ProviderResolver::current()` static helper. Defer to Sprint 1 (not S0.13 as originally planned — S0.13 was a CoreIdentity rewrite, didn't touch AdminController).
- [ ] **S2 (session 76)** — `AssetCaptureEntityExtractor` now 828 lines (+162 from S0.11.5). Three concerns sharing a file: extraction, identity-key normalisation, DB dedup. Split when Sprint 1's eval harness opens it.
- [ ] **S3 (session 76)** — `$fromLlm` parameter on the four `*IdentityKey` private methods is dead (pre-existing, not introduced by S0.11.5). Drop in the same split as S2.
- [ ] **S4 (session 76)** — `ai:usage:backfill` artisan command silently `updateOrCreate`s every aggregated row on rerun — no skipped/wrote tally. Add `--dry-run` + counters when next touched.

From session 75 tech-debt-report.md (still carried):

- [ ] **S1 (session 75)** — Mid-stream consent re-check fires one DB query per SSE event. Indexed, sub-ms, but on a long stream this is dozens of redundant queries for the unchanged answer. Throttle (e.g. re-check every Nth event or once every 5s) when next touched.
- [ ] **S2 (session 75)** — Duplicated "grant ai_chat consent" helper across 4 test files. Extract to a test trait when 5+ callers exist.

## Known Issues

- None new from session 77.

## Deploy Status

- `feature/fyn-persona-split` was 3 commits ahead of origin at start of session 77; pushed at session-end (origin/feature/fyn-persona-split now matches local).
- **Not yet on `dev`.** Per memory `feedback_main_via_dev_only.md`, work flows `feature/fyn-persona-split → dev → main`. Open PR feature/fyn-persona-split → dev only AFTER Sprint 0 closes (S0.15 + S0.16 + S0.17 complete with browser matrix + Rubric-A re-score).
- Deploy guide for session 77 at `April/April25Updates/deploy.md` covers S0.12 + S0.13 + S0.14 — 16 PHP files + 1 migration + frontend rebuild + routes/api.php + config/app.php. Sets `AI_AUDIT_HMAC_KEY` env (production must override `APP_KEY` fallback). Smoke includes `php artisan ai:audit:verify-chain`.

---

*Generated by `/session-end` skill — 25 April 2026, session 77.*
