# Go-to-Market Plan — Fynla for UK Ltd Co Directors

**Date**: 2026-04-19
**Framework**: `pm-go-to-market:plan-launch`
**Launch date**: mid-June 2026 (8-week runway)
**Type**: Repositioning + channel launch for the existing Fynla v1.0 app into a defined niche
**Current state**: Live on production (fynla.org), payment infra working, 6 personas feature-complete, no dedicated founder-director acquisition engine yet

---

## Beachhead Segment

**Who**: UK IT contractors, consultants, and agency founders operating through a single-director Limited Company, £120k–£300k annual revenue, 2+ years trading, mix of salary + dividends + retained profits, business valuation £250k–£2m.

**Why them first**:
- Scored 36/40 on burning pain / WTP / winnability / referral (vs 29 for IHT-anxious couples — see `02-beachhead-analysis.md`)
- **Oct 2024 BPR cap is an acquisition catalyst with a closing window** — once the market absorbs the £1m BPR cap, the wedge dulls
- Founder-to-founder referral culture is the strongest in any UK consumer niche
- Business-as-payer makes £20–£30/mo feel free (tax-deductible)
- Natural expansion path into Segment 1 (same customer, 15 years later)

**Size**:
- TAM ~500k UK owner-operator Ltd Cos (Companies House, >£100k revenue)
- SAM ~150k digitally literate, self-service inclined (LinkedIn/ContractorUK active)
- SOM in 12 months: 1,500 paid users (0.1% of SAM, 1% of reachable SOM) = ~£360k ARR at Pro tier

---

## Ideal Customer Profile

| Attribute | Definition |
|---|---|
| Company type | UK Limited Company, sole director or director + spouse-shareholder |
| Revenue band | £120k–£300k annual (tight entry niche; expand later) |
| Years trading | 2+ years (enough retained profit + pension history to matter) |
| Primary extraction | Salary (£9k–£12.5k) + dividends (£30k–£100k) + pension contribution |
| Personal wealth | £300k–£1.5m (house + pension + retained profits + ISAs) |
| Age | 35–55 |
| Job title signals | "Director", "Managing Director", "Founder", "Consultant", "Contractor", "Principal" |
| Industry | IT contracting, management consulting, marketing/creative agencies, professional services |
| Existing stack | Accountant + Xero/FreeAgent + HL or AJ Bell + life insurance + spreadsheets |
| Core JTBD | "Extract wealth tax-efficiently while I'm alive, and make sure the business doesn't trigger an IHT disaster if I die" |
| Current pain | Business data (Xero), pension data (HL), personal wealth (spreadsheet), estate (nothing) live in silos — the founder is the integrator |
| Qualification signal | Mentions BPR, dividend vs pension, IR35, key-person cover, or "my accountant does the tax but…" |
| Budget authority | 100% — founder is the buyer, and expenses it through the company |

---

## Positioning Statement

> For UK founder-directors running a Limited Company, **Fynla** is the only personal financial planning app that sees your **business, pension, and estate as one picture**. Unlike Xero (business only), PensionBee (pension only), or your accountant (tax only), Fynla models how a decision in one corner — a dividend, a BPR-qualifying share, a key-person premium — ripples through your whole financial life.

**Elevator (founder-facing)**:
> Xero knows your company. HL knows your pension. Your accountant knows your tax. *No one knows all three — except you, in a spreadsheet at 11pm.* Fynla does.

**Hero hook (BPR wedge)**:
> The Oct 2024 Budget capped Business Property Relief at £1m. If your company is worth more, HMRC now owns 40% of the overflow at your death. **Check your exposure in 2 minutes →** *(free, no signup)*

---

## Messaging by Stakeholder

| Audience | Message | Proof point |
|---|---|---|
| **Founder-director** (primary buyer/user) | "One place for your business, pension, and estate. See the £ impact of every extraction decision." | Business Interests module with BPR eligibility + dividend/pension/salary optimiser + household estate view |
| **Spouse** (co-shareholder, future Family tier upgrade) | "Know where you stand if anything happens to them — the succession plan, the cover, the estate." | Letter to Spouse, joint ownership model, key-person cover adequacy |
| **Accountant** (referral channel) | "Keep the tax work. Give your clients a personal planning tool that makes *your* advice more actionable." | Accountant partner portal, revenue share, co-branded onboarding |
| **Solicitor / STEP planner** (affiliate channel) | "Refer clients to a tool that keeps their estate plan live between your meetings." | Will + LPA + Letter to Spouse modules, automatic IHT recalc |

---

## Channel Strategy (ranked by expected ROI)

| # | Channel | Tactic | Reach | Cost / month | Priority |
|---|---|---|---|---|---|
| 1 | **SEO content** | Free tools: "BPR Exposure Calculator", "Dividend vs Pension 2026/27", "Key-Person Cover Calculator". 20+ long-form articles targeting director-led queries | Organic compounding | £0 build + £2k/mo writing | **Must-do** |
| 2 | **LinkedIn founder content** | Chris-led personal brand: 3 posts/week, BPR/IR35/extraction stories, case studies | 10k–50k impressions/mo by mo 3 | £0 direct | **Must-do** |
| 3 | **ContractorUK forum** | Seeded expert presence (not spam), 2 threads/week answering real questions, calculator linked organically | 200k member base | £0 | **Must-do** |
| 4 | **r/UKPersonalFinance + IndieHackers** | Helpful comments, not promotion; link to free tools | 500k+ UK audience | £0 | High |
| 5 | **LinkedIn paid ads** | Lookalike of converters, targeting "Director" + "Limited Company" + UK | 50k-100k impressions | £3k/mo | High |
| 6 | **Accountant partnership pilot** | 3–5 small practices (Mazuma, Crunch alumni, regional firms), co-branded + rev share | ~2k client base per partner | £500 integration + 20% rev share | High |
| 7 | **Podcast tour** | Accountancy Podcast, Contractor Weekly, The Self-Employed Podcast, UK Startups | 50k listeners combined | £0 (pitch-led) | Medium |
| 8 | **IPSE & FSB member benefit** | Discount + co-marketing | 80k IPSE + 160k FSB members | Rev share | Medium |
| 9 | **Product Hunt UK launch** | Coordinated day-of push | 20k-50k views | £0 | Medium (launch week only) |
| 10 | **Email nurture** | 8-email drip from calculator capture → Pro trial | All captures | £200/mo (Loops / Resend) | Must-do |

---

## Launch Timeline

### Pre-launch (Weeks 1–8, Apr 22 – Jun 15, 2026)

| Week | Actions | Owner |
|---|---|---|
| 1 | Ship free **BPR Exposure Calculator** at fynla.org/bpr — no auth, email-gated result PDF | Dev (CSJ) |
| 1–2 | Rewrite landing page hero + 3 Ltd Co-focused landing pages (/contractors, /consultants, /agency-founders) | Design + content |
| 2 | Set up Loops / Resend nurture sequence (8 emails) | Ops |
| 2–3 | Publish 6 SEO pillar articles: BPR cap / dividend-vs-pension / key-person cover / succession / IR35 / director pension | Content |
| 3–4 | Record 4 short videos (90 sec each) for LinkedIn — BPR explainer, dividend-vs-pension demo, succession letter demo | Chris + editor |
| 3–5 | 20 founder user interviews (unpaid, 30 min, Zoom) — validate messaging + pricing + onboarding friction | Chris |
| 4–6 | Accountant partnership outreach — pitch 15 small practices, sign 3 pilots | Chris + BD |
| 5–7 | Seed ContractorUK, r/UKPersonalFinance, IndieHackers with helpful (not promotional) presence | Community lead |
| 6 | Draft Product Hunt listing, line up 30 supporters | Ops |
| 7 | PR: pitch *Money Box*, *Telegraph Money*, *FT Money*, *Contractor Calculator*, *Accountancy Daily* | PR |
| 8 | Final QA, payment flow retest, seeded personas verified, analytics events audited | Dev |

### Launch week (Jun 15–21, 2026)

| Day | Action |
|---|---|
| Mon | Product Hunt listing goes live + LinkedIn announcement post + podcast episodes drop |
| Tue | ContractorUK AMA thread + FT Money / Telegraph coverage if secured |
| Wed | Accountant partner emails to their lists (3 pilots x 2k clients = 6k reach) |
| Thu | LinkedIn paid ads activate (£500/day for 14 days) |
| Fri | Email wave to full calculator waitlist with launch offer (50% off Pro for 3 months) |
| Sat–Sun | Monitor, respond, capture testimonials |

### Post-launch (Weeks 1–12 after launch, through mid-Sept 2026)

| Phase | Focus |
|---|---|
| Weeks 1–4 | Acquisition: double down on whichever channel converts best (cut the rest); daily LinkedIn content |
| Weeks 5–8 | Retention + expansion: onboarding optimisation, first upsell offers to Family tier |
| Weeks 9–12 | References: collect 10 named case studies, 3 video testimonials, Trustpilot campaign; open referral programme |

---

## Pricing Alignment

| Tier | Price (launch) | Fit for Ltd Co director |
|---|---|---|
| Student £3.99/mo | — | Not relevant |
| Standard £10.99/mo | — | Feature-gated (no Business Interests) |
| **Family £14.99/mo** | ✔ | Spouse upgrade, household view — the post-beachhead upsell |
| **Pro £19.99/mo** | ✔✔ | **Entry tier for this beachhead** — Business Interests, BPR modelling, key-person cover, non-dom tracking |

**Explicit tax-deduction messaging**: *"£19.99/mo pays for itself at Corporation Tax — real cost to you: £14.99/mo."*

Launch offer: 50% off Pro for first 3 months for first 500 Ltd Co signups (builds on the existing 500-user launch pricing promise).

---

## Success Metrics

| Metric | 30-day target | 90-day target |
|---|---|---|
| Free BPR calculator completions | 1,000 | 4,000 |
| Email captures (calculator → waitlist) | 600 | 2,500 |
| Pro tier paid conversions | 75 | 300 |
| MRR from beachhead | £1,500 | £6,000 |
| Accountant partners signed | 3 | 8 |
| Named reference customers (case-study consent) | 3 | 12 |
| CAC (blended) | <£80 | <£50 |
| LTV:CAC (projected) | >3:1 | >5:1 |
| NPS (paid users) | ≥40 | ≥50 |
| Activation (modelled business + pension + estate within 14 days) | 50% | 65% |

---

## Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Trust gap — founders won't hand business data to a new fintech | High | High | Free no-auth BPR calculator as entry wedge; UK-hosted data messaging; SOC2/ISO roadmap published; add "accountant-recommended" badge once partners signed |
| BPR cap wedge dulls as market adapts | Medium | High | Hit hard in 90 days while wedge is hot; diversify hero hooks by month 4 (dividend tax, IR35, key-person) |
| Accountants see Fynla as competitive threat | Medium | Medium | Position explicitly as complement: "we don't file your tax" — partner portal, rev share, co-brand |
| LinkedIn paid CAC too high (>£150) | Medium | Medium | Cap spend at £3k/mo for first 60 days; cut if CAC>£120 and reallocate to SEO + community |
| Data security incident during launch | Low | Existential | Pre-launch pen test, no business data held in free calculator tier, separate staging data from production |
| Budget drift — Rachel Reeves changes rules mid-launch | High | Low | Built-in advantage: Fynla updates tax values via TaxConfigService, competitors don't — make that a feature story |
| Product bugs surface under acquisition pressure | Medium | High | Week-7 full regression test, mobile + web, all 6 personas; on-call rotation for launch week |
| Solo-founder bandwidth | High | Medium | Lock the scope: no new features during launch window; say no to adjacent requests |

---

## Expansion Plan

| Stage | Timing | Target |
|---|---|---|
| **Beachhead** | Mo 0–6 | UK IT contractors / consultants / agency founders, single-director Ltd Co, £120k–£300k revenue |
| **Beachhead deepen** | Mo 6–9 | Revenue band expansion up (£300k–£1m) and down (£60k–£120k) |
| **Adjacent 1: LLP partners & professional services firms** | Mo 6–12 | Solicitors, consultants in LLPs — similar JTBD, different entity mechanics |
| **Adjacent 2: E-commerce & SaaS Ltd Cos** | Mo 9–15 | Founder population with more complex cap tables (option pools, EMI) |
| **Household cross-sell (entry to Segment 1)** | Mo 9–18 | Spouse upgrade → household view → IHT view |
| **Segment 1 direct acquisition** | Mo 12–24 | IHT-anxious couples via (a) Telegraph/Times Money PR, (b) solicitor/STEP affiliate, (c) children-of-founders referrals |
| **Adjacent 3: NHS & Civil Service DB pensioners** | Mo 18–30 | Public-sector professionals — Mitchell persona already proves feature fit |

---

## Assumptions to challenge before committing

- Paid-ad budget can absorb £3k/mo on LinkedIn + £2k/mo on content writing — confirm before spend
- Pricing stays at current tier structure rather than re-tiering for the beachhead — confirm
- Chris has bandwidth for 20 founder interviews + 3 posts/week LinkedIn during 8-week runway — confirm or delegate
- Accountant partner integration (white-label onboarding, rev share) buildable in weeks 4–6 — scope needed
