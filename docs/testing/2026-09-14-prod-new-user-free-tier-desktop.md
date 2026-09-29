# Production new-user run, Free tier, desktop web — 14 September 2026

**Tester:** Claude (driven by Brett Isenberg), Chrome desktop, 1358 x 898 viewport.
**Account:** `isenbret+fynla1409@gmail.com`, "Tom Harris", registered 09:50 UTC on fynla.org, Free tier, cookies declined. **Please purge this account after review.**
**Scope:** register, onboard, enter a modest household, read every module a Free user can reach, and question Fyn hard on retirement, estate and next steps. Web only, as asked. No code changes were made.

## Verdict in one paragraph

The data-entry side of Fynla is solid: every form saved what I typed, every dashboard figure reconciles to the penny, and the Free-tier caps and upgrade prompts are honest and well written. The product falls down on the one question this persona came to ask. "When can I retire?" got one good answer and then three consecutive failures from Fyn, including a reply that claimed no pension was on file, while the Retirement page itself shows a middle-outcome projection of £0 beside a lower outcome of £189,420. For a beginner, the retirement surface is currently the least trustworthy part of the app, and it is the part the marketing leads with. Everything else is a list of fixable polish issues and a handful of genuine bugs.

## The persona

| Item | Entered |
|---|---|
| Person | Single, born 15 June 1984 (42), employed IT project manager, Reading |
| Salary | £48,000, plus £780 estimated savings interest |
| Home | Main residence £325,000, Nationwide repayment mortgage £180,000 at 4.2% fixed, £1,050 a month, ends March 2048 |
| Running costs | Council tax £165, gas £60, electricity £80, water £35, insurance £40 a month |
| Cash | Marcus easy access £12,000 at 4.0% (emergency fund), Halifax Cash ISA £8,000 at 3.8% |
| Investment | Vanguard Stocks & Shares ISA £15,000, £100 a month, £500 paid this tax year |
| Pension | Aviva workplace pension £38,000, employee 5%, employer 3% |
| Debts | Car finance £6,000 at 7.9% (£220 a month), Barclaycard £2,500 at 22.9% (£100 a month) |
| Other spending | £1,000 a month |
| Goal | Debt repayment £2,500 by September 2027 |
| Will, protection | None |
| Retirement target | £30,000 a year at 65 |

Expected dashboard figures, all confirmed correct on screen: assets £398,000, liabilities £188,500, net worth £209,500, emergency fund 6.6 months.

## Bugs

Severity is my judgement of impact on a real Free user. "Where" is the surface; the evidence column says what I saw.

### Critical

| # | Where | What happened | Evidence |
|---|---|---|---|
| C1 | Fyn, retirement questions | Retirement-age questions fail three times out of four. "When can I retire?" answered in ~25 s. The follow-up "give me an age" showed "Processing your request..." for 80 s, then "Checking your accounts...", then after ~2 min "Fyn couldn't generate a response. This can happen with longer conversations" on turn 2. A rephrase sat on "Waiting — cancel" for over 4 min. "Can I retire at 60?" in a fresh conversation failed the same way on turn 1. "If I stopped working at 60 would my pension be enough?" replied "I don't have any pension records on file for you yet" although the £38,000 Aviva pension is on the dashboard and Fyn had quoted it minutes earlier. Every non-retirement question (six of six) succeeded. | Network log: conversation 856 returned six consecutive HTTP 503 on POST `/api/ai-chat/conversations/856/messages`; conversation 857 returned 200 with the failure copy. |

### High

| # | Where | What happened | Evidence |
|---|---|---|---|
| H1 | Retirement page | "Projected Value (middle outcome) £0" displayed next to "Lower outcome (4 in 5 do better) £189,420". The middle outcome cannot be below the lower one. | `/net-worth/retirement` pension pot projection card |
| H2 | Retirement page vs Fyn | Two projections for one pension. Page: £8,903 a year, £189,420 pot. Fyn: £9,575 a year, £239,371 pot. Same user, same minute. | Fyn conversation "When can I retire?" |
| H3 | Retirement vs Investments | Default risk assumption differs per module for a user who never set a risk profile: Retirement uses "Upper-Medium Risk, 6.50%", Investments uses "Lower-Medium Risk, 3.50%". | Both projection cards |
| H4 | Fyn, arithmetic | Fyn stated contributions of "£240 a month from you and £144 from your employer". Actual is £200 and £120 (5% and 3% of £48,000; the pension card shows £320). Both figures are exactly 1.2x. The same reply then says "£3,840 of your £60,000 Annual Allowance", which is 12 x £320, so it contradicts itself. Separately, employer NI saving from salary sacrifice quoted as £36; 15% of £2,400 is £360. | Fyn conversation "Should I use my savings..." turns 5 and 6 |
| H5 | Fyn, history | Ten empty "General Fyn conversation" rows created in about 30 minutes alongside three real conversations. One appears each time the panel opens or the route changes. The panel also resets to "Hi, I'm Fyn" on every page navigation instead of resuming. | Fyn History list |
| H6 | Onboarding layout | On the Family step and again on the Debts step after saving, the content pane keeps a blank area roughly three screens tall, pushing Back/Continue below the fold. On the Family step the "Why we ask this: Highest Education Level" tooltip from step 1 is still shown. | Screenshots ss_4861cqub4, ss_2819ibev0 |

### Medium

| # | Where | What happened |
|---|---|---|
| M1 | Bank account form | Product Type dropdown lists Easy Access, Notice Account, Cash ISA and Premium Bonds twice each; "Fixed Rate" and "Fixed Term" both map to value `fixed`. |
| M2 | Bank account card | Halifax card shows the raw code "cash_isa" where the Marcus card shows "Easy Access". |
| M3 | Cash ISA form | ISA allowance panel shows "Stocks ISAs: £0" and "£20,000 remaining" although the Stocks & Shares ISA had just been saved with £500 subscribed and £700 planned. The S&S ISA form itself correctly showed £18,800 remaining. |
| M4 | Retirement page | State Pension is never captured and the projection silently excludes it, so income shows £8,903 a year against a £30,000 target. The "Your State Pension forecast" outstanding link points at the same page. |
| M5 | Estate | `/estate` and `/estate/will-builder` both redirect to a paid-plan teaser. The pricing page says Inheritance Tax planning is "Preview" on Free; no preview exists. The dashboard Estate tab still lists four actions (No Will, two LPAs, beneficiary review) a Free user cannot act on. |
| M6 | Onboarding, Estate step | "Why we ask this" reads "Your yes helps Fynla build a more accurate..." after answering No. Template placeholder not filled. |
| M7 | Onboarding, About You | Step badge shows "56%" after every required field is completed. Meaning unclear; probably counts blank optional address and phone. |
| M8 | Onboarding, Spending | "Financial Commitments £2,050" with no breakdown. It is mortgage £1,050 + running costs £380 + car £220 + card £100 + pension £200 + ISA £100. A beginner cannot reconcile it and can easily double count by entering total spend in the box beneath. The Expenditure page does have the expander; onboarding does not. |
| M9 | Dashboard recommendations | Contradictions: Goals tab says "No Emergency Fund Goal" while the Savings card says "Emergency fund on track"; "1 goal falling behind schedule" 30 seconds after the goal was created; three of four Savings actions are nags about the goal. Protection copy says "Make sure your family is covered" and recommends decreasing term life cover to a single person with no dependants. |
| M10 | Dashboard | Save Tax tab is selected by default and is empty ("—", "No recommendations here right now", "0 / 0"). Retirement card says "0% of target" before any target was asked for. |
| M11 | Footer, every authenticated page | "For demonstration purposes only" and "This system is for demonstration purposes only and does not constitute regulated financial advice", plus "v1.0", on the live paid product. |
| M12 | Onboarding, Goals | No Retirement goal type although the side panel talks about retiring by a certain age. |
| M13 | Fyn, guidance | "Top 3 things to do next" contradicted the previous turn (clear the card now from Marcus vs drip £208 a month to the goal) and omitted the State Pension forecast Fyn itself called the biggest gap. It reconciled well when challenged. |
| M14 | Fyn, guidance | "How much should I be paying into my pension?" never produced a figure, only "plenty of room to increase contributions". |

### Low

| # | Where | What happened |
|---|---|---|
| L1 | Liability form | Repayment estimate rounds months up then multiplies: 31 x £220 = £6,820 total, interest £820. Amortisation gives about £640. Same pattern on the card (35 x £100). |
| L2 | Onboarding, About You | Marital status offers single/married/divorced/widowed only; no civil partnership or cohabiting. Health options ("Yes, previous health conditions" / "No, previous health conditions") read ambiguously. Middle-name helper claims it matches identity "across financial accounts and official records". |
| L3 | Onboarding, Income | Salary asked again after the pension form already took it, no prefill. Retirement Age placeholder 65 here, 67 defaulted on the pension card. Helper "Use the HMRC tax calculator to estimate your tax" sits under Retirement Age. "Did you know" about 40% higher-rate relief shown to a basic-rate earner. |
| L4 | Register page | Cookie dialog overlays the form and swallows typing; decline is two clicks. Submit button has no accessible name. |
| L5 | Landing page | "1000's of financial plans". Mega-menu items read "Gettingstarted" and "FynlaFeatures" in the accessibility tree (not visually checked). Save Tax box reads "up to each year" to a screen reader. |
| L6 | Fyn rendering | "Let me fetch that analysis now.In simple terms" — tool narration and answer run together. |
| L7 | Fyn panel | Overlays page content instead of reflowing it; on Cash Management the Cash ISA column is half hidden and NS&I fully hidden. Suggestions hidden behind "show" by default. |
| L8 | Net worth page | Asset allocation donut has a stray lavender ellipse over the top-left of the ring (screenshot ss_3348oxb2y). |
| L9 | Investments page | "Analytics — Coming Soon — Bloomberg / Morningstar / FE Analytics" placeholder card; "Monte Carlo (1,000 iterations)". |
| L10 | Subscription modal | Does not close on Escape; single plan labelled "Most Popular"; feature names differ from the pricing page ("Holistic Plan", "Retirement decumulation planning"). |
| L11 | Protection page | Title bar reads "Family". |
| L12 | Cookies | A plausible.io event fires with cookies declined. Consistent with the "only GA and affiliate are switched off" wording, but worth confirming that is intended. |

## What worked well

- Registration and verification: code email arrived within seconds; the six-box code entry auto-advances cleanly.
- Every figure I entered came back correctly on the dashboard and Net Worth page. Pension monthly contribution (£320), property equity (£145,000), net worth (£209,500) and emergency-fund months (6.6) are all right.
- Pension type cards in onboarding explain money purchase, final salary and State Pension in plain English. A beginner needs exactly this.
- The ISA allowance tracker inside the Stocks & Shares ISA form (£18,800 remaining) is clear and correct.
- Free-tier gating is honest and well worded: "Your Free plan includes up to 2 bank accounts" before entry, and the statement-upload modal says "You can still enter the details yourself".
- Fyn on non-retirement topics is good. The credit-card answer (clear it from Marcus, net saving £472.50 a year, rebuild the fund) and the inheritance-tax answer (pension outside the estate until April 2027, nil-rate band £325,000, projected estate £1.16m at 80 and a £334,000 bill) were correct, specific and actionable. The plain-English "what is a pension pot" explanation was the best piece of copy in the whole run.
- The level-up moment after onboarding and "You're ahead of 89% of people" land well for a first-time user.

## Does it make sense to a layman?

**Onboarding.** Eight steps took me, driving fast, about 45 minutes; a human typing would take 30 to 40. The forms are comprehensive but ask for things a beginner does not have to hand: scheme names, policy numbers, mortgage account numbers, purchase dates, domicile status. None are marked optional, so the honest beginner stalls. The "Why we ask this" panel is a good idea and mostly well written, but it is undermined by the stale tooltip, the "Your yes" placeholder and generic "Did you know" boxes that talk about higher-rate relief and married couples to a basic-rate single person.

**Dashboard.** Understandable. The five finance cards are the right five things. The weak points are the empty Save Tax tab on first load, the "0% of target" retirement card, and recommendations that nag about a goal created seconds ago.

**Retirement page.** Not understandable for this audience. "Required Capital £638,298", "Projected Gross Income £8,903", four probability bands and "using high probability of 80% of achieving 6.5% returns" is adviser language. The page never says the one sentence a beginner wants: "On what you have entered, you could stop work at about age X; to make 65 work you need roughly £Y more a month." It also frightens: without the State Pension the income line is a third of the target, and nothing on the page says so plainly.

**Estate.** Fyn explained intestacy and inheritance tax better than any page could, but the module itself is a paywall for this user, and the dashboard keeps assigning estate actions they cannot complete.

**Fyn.** When it works it is the thing that makes the app make sense. It uses the user's own numbers, explains terms, and ends with a next step. Two things hurt it for a beginner: the retirement failures (the question they came with), and the disclaimer paragraph on every single reply, which reads as boilerplate by the third message.

## Is the guidance on next steps solid?

Mixed, and it depends which surface you ask.

- **Fyn, when asked directly**: yes for debt, will and inheritance tax. Correct maths, correct law, a concrete next action each time.
- **Fyn, "top 3 things to do"**: it read out the recommendation engine rather than reasoning, so it contradicted its own earlier advice and dropped the State Pension forecast.
- **Dashboard recommendations**: two thirds are data-entry nags ("No State Pension Forecast Entered", "Link an Account to Debt Repayment", "Add Your Fund Holdings") rather than financial guidance. The single most obvious win for this persona, clearing a 22.9% card from £12,000 of 4% cash, appears nowhere on the dashboard. Life cover is recommended to someone with no dependants.
- **Retirement**: no guidance at all beyond "add a State Pension forecast" and "consider salary sacrifice". No pension-contribution figure, no affordable age.

## Recommendations, in priority order

1. Fix the Fyn retirement path before anything else. Find why retirement-age prompts return 503 and "no pension records", and make the failure copy honest (it blames "longer conversations" on turn 1).
2. Make the Retirement page answer the question: one sentence with an affordable age, one sentence with the monthly contribution that makes the target age work, and the State Pension included by default at the full new rate with a clear "assumed, add your forecast to refine" label. Fix the £0 middle outcome and reconcile the page and Fyn to one projection.
3. One default risk profile across modules, or ask the question during onboarding.
4. Onboarding polish: the blank-pane bug on Family and Debts, the stale tooltip, the "Your yes" template, mark optional fields as optional, prefill salary from the pension form, show the Financial Commitments breakdown.
5. Recommendation engine: suppress goal nags for 7 days after creation, drop life cover when there are no dependants, show "clear high-interest debt from cash" when the rate gap is large, and reconcile the emergency-fund contradiction.
6. Remove "demonstration purposes only" from the production footer or word it as a regulatory disclaimer.
7. Fyn hygiene: stop creating empty conversations on panel open, resume the open conversation across navigation, show the adviser disclaimer once per conversation rather than per message.
8. Small data fixes: Product Type duplicates, `cash_isa` label, cross-form ISA allowance, repayment rounding, civil partnership option.

## Not covered this run

/m mobile web, Premium features (What If, decumulation, full estate), spouse and household, statement upload, two-factor setup, balance history. Suggested next runs: the same persona on Premium, and a financially literate persona who will push Fyn on tax detail and the projection assumptions.
