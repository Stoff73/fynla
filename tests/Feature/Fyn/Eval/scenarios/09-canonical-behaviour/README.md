# 09 — Canonical §0 behaviour

10 scenarios exercising the canonical two-Fyn contract end-to-end. Any regression in this category blocks merge.

| ID | Scenario | Exercises |
|----|----------|-----------|
| 09-01 | `path-choice-to-done` | Full onboarding flow path_choice → all base states → asset_capture → done; every write persists. |
| 09-02 | `resume-after-disconnect` | Pause mid-state; reconnect >5min later; Fyn greets with `resume_summary` + Yes/No bubble; user says Yes; state machine resumes. |
| 09-03 | `memory-no-repeat-ask` | User profile seeded with `marital_status`, `first_name`, `date_of_birth`; Fyn never re-asks any. |
| 09-04 | `advice-factual-net-worth` | "What's my net worth?" → Advice Fyn calls `NetWorthService`, bypasses engine, structured factual response; no `orchestrateAnalysis`. |
| 09-05 | `advice-recommendation-route` | "Should I contribute more to my ISA?" → Advice Fyn calls `orchestrateAnalysis`, projects into `advice_response` SSE event with deep link. |
| 09-06 | `advice-invoice-subscription` | "Where's my invoice?" → Advice Fyn calls `get_subscription_status` + `list_invoices`, emits navigation, confirms subscription. |
| 09-07 | `advice-handoff-invisible-capture` | Missing-data question; `DataReadinessService` flags; Advice Fyn emits `delegate_to_capture`; Onboarding captures; control returns; original query answered. **Zero `persona_state_change`, zero `quick_replies`, zero capturing-pill renders during the handoff.** |
| 09-08 | `advice-read-only-tool-list` | Integrity test: `AdviceFyn::buildToolList()` returns a set with zero DB-mutating tools (no `create_*`, no `update_*`, no `delete_*`, no `set_expenditure`, no `capture_*`). |
| 09-09 | `index-populated-on-close` | Close a conversation with known topics/entities; assert `ai_conversations` index row has non-empty `summary`, `topics`, `entities_mentioned`, `intents_stated`. |
| 09-10 | `cross-conversation-surface` | Pension drawdown preference stated in conversation A; new conversation B asks about retirement planning; assert Advice Fyn queries `search_conversation_index`, finds conversation A, references the prior preference without re-asking. |

Source: `fyn-rubrics.md §B` coverage table — "Canonical §0 behaviour (v2 new)" + scenario-by-scenario breakdown.
