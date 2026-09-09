# Fyn wiring Batch B — evidence (F2, F12: one ranking)

Branch `fix/f2-f12-one-ranking`, verified locally on 9 September 2026 against the main checkout's `./dev.sh` server (`127.0.0.1:8000`, `/m` from the built `public/m-build`). Plan: `fyn-wiring-batch-b-plan.md`. Persona: young family (`preview_young_family@fynla.local`, user 63, James Carter), entered through the landing-page demo chooser; `/m` token minted through `POST /api/preview/login/young_family` and confirmed with `GET /api/auth/user` (user 63).

## The rule, in one place

`PriorityRanker::priorityLabel` → band (critical 95, high 85, medium 60, low 45); a benefit figure and the module weight only break ties inside a band (at most +5.55). Consumed by `CoordinatingAgent` (Fyn) and `RecommendationsAggregatorService` (web API, dashboard, `/m`). Deleted: the aggregator's 85/60/45 map and int maps, `CoordinatingAgent::getUserContext` (second weight table), `TaxOptimisationAgent::mapPriorityToUrgency`, the five adapters' private label resolvers, the ranker's ease and user-priority scores. Also fixed on the way: three adapters turned `impact: 'Critical'` into `medium` because `StrategyPriority` has no critical case (the seeded label now travels as `extra['seeded_priority']`); `/m` next actions sorted by the pound benefit when one existed, so a £1,000 tax item sat above a critical estate item.

## Same order on every surface (user 63)

| Surface | Top of list |
|---|---|
| `GET /api/recommendations/top?limit=8` (fetched from the dashboard tab) | estate 98 No Will in Place · protection 88.3 Add income protection · savings 89/89/88.25… Build cash reserve for Replace Family Car, Increase Your Emergency Fund, 'Emergency Fund' Needs Increased Contributions, Consider a Cash ISA |
| Web dashboard, "Where to focus" → Savings tab (clicked) | Build cash reserve for Replace Family Car · Increase Your Emergency Fund · 'Emergency Fund' Needs Increased Contributions · Consider a Cash ISA |
| `/m/app/dashboard` "Top actions" (390 × 844) | No Will in Place · Build cash reserve for Replace Family Car · Increase Your Emergency Fund · Add income protection insurance |
| Fyn, live turn on the `/m` dashboard chat, "How is my emergency fund looking?" → `ai_messages` 439 (conversation 151) `assembled_context` | "Top ranked recommendations": 1 Build cash reserve for Replace Family Car (urgency 85) · 2 Increase Your Emergency Fund · 3 'Emergency Fund' Needs Increased Contributions · 4 Consider a Cash ISA, each with its `Triggered by:` line |
| `CoordinatingAgent::orchestrateAnalysis(63)['ranked_recommendations']` (tinker) | savings 89 Build cash reserve · savings 89 Increase Your Emergency Fund · protection 88.3 · savings 88.25 … · tax_optimisation 88.15 |

Before the change, the web dashboard listed the savings items in the aggregator's insertion order at a flat 85, Fyn's list carried urgency 50 for everything, and `/m` put the two tax items (£1,440 and £1,000 benefits) above the critical will.

Fyn's reply (message 439) cites £11,700, 2.68 months, the 6-month target, the £14,493 shortfall and the £400 → £5,500 goal contribution gap; no score, "/100" or "out of 100" in the reply.

## Tests

New `tests/Unit/Services/Coordination/PriorityRankerTest.php` (9). Updated for the one rule: `HolisticPlanRefactorTest` goals scoring, `RecommendationsAggregatorServiceTest` raw-path bands, `CrossModuleIntegrationTest` key list, `ProtectionRecommendationAdapterTest` (label wins over int). Consolidated pass at the end of the branch (coordination, integration, aggregator, recommendations API, holistic, mobile, Fyn context, client contract families): 478 passed, 2,067 assertions.

## Noticed, not fixed

- `AdvicePromptBuilder.php:738` warns "Undefined array key months_until" while building the life-events block for this persona (pre-existing).
- `orchestrateAnalysis` emits a PHP deprecation from `BelongsTo.php:187` ("Using null as an array offset") for this persona (pre-existing).
- `/m` dashboard logs a 403 from `POST /api/ai-chat/onboarding/start` for a preview persona (pre-existing; preview writes are intercepted).
- Fyn's reply called the emergency-fund shortfall "a medium-priority concern" while the seeded row is `high`; the wording comes from the module analysis category ("Fair"), the Rule 12 residue CSJ said to leave for now (F22).
- The `(urgency: N/100)` line in the prompt is unchanged (F10).
