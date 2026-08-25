# Product Strategy: Fynla

**Date**: 2026-04-19
**Framework**: `pm-product-strategy:strategy` — 9-section Product Strategy Canvas
**Stage**: MVP → early growth (v1.0 live, paid tiers active, <1k paying households, no confirmed PMF at scale)
**Author**: Chris Jones (founder)

---

## 1. Vision

**Every UK household should plan its money the way the wealthiest families do — seeing business, pension, property, and estate as one living picture — and pay £20/month for it, not £20,000/year.**

Fyn, the AI companion, is the thing that makes that price point possible: the financial reasoning of a £500/hour advisor, always on, never scolding, never selling you a product.

---

## 2. Target Segments

| Segment | Size (SAM, UK) | Pain Level | Current Alternative | Priority |
|---|---|---|---|---|
| **UK Ltd Co Founder-Directors** (single director, £120k–£300k revenue, age 35–55) | ~150k | 9/10 | Accountant + Xero + HL + spreadsheet | **P0 — Beachhead** |
| **IHT-Anxious Mass-Affluent Couples** (£800k–£2m, age 50–70) | ~300k | 8/10 | One-off STEP plan + IFA AUM fees | **P1 — Year 2 expansion** |
| LLP partners & professional-services firms | ~80k | 7/10 | Accountant + spreadsheet | P2 |
| NHS / Civil Service DB pensioners | ~200k | 6/10 | Employer scheme + HMRC guidance | P3 |
| First-home LISA savers | ~1m | 5/10 | Moneybox, Plum, Dodl | P4 |

**Primary segment**: UK Ltd Co Founder-Directors — concentrated pain, highest WTP, best referral culture, and a one-time acquisition wedge (Oct 2024 BPR cap) that is open *now*.

**Explicitly not serving**:
- **True HNW (>£5m)** — already have private bankers, Coutts, SJP; Fynla's price/positioning doesn't translate
- **Sub-£30k earners with no assets** — served well by Plum/Emma/Monzo; we add no value there
- **Non-UK residents** — tax engine is irreducibly UK-specific; international = different product
- **Business-only customers** — Xero/FreeAgent/QuickBooks win; Fynla's edge is the *personal–business bridge*, not the business
- **Day-traders and crypto-native users** — not modelled, not the JTBD
- **Under-18s** — LISA floor is 18, legal friction too high

---

## 3. Pain Points & Value Created

**For Ltd Co Founder-Directors:**
- **Pain**: Financial life sits in four silos — accountant has the business, HL has the pension, a spreadsheet has personal wealth, nobody has the estate. The founder is the integrator, and dividend-vs-pension decisions at year-end are guessed, not modelled. Post-Oct 2024 BPR cap, businesses >£1m carry silent £400k+ IHT exposure.
- **Current cost**: £1.5k–£5k accountant + £1k–£3k IFA + £2k–£5k one-off STEP plan = £5k–£13k/yr. Plus the 20+ hours/yr doing spreadsheet reconciliation.
- **Value delivered**: One dashboard that models the personal–business–pension–estate interaction. £240–£360/yr subscription (tax-deductible → effective £180–£270 net). A free BPR calculator that quantifies exposure in 2 minutes.

**For IHT-Anxious Mass-Affluent Couples:**
- **Pain**: No couple-level net worth view; IHT uncertainty; gifts made years ago that nobody is tracking on a 7-year clock; RNRB taper over £2m that nobody models correctly; spouses on different platforms.
- **Current cost**: £2k–£5k STEP plan every 5 years + potential £100k–£400k in avoidable IHT at second death.
- **Value delivered**: Automatic NRB/RNRB/spouse-transfer calc, live 7-year gift taper, "what if we gifted £50k" scenarios, household rollup across all assets. Re-runs automatically when HMRC rules change.

---

## 4. Value Propositions (JTBD framing)

**For Ltd Co Founder-Directors:**
> *When* tax year-end is approaching and I have to decide how to extract from my company this year, *I want to* see the 20-year personal wealth impact of each extraction path — not just this year's CT bill — *so I can* make the choice my 65-year-old self won't regret.

**For IHT-Anxious Mass-Affluent Couples:**
> *When* I realise the house + pension + ISAs put us over the IHT threshold, *I want to* understand which specific gifts, trusts, or BPR moves work for *our* situation — not a generic HMRC article — *so I can* pass what we built to our children, not to the taxman.

---

## 5. Strategic Trade-offs (the hard "no"s)

| We Choose | Over | Because |
|---|---|---|
| **Modelling money** | Managing money (robo-advisor) | Regulated asset management = FCA Part IV permission, compliance burden, slower product cycles. Tooling is defensible; allocation is commoditised. |
| **UK-only, deep** | Multi-country, shallow | Tax depth is the moat. Going wide dilutes the asset we can't easily re-build. |
| **Household as the unit** | Individual-account-first | Couples are 60% of UK wealth; every incumbent treats them as two disconnected users. The architecture is already built around this. |
| **Subscription** | Freemium with ads / AUM fees | Ads destroy trust with HNW cohort; AUM fees put us in an adversarial position to clients and require FCA permission. |
| **AI-augmented tool** | "Full replacement of an IFA" | Positioning ourselves as "advice" invites FCA scrutiny; positioning as "tool" keeps us agile and keeps accountants/solicitors as partners, not threats. |
| **Accountants as channel** | Accountants as product (no B2B-first SaaS) | We'd lose consumer velocity and fight Xero head-on. Channel partnership is a wedge, not a product pivot. |
| **Build the tax engine in-house** | License a tax engine | The TaxConfigService + 27-service Estate module is the thing no competitor has. Licensing it commoditises ourselves. |
| **No community / social features** | Forum / "share your plan" social layer | Financial data is private; social layer would bleed trust and engineering focus. |
| **No real-time trading** | Trading execution | Different regulatory regime, different product, different team. |
| **Ship on monthly cadence** | Big quarterly releases | Budget-day updates, Finance Bill changes, HMRC guidance — speed of tax-rule updates is a feature. |

---

## 6. Key Metrics

**North Star**: **Paid Active Households** — a household with ≥1 paid subscription, logged in within 30 days, and ≥3 modules populated. Household (not user) because the product's asset is the couple/family view.

**Input Metrics** (the levers):
- Weekly new paid signups
- Beachhead channel mix — % from SEO / LinkedIn / accountant partner / community
- 14-day activation rate (% completing ≥3 modules)
- Spouse-add rate (% of paid users who add a spouse within 60 days)
- Referral rate (% of paid users sending ≥1 referral in their first 90 days)

**Health Metrics** (the guardrails):
- Monthly churn (target <3%)
- CAC payback period (target <6 months)
- Net revenue retention (target ≥110% at month 12)
- Support tickets per 100 active users per month (target <8)
- Fyn AI cost as % of ARR (target <12%)
- Crash-free rate, mobile and web (target ≥99.5%)
- Seed-drift incident rate (target 0; known-sensitive metric given operational history)

---

## 7. Growth Engine

**Acquire:**
- Free tools as top-of-funnel (BPR calculator, dividend-vs-pension calculator) — no-auth, email-gated result PDF
- SEO: long-tail director-led queries ("BPR cap calculator", "dividend vs pension 2026/27", "key person cover calculator")
- Founder-led content on LinkedIn + niche forums (ContractorUK, IndieHackers, r/UKPersonalFinance)
- Accountant partner programme — revenue share + co-branded onboarding
- PR wedge around tax-rule changes (Budget day, Finance Bill, HMRC updates)

**Activate:**
- Persona-led onboarding — user picks life stage, gets pre-populated sensible defaults instead of an empty spreadsheet
- Fyn AI nudges the first 3 module completions within 14 days
- One "win" moment by day 7 — a concrete £ finding ("you're paying £4,200/yr more tax than necessary")

**Expand:**
- Spouse upgrade: Pro → Family tier once household is added
- Module expansion: director starts with Business Interests → prompted to add Protection (key-person cover sizing) → Estate (BPR modelling) → Goals (succession)
- Annual tax-year review triggers dormant reactivation

**Retain:**
- Budget-day auto-alerts — every user gets a personalised "here's what changed for you" email within 48h of a Finance Bill
- Gifting-anniversary reminders (7-year clock)
- Year-end extraction optimiser re-runs in March

---

## 8. Core Capabilities

| Capability | Build / Buy / Partner | Investment | Timeline |
|---|---|---|---|
| UK Tax Engine (TaxConfigService, all current rules) | Build | High ongoing — 0.5 FTE equivalent | Exists; quarterly + Budget-day updates forever |
| Estate & IHT modelling (NRB/RNRB/BPR/APR/taper/trusts) | Build | High | 27 services shipped; add APR + trust-distribution modelling Q3 |
| Business Interests + BPR eligibility | Build | Medium | Exists for single-director Ltd Co; extend to LLP + EMI + EIS Q3 |
| Fyn AI guidance layer | Build on Anthropic API | High — cost + prompt eng | In place; iterate monthly; monitor cost/ARR ratio |
| Payment infrastructure | Partner (Revolut) | Low | Live |
| Open Banking aggregation | Partner (TrueLayer or Plaid UK) | Medium | **Roadmap Q2–Q3** — biggest activation lever we don't yet have |
| Investment data feeds (fund lookup, holdings, pricing) | Partner (Refinitiv / ICE / Morningstar UK) | Medium | **Roadmap Q3** — blocks deeper Investment module depth |
| Mobile apps (iOS live, Android next) | Build (Capacitor) | Medium | iOS live; Android Q3; iPad-optimised Q4 |
| Accountant partner portal | Build | Medium | **Roadmap Q2** — required for beachhead channel |
| Security & compliance (SOC2 Type II, ISO 27001, Cyber Essentials+) | Partner (Vanta/Drata) + internal | Medium | **Start Q2, complete Q4** — critical trust enabler |
| PR / content engine | Build (in-house founder-led) + contractor writers | Low–Med | Activate week 1 of beachhead launch |
| Data residency (UK-hosted) | Already compliant | Low | Maintain; message explicitly |

---

## 9. Defensibility

**What Fynla has today (modest moat):**
- **Tax-engine breadth** — the TaxConfigService + 27-service Estate module + DB-pension handling + LISA/Plan2/Plan5 + BPR + non-dom tracking would take a new entrant 18–24 months to replicate. This is the strongest current moat.
- **Household data model** — `joint_owner_id` + Coordination module is architectural, not a feature bolt-on. Retrofitting it into single-user apps is expensive.
- **Speed of tax-rule updates** — founder-led, no committee, Budget-day same-week updates. Incumbents can't match this without restructuring.

**What Fynla can *build* into a moat over 24 months:**
- **Switching costs** — once a household has modelled assets + wills + 7 years of gift history + business valuations, the "leave" cost compounds. Target: median paid household has >£500k of assets modelled by month 6.
- **Data priors** — at 50k+ households, the platform learns real UK distributions (gift frequency, DB vs DC allocation, property-vs-pension ratios) that sharpen modelling. Not a moat today; a moat at scale.
- **Brand in the niche** — "Fynla = UK founder-director household planning" is a category we can own if we move before well-funded entrants.
- **Accountant-referral flywheel** — sticky distribution channel; hard to displace once 50+ practices are embedded.

**What Fynla does *not* have (be honest):**
- No network effects between users (each household is a silo)
- No patent protection
- No economies of scale in a meaningful sense (Anthropic API is variable cost)
- No two-sided marketplace dynamics

**Honest read**: the moat today is execution speed × UK-tax depth × household-first architecture. The strategy must deliberately *build* switching costs and category-brand over 24 months — the moat is not yet structural.

---

## Strategic Risks

1. **Well-funded entrant adds IHT + household + business in 18 months** (e.g. a recapitalised Nutmeg, a Wealthfront UK, or an accountant-led platform). *Mitigation: move now on Ltd Co niche while wedge is open; deepen switching costs through asset-graph completeness.*

2. **Regulatory reclassification** — FCA deems Fynla's Fyn outputs "personal recommendations" requiring Part IV permission. *Mitigation: explicit tool-not-advice positioning, audit Fyn prompts for advice-language triggers, maintain FCA-counsel relationship, avoid product recommendations.*

3. **Anthropic API economics break the £20/mo price point** — if Fyn interactions average >£3/mo per user in API cost, margin collapses. *Mitigation: caching aggressively; tiered Fyn access (free/basic/premium); prompt optimisation; dual-vendor option (Gemini/OpenAI as fallback).*

---

## One-Page Executive Summary

| | |
|---|---|
| **Vision** | Give every UK household the kind of integrated financial plan wealthy families pay £20k/yr for — at £20/month. |
| **Beachhead** | UK single-director Ltd Cos, £120k–£300k revenue, age 35–55. ~150k SAM. |
| **Differentiator** | Only UK product that models personal + business + pension + estate as one living picture, with automatic tax-rule updates. |
| **Business model** | Direct-to-consumer subscription, 4 tiers (£3.99–£29.99/mo). Accountant partnerships as secondary channel. |
| **North Star** | Paid Active Households. |
| **The big "no"** | Not a robo-advisor. Not an accounting tool. Not multi-country. Not ad-supported. |
| **24-month moat plan** | Switching costs via asset-graph depth + accountant-channel flywheel + UK-tax breadth. |
| **Biggest risk** | Regulatory reclassification of Fyn outputs as "advice" triggering FCA Part IV. |

---

## Next Steps

1. **Socialise + challenge** — review this with your closest trusted operator (not me; a real person with UK fintech scars) and stress-test the trade-offs
2. **Pressure-test with 10 target customers** — 20 min interviews with Ltd Co founders; ask whether the value prop rings true in their words
3. **Align the 12-month roadmap** to Section 8 capabilities — Open Banking + accountant portal + SOC2 are the three load-bearing items
4. **Set Q2–Q4 OKRs** from Section 6 metrics
5. **Lock the "no"s** — commit the trade-offs to a public-internal doc so feature requests get triaged against them

---

## One decision worth pushing back on

The vision sentence commits Fynla to *every UK household*, but the strategy commits to two tight niches for 24 months. That's a deliberate gap — niches fund the vision — but someone on your team will eventually argue for broadening too early. Be ready for that conversation.

---

**Complementary skills worth running next:**
- `pm-product-strategy:business-model` — Lean Canvas to pressure-test the economics
- `pm-product-strategy:market-scan` — SWOT/PESTLE/Five Forces to stress-test external assumptions (FCA regulatory drift is the biggest unknown)
- `pm-go-to-market:growth-strategy` — design the specific growth loops to make Section 7's engine real
