# Brett test batch — 2026-09-22

Source: `brettTest/commentsBrett.md` (items 2–12; item 1 withdrawn by CSJ). Decisions by CSJ, 2026-09-22 17:50 BST. Every item is `/m` first (Brett tested on a phone) and web where a counterpart exists (Rule 19).

## PR A — bugs and copy (branch `fix/brett-batch-a`)

| # | Change | Where | Check |
|---|---|---|---|
| 2 | Split `BUBBLE_BREAK` once, before the turn-type branch, so a form turn never carries `\x1E` | `OnboardingChatDirector` (form branch at ~1132 saves/streams the raw prompt; only the free-text branch at ~1273 splits) | Golden masters; the SaveTax opener on `/m` shows two bubbles and no glyph, also on transcript reload |
| 12 | "See all your actions" goes to the ranked list | `TaxStrategy.vue:230` → `{ name: 'm-actions' }` | Tap lands on `/m/actions` |
| 3 | Job title optional | `CaptureForms::work()` `occupation.required = false` | Form saves with title blank; income screen shows employer · amount |
| 5 | Risk profile card hidden during onboarding verify; wording "any you don't agree with" | `Investment.vue` risk card `v-if` on the verify predicate (`onboarding_fyn_step` starts `campaign_verify_`, as `MobileChrome::showVerifyActions`) | Card absent on the verify visit, present after onboarding |
| 7 | Projected-vs-target hero hidden during onboarding verify (ASSUMPTION — see open questions) | `Retirement.vue` hero (line 14) same predicate | Same |
| 10 | Expenditure label says household | `CaptureForms::expenditure()` label/hint | Form text |
| 8a | Spouse dividend-holdings hint "Leave blank if none or unknown" | `CaptureForms::spouseAssets()` | Form text |

## PR B — property equity on `/m` (branch `feat/m-property-equity`)

| 6 | Equity line per property row (share of value minus share of mortgage) | `NetWorthCategory.vue` items (value at 186, mortgage line at 54) | Web `PropertyCard.vue` already shows Equity (line 53), so web is done |

## PR C — Fyn capture changes (branch `feat/brett-fyn-forms`)

| 4 | Drop the "tap Okay" gate: `enterCampaignVerify` returns `campaign_verify_navigate` directly | `OnboardingStateMachine::enterCampaignVerify` (~1226); `campaign_verify_announce` becomes orphaned like `campaign_verify_more` | `CampaignSectionFlowTest`, `JourneyVerifyFlowTest`, golden masters; `/m` walk: save → chat minimises → section screen with Continue/Edit |
| 8b | Main user: "Dividends you receive from it each year" on the GIA kind of the investment form | `CaptureForms::investment()`; `create_investment_account` already accepts `annual_dividend_income` (agent ~3296) — schema description in `AiToolDefinitions` | Value lands on the account and the user total |
| 8c | Spouse: "Dividends they receive each year" on the non-working assets investments kind → `spouse_annual_dividends` | `CaptureForms::spouseAssets()`, `handleCaptureSpouseNonWorkingAssets` allow-list (~5780), tool schema | Household input row carries it; tax plan reads it |
| 9 | Non-working spouse pension: a choice "They pay in the non-earner maximum (£X a year)" that writes `spouse_pension_input_annual` = relevant_earnings_minimum × (1 − basic rate) from `TaxConfigService` | `CaptureForms::spouseAssets()` pension kind, same handler allow-list, tool schema | Never a literal 2880 |

## Open questions (CSJ)

- **11 (expenditure step).** The SaveTax expenditure form is the only place onboarding captures childcare, charitable donations and Gift Aid — the free-tier tax inputs shipped yesterday (#921/#922). Dropping the step drops those. Options: (a) drop the whole step; (b) keep the step but ask only the three tax fields, no monthly total. Recommend (b).
- **7 (retirement projection).** Implemented as "hidden during the onboarding verify visit" to match item 5. If it should stay hidden on the `/m` Retirement screen always and only appear in pension detail, say so.

## Order

A → B → C, one PR each to dev, csjones walk of each on `/m` before merge (Brett's path), web where a counterpart exists.
