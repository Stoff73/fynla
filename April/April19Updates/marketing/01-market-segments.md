# Two Niche Target Markets for Fynla

**Date**: 2026-04-19
**Framework**: `pm-market-research:market-segments`
**Method**: Codebase analysis (seeded personas, module service depth, distinctive features) + standard market-segmentation criteria.

Based on where the product has unusual functional depth (Estate: 27 services, Investment: 25, Coordination: 8 services dedicated to couples) and distinctive features that don't exist in generic UK robo-advisors, the two niches below stand out as markets Fynla is *already built to serve* — not ones it would have to pivot toward.

---

## Segment 1 — "IHT-Anxious Mass-Affluent Couples" (£800k–£2m households, age 50–70)

### Overview
UK couples sitting on £800k–£2m in combined assets (typically main home + DB pension + ISAs + one buy-to-let or secondary property), approaching or in early retirement, actively worried about what their children will inherit versus what HMRC will take. Estimated ~1.2m UK households (ONS Wealth & Assets Survey: top decile, owner-occupiers with private pensions). Growing ~3–4%/yr as house-price appreciation pushes more households over the frozen £325k NRB.

### Demographics
- Age 50–70, married or long-term cohabiting
- Combined household income £80k–£200k (often one DB pensioner + one still working)
- Main residence £500k–£900k; often a secondary property or BTL
- At least one DB or public-sector pension in the household
- 2+ adult children (the intended beneficiaries)
- Seeded personas that map here: "Mitchell" (peak earners) and "Bennett" (retired couple)

### Jobs-to-be-Done
- **Primary job**: "Pass as much of what we've built to our children without overpaying IHT, without giving up control or income security while we're alive."
- **Supporting jobs**: Decide whether to gift now vs hold; understand the 7-year clock on past gifts; know if gifting £6k/yr is enough; model whether downsizing loses RNRB; coordinate spouses' NRB transfer; understand when a trust is worth the cost.
- **Stakes**: £100k–£500k of avoidable IHT on a typical £1.5m household at second death. Also emotional — "fair" split between children.

### Pain Points
- Robo-advisors (Nutmeg, Moneyfarm, Vanguard UK) don't do estate planning at all
- IFAs charge 1%+ AUM, often have £500k–£1m minimums, and deliver glossy PDFs once a year
- STEP-qualified estate planners charge £2k–£5k per plan and don't update as rules/assets change
- DIY via HMRC guidance is legally dense and easy to misapply (RNRB taper, GWR rules, gift-out-of-income tests)
- Couples rarely have a single shared view — spouses hold different accounts, platforms don't merge

### Desired Gains
- A single household-level net-worth view with IHT liability front-and-centre
- Gift register with automatic 7-year taper tracking
- "What if we gifted £50k now?" scenario modelling
- Explicit NRB + RNRB + spouse-transferred NRB breakdown (not a generic 40% estimate)
- Confidence that the numbers refresh as the Budget changes tax rates

### Product Fit — Strong
Fynla's Estate module (27 services) already covers: NRB/RNRB with taper, spouse-transferred NRB, 7-year gift taper with sliding relief, BPR, trust modelling, chattels, wills with conditional legacies, LPAs. The `joint_owner_id` pattern + Coordination module make it one of the few UK apps that models a *household* rather than an individual account. The "Retired Couple" persona proves decumulation + estate is a first-class flow.

### Competitive Landscape
| Alternative | Gap Fynla fills |
|---|---|
| Nutmeg / Moneyfarm / Vanguard UK | No estate planning, no IHT calc, no couple view |
| Hargreaves Lansdown / AJ Bell | Platform only, no advice, no household rollup |
| St. James's Place / traditional IFA | £500k+ minimum, 1%+ AUM, slow to update |
| Spreadsheet + HMRC manuals | No 7-year gift tracking, no automatic RNRB taper |

**Why "niche"**: The population is smaller than "UK adults with savings" (~30m) but the pain is acute and under-served — no consumer-priced product models IHT properly for couples.

---

## Segment 2 — "UK Owner-Operator Limited Company Directors" (age 35–55, Ltd Co income £120k–£500k)

### Overview
Founder-owned UK limited companies (consultants, agency owners, contractors, professional services) where the director's personal finances, pension, and estate are entangled with the business. Roughly 2m active micro-companies in the UK (Companies House), of which ~300k–500k are "lifestyle" or scaling owner-operator businesses with meaningful retained profits.

### Demographics
- Director–shareholder, typically 50–100% equity stake
- Mix of salary (≤£12.5k to stay under NI) + dividends (£20k–£150k+) + retained profits
- Often single (no spouse to split dividends) or spouse-shareholder for income-splitting
- SIPP or workplace pension used aggressively for corporation-tax relief
- Business valuation £250k–£2m; may have director's loan, shareholder agreement, key-person cover
- Seeded persona that maps here: "Chen" (entrepreneur)

### Jobs-to-be-Done
- **Primary job**: "Extract wealth from my company tax-efficiently during my lifetime, and make sure the business doesn't collapse or trigger an IHT disaster if something happens to me."
- **Supporting jobs**: Decide dividend vs pension vs retained profit each year; model BPR eligibility so shares pass IHT-free; keep key-person cover sized correctly; plan the exit (sale, succession, wind-down); coordinate with accountant.
- **Stakes**: £20k–£60k/yr in avoidable personal tax. Loss of BPR at death = 40% IHT on a £1m business = £400k.

### Pain Points
- Accountants handle corp tax + payroll but don't do personal estate or life cover
- IFAs do personal wealth but rarely understand Ltd Co extraction strategy or BPR
- Robo-advisors ignore business assets entirely
- Business valuation for IHT purposes is a blind spot most founders discover at probate
- Key-person insurance is frequently under-sized or missing
- No integrated view of "personal + business + pension + estate"

### Desired Gains
- A single dashboard showing personal net worth *including* business equity
- BPR eligibility flag on shareholdings (2-year holding period, trading-not-investment test)
- Key-person cover adequacy check linked to business valuation
- Pension-vs-dividend "what's the optimal mix this tax year?" modelling
- Succession letter / shareholder agreement storage
- Non-dom status tracking where relevant (the Chen persona explicitly handles this)

### Product Fit — Strong
Fynla's Business Interests model (Ltd/LLP/sole trader, earnings-multiple vs book-value valuation, BPR eligibility), key-person insurance linked to business, director's loan tracking, SIPP with corp-tax-relief projection, Letter to Spouse for succession, and non-UK-dom tracking together cover a stack that no mainstream UK consumer product assembles. The Entrepreneur persona is feature-complete proof.

### Competitive Landscape
| Alternative | Gap Fynla fills |
|---|---|
| Xero / FreeAgent / accountant portal | Business-only; no personal estate, no BPR planning, no life cover |
| PensionBee / Penfold | Pension-only; ignore dividends, BPR, business valuation |
| Traditional IFA | Often weak on Ltd Co extraction mechanics; slow |
| Accountant + separate IFA + separate solicitor | Three silos, no shared data, founder is the integrator |

**Why "niche"**: Far smaller than "UK adults" but the customer has high willingness-to-pay (already spending £2k–£10k/yr on accountants + advisors) and the pain — personal/business entanglement — is structural, not solved by any existing single product.

---

## Why These Two (and Not "Young Families" or "Students")

The Young Family, Young Saver, and Student personas exist in the product, but they compete directly with Moneybox, Plum, Vanguard UK, and Monzo — categories with £100m+ marketing budgets and simpler needs. The IHT-Anxious Couple and Owner-Operator Director niches are **defensible** because the product's deepest modules (Estate, Coordination, Business Interests, DB pensions) specifically address problems those competitors don't touch, and the customers have both acute pain and willingness to pay.

## Evidence base

**Module service count (signal of "who this is really built for"):**

| Module | Services | Signal |
|---|---|---|
| Estate | 27 | IHT, trusts, gifts, spouse NRB, wills, LPAs |
| Investment | 25 | Portfolio management, tax wrappers |
| Retirement | 12 | DB/DC pensions, decumulation |
| Goals | 12 | Life events, dependencies |
| Savings | 9 | ISAs, emergency funds |
| Coordination | 8 | Household/couple features |
| Protection | 7 | Life/CI/IP policy adequacy |

**Distinctive features (not in generic robo-advisors):**
- Defined Benefit pension handling (NHS, Civil Service) with survivor benefits
- IHT mastery (NRB + RNRB taper + spouse transfer + 7-year gift taper + BPR)
- Lifetime ISA (LISA) with age constraint + 25% bonus
- Buy-to-let / multiple property support
- Business interests with BPR eligibility + valuation methods
- Key-person insurance linked to business valuation
- Non-UK-dom status tracking
- Spouse-aware joint ownership via `joint_owner_id`
- Student loan (Plan 2 + Plan 5) repayment modelling
- Trust and LPA modelling

**Next step**: Run `pm-go-to-market:beachhead-segment` to score these two against burning pain / WTP / winnability / referral potential and pick the first to concentrate launch resources on. *(See `02-beachhead-analysis.md`.)*
