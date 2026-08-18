# Session 7 — Commercials

**Onboarding session 7 of 9.** Produces `core/constitution/06-commercials.md`.
**Date:** 2026-08-13 · **Interviewer:** Chief of Staff · **For:** CSJ
**Domain note:** commercials are **Brett's** (`registry/people.md` §3.2). CSJ is
answering as the only active founder; expect divergences when Brett is interviewed.

---

## What I read first

`TierResolver.php`, `PremiumEntitlementResolver.php`, `SubscriptionPlanSeeder.php`,
`config/apple_store.php` · `04-product-strategy.md` §6 metrics in full ·
`03-hard-nos.md` §2 as verified · `charter.md` §5 spend

**Verified in code:** one paid tier, `premium`, billed monthly or annually
(`org.fynla.premium.monthly`, `org.fynla.premium.annual`). Four legacy plans exist
solely for grandfathering. Free is the default resolution for everyone else.

---

## The thing this session most needs to resolve

### A1 — The AI-cost guardrail is structurally fragile under free-forever

`04-product-strategy.md:97` sets **Fyn AI cost < 12% of ARR**. That guardrail was
written when every user paid.

**Under free-forever, cost and revenue decouple:**

- AI cost scales with **total active users** — free and paid alike
- ARR scales with **paid users only**

So the ratio can breach badly while the paid business is perfectly healthy, simply
because free usage grew. Or it can look fine while free-user economics are dire.
**It no longer measures what it was built to measure.**

This matters more for Fynla than for most freemium products, because the free tier
is not a static brochure — free users talk to Fyn, and Fyn is the expensive part.

**Proposal — keep the ratio, add the thing that actually controls it:**

| Metric | Purpose |
|---|---|
| Fyn AI cost as % of ARR | Keep. Board-level health. |
| **Fyn AI cost per active free user, monthly** | **New.** The actual cost driver. |
| Fyn AI cost per active paid user, monthly | Unit economics of the paid tier |
| Free → paid conversion rate | The thing that makes free-user cost worth bearing |

And a decision I can't make for you: **is there a ceiling on free-user AI cost**,
and what happens at it — degrade, throttle, cap, or absorb? Absorbing is a choice;
it just needs to be a chosen one rather than a discovered one.

### A2 — "CAC payback < 6 months" is now ambiguous

With free-forever, acquisition spend buys mostly free users. **Is CAC measured per
acquired user, or per acquired *paying* user?** They differ by the conversion rate,
which is to say by a lot. Proposal: define as **paid CAC** — spend divided by
paying customers acquired — with blended CAC reported alongside.

### A3 — Does the north star survive?

**Paid Active Households** — ≥1 paid subscription, logged in within 30 days, ≥3
modules populated.

**Proposal: keep it, unchanged.** A north star should be singular and should be the
thing the business lives on. But free-tier health is now invisible in the metric
set, so free-tier metrics join as **inputs**, never as competing north stars.

Worth noting the tension with V1: access, not exclusivity. Free users are clients
under `01-mission.md`, not merely a funnel. Measuring only paid households risks
the workforce treating free users as a cost centre. **Proposal:** one free-tier
health metric is elevated to guardrail status so it cannot be optimised away —
suggest *active free households* — sitting alongside churn and the AI-cost ratio.

---

## Part B — Ready to adopt as written

Unless you say otherwise these go into `06-commercials.md` unchanged:

**Model:** free tier forever; one paid upgrade (`premium`), monthly or annual;
four legacy plans grandfathered; **more than one paid tier is a hard no**
(`03-hard-nos.md` §2) and re-tiering is a trunk amendment.

**Health guardrails** from `04-product-strategy.md:91–99`: monthly churn <3% · CAC
payback <6 months *(pending A2)* · net revenue retention ≥110% at month 12 ·
support tickets <8 per 100 active users per month · Fyn AI cost <12% of ARR
*(pending A1)* · crash-free ≥99.5% web and mobile · seed-drift incidents 0.

**Input metrics:** weekly new paid signups · channel mix · 14-day activation
(≥3 modules) · spouse-add rate within 60 days · referral rate in first 90 days.

**Spend authority: £0** — every spend is a gate, including free tiers requiring a
card and anything that renews (`charter.md` §5, already ratified).

**Go-to-market sequencing: Azlan's**, not defined here (`01-mission.md` §5).

---

## Part C — Open

1. **A1** — free-user AI cost ceiling, and what happens at it.
2. **A2** — CAC defined as paid or blended.
3. **A3** — elevate a free-tier health metric to guardrail?
4. **Premium price.** Not in the repo — it lives in Revolut and App Store Connect.
   Should the trunk record it, or point at those as canonical? **Proposal: point.**
   A price written in two places will diverge, and the payment provider is the one
   that actually charges.
5. **Grandfathered legacy subscribers** — is there an intent to migrate them to
   premium eventually, or do they stay indefinitely? Affects whether
   `LEGACY_PAID_PLANS` is permanent machinery or temporary.

---

## Completeness check

**Ready to write:** Part B in full.

**Cannot write without a decision:** A1 and A2 — both change what Intelligence
reports from Phase 1, and Intelligence ships in Phase 1.

**What I'll assume otherwise:** guardrails as written, north star unchanged, price
by reference rather than recorded, CAC as paid CAC.

**For the divergence register:** commercials are Brett's domain. This file is the
most likely of the nine to be revised when he is interviewed, and A1–A3 are exactly
the questions he should be asked.
