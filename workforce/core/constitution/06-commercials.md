# 06 — Commercials

**Status:** Written from implemented code, 2026-08-13, session 7.
**Owner:** Brett (business and financial — `registry/people.md` §3.2). Amendments gated.

**None of this is elicited or proposed. All of it is read from the running system.**
`database/seeders/TierConfigurationSeeder.php` states it directly: *"Prices and
limits are the approved Free and Premium commercial contract."*

---

## 1. Canonical source

| Concern | Canonical |
|---|---|
| Prices, capabilities, caps, AI budgets | **`TierConfigurationSeeder.php`** → `tier_configurations` table |
| Which tier a user is on | `TierResolver` + `PremiumEntitlementResolver` |
| Enforcement | `DbTierGate`, `TeaserGate` |
| Apple products | `config/apple_store.php` |
| AI model degrade behaviour | `config/services.php` `degrade_chat_model` |

**Never restate a price or a limit in prose.** They change; the trunk points.

## 2. The commercial contract

Two tiers. **More than one paid tier is a hard no** (`03-hard-nos.md` §2).

| | **Free** | **Premium** |
|---|---|---|
| Monthly | **£0** | **£6.99** |
| Annual | £0 | **£59.99** |
| Currency display | GBP only | User choice |
| Snapshot window | 90 days | Unlimited |
| Open API affordance | No | Yes |
| Document storage | — | 1 GB |

### Capability verbs

`full` · `limited` · `teaser` · `none`. **`teaser` is a first-class state** — the
free tier's Estate module is `teaser`, meaning visible and inviting rather than
hidden. That is V1 (access) expressed in the product: you can see what you're
missing.

**Free — full access:** dashboard, protection, income, liabilities, expenditure,
tax strategy, risk profile, future-value projections, chattels, child benefits,
family module.

**Free — limited (count-capped):** savings 2 · investments 2 · pensions 2 ·
property 1 · mortgages 10 · goals 2 · life events 1.

**Free — teaser:** estate.

**Free — none:** detailed expenditure · joint household view · letter to spouse ·
exotic investments · buy-to-let analysis · retirement decumulation · what-if ·
holistic plan · document upload · statement upload · adviser export · investment
cost analysis.

**Premium:** every capability `full`, every count cap `null` (unlimited).

## 3. AI cost control — already solved, and where

The free tier's AI cost is **capped and enforced in the tier configuration**, not
managed by policy:

| | Free | Premium |
|---|---|---|
| `fyn_weekly_token_budget` | **100,000** | 500,000 |
| `fyn_daily_hard_backstop` | **500,000** | 2,000,000 |

**Behaviour at the ceiling is soft-degrade, not cut-off.** `config/services.php:44`:
a cheaper model takes over when the rolling weekly budget is exceeded
(`degrade_chat_model`, `HasAiGuardrails`). The comment notes it currently defaults
to the standard model, *"so chat stays OPEN"* — degradation is available and
deliberately not yet armed.

**This is V1 and V4 built into the cost model.** A free user who exhausts their
budget gets a cheaper answer, not a locked door and not a sales pitch.

**Consequence for the metric set:** the "Fyn AI cost < 12% of ARR" guardrail is a
*reporting* measure, not the control. The control is per-user token budgets. Free
users cannot run away with cost, because the ceiling is per user and enforced.

## 4. Metrics

**North star: Paid Active Households** — ≥1 paid subscription, logged in within 30
days, ≥3 modules populated. Household, not user, because the couple/family view is
the product's asset.

**Health guardrails** (`04-product-strategy.md:91–99`): monthly churn <3% · CAC
payback <6 months · net revenue retention ≥110% at month 12 · support tickets <8
per 100 active users per month · Fyn AI cost <12% of ARR · crash-free ≥99.5% ·
seed-drift incidents 0.

**Inputs:** weekly new paid signups · channel mix · 14-day activation (≥3 modules) ·
spouse-add rate within 60 days · referral rate in first 90 days.

## 5. Legacy plans — machinery with no subjects

**Ratified CSJ, 2026-08-13: there are no users to grandfather. Founder accounts are
admin, not subscriptions.**

So `SubscriptionPlanSeeder`'s four historical plans — student, standard, family,
pro — `TierResolver::LEGACY_PAID_PLANS`, `isGrandfatheredLegacyPaid()`, and the
cutover machinery (`TierCollapseLock`, `TierCollapsePreflight`,
`RevolutTierVariationSync`) currently **protect nobody**.

**Two tiers is the whole commercial model, with no exceptions behind it.** Any
future reference to a legacy or grandfathered plan is describing dead machinery,
not a live commitment — and no agent may treat a legacy plan as a constraint on
product decisions.

**Flagged to the Quality lead as a removal candidate — not asserted as dead code.**
`00-precedence.md` §2.5 applies: removal is riskier than addition, and a proposal
must state what breaks if the thing is gone. `TierCollapseLock` in particular guards
payment processing during identity cutover; whether that cutover is complete is a
question for the code, not for this file. Logged on the capability map.

## 6. Spend and sequencing

**Spend authority: £0.** Every spend is a gate, including free tiers requiring a
card and anything that renews (`charter.md` §5).

**Go-to-market sequencing: Azlan's** (`01-mission.md` §5). Not defined here.

## 7. Design specs

`docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md` ·
`2026-05-29-pure-freemium-signup-design.md` ·
`plans/2026-05-29-freemium-nav-and-limit-ux.md` ·
`plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md`

## 8. Open

**None. Session 7 closed 2026-08-13.**
