# Fyn wiring Batch B — one ranking (F2, F12)

Date: 2026-09-09. Branch `fix/f2-f12-one-ranking` off dev `b0deac8da`. Artifact: https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88 (F2, F12; §08 callout). Source HTML: `September/September8Updates/fyn-wiring-artifact.html`.

## CSJ decision (2026-09-09)

The seeded priority wins. The ranker reads `priority`, `impact` and `estimated_impact` off each recommendation; the aggregator consumes the ranker's output instead of its own 85 / 60 / 45 map.

## What exists today (three homes, none agreeing)

| Home | Rule | Consumers |
|---|---|---|
| `PriorityRanker::rankRecommendations` | 0.4 urgency + 0.3 impact + 0.2 ease + 0.1 module weight, where urgency/impact read `adequacy_score`, `emergency_fund_months`, `iht_liability`… — keys no engine emits, so every rec lands on the fallback branch | Fyn `<financial_context>`, `get_recommendations`, holistic plan, cashflow demands |
| `RecommendationsAggregatorService` | label → 85 / 60 / 45 (composed and tax blocks); int → `90 − 5p` (raw blocks); category → 90 / 60 (raw savings) | `/api/recommendations`, dashboard "Where to focus", `/m` next actions |
| `TaxOptimisationAgent::mapPriorityToUrgency` | label → 80 / 60 / 40 | pre-sets `urgency_score` the ranker then ignores |

Plus a fourth: the five plan-source adapters each resolve the label their own way, and three of them (savings, retirement, investment) turn `impact: 'Critical'` into `medium` because `StrategyPriority` has no critical case. Fifteen seeded critical rows rank below high on the dashboard.

Module weights are also two tables: `CoordinatingAgent::getUserContext` (80/75/70/65/60/55/50) and the ranker's own defaults (70/75/65/60/50/55).

## The one rule

`PriorityRanker` is the home.

- `priorityLabel(rec)` → `critical | high | medium | low`. A string `priority` label wins; else the `impact` label (the DB engines put the seeded label there and an int in `priority`); else an int `priority` (1–2 high, 3 medium, 4+ low, the rule the protection adapter already uses); else medium.
- `urgency_score` = label band: critical 95, high 85, medium 60, low 45. (The dashboard's existing 85 / 60 / 45 bands, with critical above high.)
- `impact_score` = the numeric benefit on the rec (`estimated_impact`, `estimated_saving`, `estimated_annual_tax_saved`, `potential_benefit`, first present) banded 95 / 80 / 65 / 55 / 50; 50 when none.
- `priority_score` = urgency + impact / 20 + module weight / 100. Tiebreakers can add at most 5.8, so a seeded band is never crossed. Sort descending.
- `timeline` from urgency (≥ 80 immediate, ≥ 60 short_term, ≥ 40 medium_term); UI `impact` label from the seeded label (critical/high → high, medium, low).
- Module weights: one `MODULE_WEIGHTS` table on the ranker (the CoordinatingAgent values, which include tax_optimisation). `getUserContext` goes.
- `ease_score` and `user_priority_score` are deleted (nothing read them; the ease table was per-module guesswork).

Consumers:
- `RecommendationsAggregatorService` collects raw recs per module carrying the engine's own `priority`/`impact`, hands the grouped list to the ranker once, then shapes the output (`formatRecommendations`) from the ranker's fields. Its own bands, int maps, `determineTimeline` and `determineImpact` go.
- The five adapters call `PriorityRanker::priorityLabel`, map critical → high for the enum, and carry the original label in `extra['seeded_priority']` so the composed items keep it; the aggregator reads `seeded_priority ?? priority`.
- `TaxOptimisationAgent` stops pre-setting `urgency_score`.
- `CoordinatingAgent` passes `[]` as context; the prompt's "(urgency: N/100)" line is unchanged (F10 is a separate note).

## Tests

- New `tests/Unit/Services/Coordination/PriorityRankerTest.php`: label resolution for every engine shape (string priority, impact label + int priority, int only, none); band ordering; tiebreakers never cross a band; the aggregator and the ranker put the same rec in the same band.
- Updated: `HolisticPlanRefactorTest` goals scoring (category no longer sets urgency; seeded priority does), `RecommendationsAggregatorServiceTest` numbers (raw path), `CrossModuleIntegrationTest` key list, adapter tests (critical).
- Families to run: `tests/Unit/Services/Coordination`, `tests/Feature/Services/RecommendationsAggregator*`, `tests/Feature/Api/RecommendationsControllerTest.php`, `tests/Unit/Services/Mobile/NextActionsServiceTest.php`, `tests/Feature/Mobile/MobileDashboardNextActionsTest.php`, `tests/Feature/AI/GetRecommendationsCompletenessTest.php`, `tests/Feature/Fyn/ModuleScopedFinancialContextTest.php`, `tests/Integration/CrossModuleIntegrationTest.php`.

## Acceptance (live, web and /m)

For one persona: the order of "Where to focus" on the web dashboard, `/api/recommendations/top`, the `/m` dashboard next actions, and the "Top ranked recommendations" list in Fyn's `<financial_context>` (admin AI audit `assembled_context`) agree on band order, and a critical savings row sits above every high row on all four.

## Out of scope

- Adding `critical` to `StrategyPriority` (touches seeded strategy rows and the SaveTax dashboard badges).
- F10: removing "(urgency: N/100)" from the prompt.
- F18: status filtering against `recommendation_tracking`.
