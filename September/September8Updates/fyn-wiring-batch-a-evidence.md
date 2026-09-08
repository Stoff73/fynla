# Fyn Wiring Batch A — Task 8 evidence

Branch `fix/f0-savings-engine-dispatch`, worktree `/Users/CSJ/Desktop/fynla-f0`, verified locally on 8 September 2026 against the worktree server on `127.0.0.1:8001` (web bundle built with `VITE_BASE_PATH=/build/`, `/m` bundle built with `vite.mobile.config.js`). Plan: `fyn-wiring-batch-a-plan.md`. Artifact: https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88 (findings F0, F3, F15, F16, F17 marked fixed; F20 to F26 added).

Personas: young family (`preview_young_family@fynla.local`, user 63, James Carter) through the landing-page demo selector; peak earners (user 65, David Mitchell) for the fresh Fyn conversation; a real onboarded test account `f0-runway-test@example.com` (user 72, runway 1.5 months, no emergency fund goal) for the goal write, created in the local dev database for this check.

## Step 2 · reseed

`php artisan db:seed --class=SavingsActionDefinitionSeeder --force` run after the title-template fix. Young family holds four savings accounts (two at 0 %, one at 4.55 %, a current account); no maturity needed, the rate rules fire on the seeded data.

## Step 3 · web (Playwright, clicked through)

1. **Strategy tab** (`/savings`, tab "Strategy"): nineteen recommendations render, one card each, from `GET /savings/recommendations`. Opening "Show how we worked this out" on "Increase Your Emergency Fund" shows the trace: employment table "Employed = 6 months, self-employed/contractor = 9 months, retired = 3 months"; runway "Total emergency savings £11,700 ÷ £4,366 monthly expenditure = 2.7 months, against a 6-month target"; shortfall "£14,493 (3.3 months × £4,366/month)". Rates read as percentages (4.55 %, 4.89 %). Screenshot `evidence/f0-web-strategy-tab-trace.png`.
   - Before the fix the tab said "No recommendations": `SavingsRecommendations.vue` read a Vuex slot nothing wrote (F25). The savings plan endpoint is not routable (`routes/api.php:1077`), so the tab uses the recommendations endpoint every other consumer uses.
2. **Dashboard card** ("Where to focus", Savings tab): four items, same wording as the Strategy tab (Build cash reserve for Replace Family Car; 'Emergency Fund' Needs Increased Contributions; Increase Your Emergency Fund; Consider a Cash ISA). Screenshot `evidence/f0-web-dashboard-savings-card.png`.
3. **Fyn, emergency fund question.** Preview users have no web chat by design, so this ran on the `/m` dashboard chat as the persona, then on a fresh conversation as peak earners.
   - Young family, `ai_messages` 425 (conversation 147): `assembled_context` contains "Top ranked recommendations (from decision engine)" with "Triggered by: emergency_fund_low" and "Triggered by: emergency_fund_no_designated"; "Total savings: £11,700.00"; "Emergency fund: 2.68 months from cash savings". Tool results carry no `adequacy`. `list_records(savings_account)` returned four records.
   - The first attempt (`ai_messages` 423) exposed three faults, all fixed on the branch: the module-scoped path put no savings figures and no recommendations in the context (F21); the tool result carried `adequacy: 44.67`, which Fyn voiced as "44.67 out of 100" (F22); `list_records` failed on a lazy load of `jointOwner` (F23). The repeat turn in the same conversation echoed the old score from its own transcript, so the check was redone on a fresh conversation.
   - Peak earners, fresh conversation 148, `ai_messages` 427: context carries "Triggered by: emergency_fund_excess" and "emergency_fund_no_designated", "Emergency fund: 12.95 months"; reply cites 12.95 months and the £20,000 excess flagged by the engine; no "score", "/100" or "out of 100" in the reply. Screenshot `evidence/f0-m-fyn-emergency-fund-fresh-conversation.png`.
4. **Fyn, goal proposal and write** (web docked chat, user 72): "Should I create a goal for my emergency fund?" → Fyn proposes a £12,000 goal and asks "Would you like me to help you set up an emergency fund goal now?" → "Yes please, set it up." → log `[AdviceFyn] Deterministic write-intent routed {entity_type: goal, matched_verb: proposal_accepted}` → `handleInlineCapture` → `create_goal` → goal 165 (`target_amount` 12000, `target_date` 2027-03-31, `monthly_contribution` 500) → "Saved to your records: Emergency Fund — Goal" card (`ai_messages` 435, conversation 150, `capture_ack`). Screenshot `evidence/f0-web-fyn-goal-proposal-accepted-created.png`.
   - The first attempt (conversation 149, `ai_messages` 431) failed: the model wrote `delegate_to_capture` out as prose and nothing was written (F24). `WriteIntentClassifier::proposalAcceptanceIntent` now routes the acceptance deterministically.

## Step 4 · `/m` (Playwright, 390 × 844)

- `/m/app/savings` as the young family persona (token minted through `POST /api/preview/login/young_family`, account confirmed with `GET /api/auth/user` → user 63): "Recommended actions" card lists the same items as the web Strategy tab from the same endpoint. Screenshot `evidence/f0-m-savings-recommended-actions.png`.
- `/m/app/dashboard`, Savings focus area: the same four items as the web dashboard. Screenshot `evidence/f0-m-dashboard-savings-actions.png`.

## Step 1 · tests

Targeted families run green after every fix: `tests/Unit/Services/Savings` (60), `SavingsAgentTest` (11), `SavingsPlanServiceTest` (1, new), `ActionDefinitionDispatchCoverageTest`, `ModulePathFinancialContextTest` (2, new), `ListRecordsJointOwnerTest` (1, new), `AdviceQuestionScopeTest`, `ModuleScopedFinancialContextTest`, `SavingsEmergencyFundPayloadTest`, `CoordinatingAgentJointOwnerTest`, `WriteIntentClassifierTest` (28), Vitest `SavingsRecommendations.spec.js` (4, new) and `/m` `SavingsRecommendations.spec.js` (3, new).

Full suite (worktree, `./vendor/bin/pest --compact`, 2,072 s): 740 failed, 27 skipped, 7,731 passed. 662 of the failures were `Base table or view not found: laravel_testing.tax_configurations / tier_configurations` plus one `Table definition has changed` and one deadlock: the targeted Pest runs made while the suite was in flight ran `RefreshDatabase` against the same testing database. Treated as contention, not as red. The 23 files that reported failures were rerun alone with nothing else on the database: **322 passed, 1 failed** (327 s). The one real failure, `SavingsReadConsumerParityTest` line 2019, asserted the pre-Batch-A spouse-ISA semantics (fire whenever combined headroom ≥ £5,000); the evaluator now implements the seeded `spouse_isa_allowance_imbalanced` condition, and the test fixture was aligned (user's allowance fully used, spouse with £16,000 room). `AdviceFynProposalAcceptanceTest` ran green in the same rerun.

## Not done here

- F20 (market-rate year) awaits CSJ's decision. F26 notes are reported, not fixed.
