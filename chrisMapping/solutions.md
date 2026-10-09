# Fynla and Fyn — The Problems They Solve

**Stamp:** commit `a7c13608a`, 2026-09-22.
**Sources:** the ratified constitution (`workforce/core/constitution/01-mission.md`, `02-values.md`, `03-hard-nos.md`, `05-perimeter.md`, `06-commercials.md`), the public pages (`public/pages/index.php`, `about.php`, `how-it-works.php`, `pricing.php`), `README.md`, and the persona files. Where a claim is doctrine rather than code, the file is cited so the reader can see it is a decision, not an observation.

---

## 1. In plain English

Good financial planning in the UK is rationed by price. The families who can afford an adviser see their business, pension, property and estate as one picture and plan across all of them. Everyone else has the same problems and none of the tools. Fynla is the tool: it puts a household's whole financial life in one place, does the calculations the adviser would do, and tells the person, in plain English and in pounds, what is wrong and what they could do about it. Fyn is the conversational face of that engine, so a person can ask instead of hunt.

---

## 2. The vision, verbatim

From `01-mission.md` §1 (adopted from the April 2026 product strategy):

> Every UK household should plan its money the way the wealthiest families do — seeing business, pension, property, and estate as one living picture — and pay £20/month for it, not £20,000/year.
>
> Fyn, the AI companion, is the thing that makes that price point possible: the financial reasoning of a £500/hour advisor, always on, never scolding, never selling you a product.

The public "About" page says the same thing to customers: "Fynla exists because financial clarity should not be a luxury reserved for the wealthy" and describes the product as "the sat nav for your financial life" (`public/pages/about.php`).

---

## 3. The problems, one by one

### 3.1 Financial data is scattered, so nobody sees the whole picture

**Problem.** Pensions, property, savings, investments, insurance and debts live with different providers. A person cannot answer "what am I worth, and where am I exposed" without spreadsheets.

**What Fynla does.** Seven integrated modules (Protection, Savings, Investment, Retirement, Estate, Goals and Life Events, Coordination) over one database, with a net-worth balance sheet and a Coordination layer that reads across all of them. The homepage sells this as "One financial view. Use Fynla to securely centralise and view all your financial data." (`public/pages/index.php`; `README.md` Overview; `app/Agents/CoordinatingAgent.php`).

### 3.2 The calculations are hard and the rules change every year

**Problem.** Inheritance Tax thresholds, ISA and pension allowances, income tax bands, State Pension rules: the arithmetic that turns raw data into "you have a gap of £X" is exactly what people pay advisers for, and it changes with every Budget.

**What Fynla does.** An in-house UK tax engine (`TaxConfigService`, `UKTaxCalculator`, the Estate module) with every figure loaded from configuration per tax year rather than hardcoded (`CLAUDE.md` Rule 2). The constitution names "Tax engine in-house" and "UK-only, deep" as deliberate strategic trade-offs: tax depth is the moat (`03-hard-nos.md` §1). The homepage calls this "One financial brain. Our proprietary brain does the calculations so you don't have to."

### 3.3 Households are planned as two disconnected individuals

**Problem.** Couples hold most UK wealth, but every incumbent tool treats a married couple as two separate accounts, so transferable allowances, joint assets and survivor planning fall through the gap.

**What Fynla does.** The household is the unit. Spouse accounts are linked by invitation, joint assets are one record with an ownership split, permissions are accepted from both sides, and the Coordination module and Fyn reason over the household (`03-hard-nos.md` §1 "Household as the unit"; `CLAUDE.md` Rule 6; `.claude/agents/persona-tester.md` "Both accounts, every time").

### 3.4 People cannot see the consequences of a decision before they make it

**Problem.** "What if I retire at 58? What if I overpay the mortgage? What if I gift £50,000 now?" are the questions that matter, and the answers require modelling.

**What Fynla does.** What-if scenarios, Monte Carlo projections, retirement income projections, IHT projections and gifting strategies, exposed on every module agent through a `buildScenarios` contract (`app/Agents/BaseAgent.php` abstract methods; `public/pages/how-it-works.php` "Pull the levers. See what changes."; `app/Jobs/RunMonteCarloSimulation.php`).

### 3.5 Advice is either unaffordable or a sales channel

**Problem.** Regulated advice costs thousands or is priced as a percentage of assets; "free" tools are paid for by advertising, referral kickbacks or product placement, which puts the tool on the opposite side to its user.

**What Fynla does.** A free tier forever plus one paid tier at £6.99 a month, and hard nos on advertising, assets-under-management fees, referral kickbacks and more than one paid tier (`02-values.md` V1 and V3; `03-hard-nos.md` §2; `06-commercials.md` §2; `public/pages/pricing.php` "There is no time-limited trial"). The seven regulatory rules that bind Fyn and all outbound copy include "no product, provider, fund or platform recommendations" (`.claude/agents/compliance-lead.md`).

### 3.6 Summaries and scores hide the thing the person needs to learn

**Problem.** A "72/100 financial health score" answers the question and destroys the reason. The person learns nothing about their own money.

**What Fynla does.** No scores or ratings anywhere in the product; specific figures, gaps in pounds, time periods and actions instead. This is a ratified value, not a style choice: "Understanding is the product; answers alone are not" (`02-values.md` V2; `CLAUDE.md` Rule 12). Acronyms are spelled out on the surface the user is looking at (Rule 9).

### 3.7 Money is shameful, so tools that judge get closed

**Problem.** People avoid financial tools that feel like an exam or a telling-off.

**What Fynla does.** The voice constants: never patronising, never alarmist, never condescending, no false urgency, no manufactured scarcity (`04-voice.md` §2). The "About" page promises "Financial planning that feels like a conversation, not an exam." Fyn's register is "warm, conversational, calm. Ends on a question" (`04-voice.md` §3).

### 3.8 Getting the data in is the hard part

**Problem.** Every planning tool dies at the empty form. People do not know their pension values, and typing thirty fields is where they give up.

**What Fyn does.** Onboarding Fyn captures a household conversationally, one bubble at a time, and is the only write path into the database from chat; on `/m` and native, Fyn drives the input rather than forms. Document upload with AI extraction reads statements and fills fields (`fyn-architecture` skill "Onboarding Fyn"; `app/Services/Onboarding/OnboardingChatDirector`; `app/Services/Documents/AIExtractionService.php`).

### 3.9 Even with the data in, the person does not know what to do next

**Problem.** A dashboard full of numbers is not a plan.

**What Fynla and Fyn do.** Each module agent generates ranked recommendations; the Coordination agent produces one household ranking; the dashboard and the `/m` "next actions" surface show them as actions; Fyn answers questions against the same recommendation engine and can route a "yes please" into the action (`app/Agents/*::generateRecommendations`; `app/Services/Mobile/NextActionsService.php`; `fyn-memory/procedural/recommendation-routing.md`). Scheduled alerts (protection, savings, estate, mortgage rates, business filings) keep nudging after the session ends (`app/Console/Kernel.php:37-46`).

### 3.10 Staying in the perimeter: guidance, not regulated advice

**Problem.** Telling people what to do with pensions and investments is regulated activity. A tool that drifts into advice invites FCA scrutiny and would need Part IV permission.

**What Fynla does.** It is deliberately "an AI-augmented tool" rather than "a full replacement for an IFA", runs in fail-closed guidance mode, hedges every recommendation, signposts regulated advice for complex matters, and never quotes a tax figure from memory (`03-hard-nos.md` §1; `05-perimeter.md` §1; `ComplianceRules.php` via `.claude/agents/compliance-lead.md`).

---

## 4. Who it is for

Income is explicitly not the test. "Whether someone is our client is a matter of their situation, never their income" (`01-mission.md` §2). The six seeded personas are the definition of the client base, and they are a sequence of life stages rather than wealth tiers (`01-mission.md` §3):

| Persona | Situation |
|---|---|
| Student (Janice Taylor) | Lifetime ISA, student loan, early career |
| Young saver (John Morgan) | Emergency fund, first-time saving |
| Young family (Emily and James Carter) | Mortgage, workplace pensions |
| Entrepreneur (Alex Chen) | SIPP, business interests |
| Peak earners (David and Sarah Mitchell) | Multiple properties, SIPP plus NHS pension |
| Retired couple (Patricia and Harold Bennett) | Decumulation, estate planning |

Three structural exclusions only: non-UK residents (the tax engine is UK-specific), business-only customers (the edge is the personal–business bridge), and under-18s (`03-hard-nos.md` §3).

---

## 5. What Fyn specifically adds

Fyn is not a separate product; it is the interface that makes the price point work. Its jobs, each traced in `agentsMap.md`:

1. **Capture** the household in conversation instead of forms (Onboarding Fyn, write state).
2. **Explain** the person's own numbers using the same engines the dashboard uses, read-only (Advice Fyn).
3. **Route** a recommendation the person accepts into the matching action, deterministically.
4. **Remember** how Fynla does things (procedural memory), what has happened with this person (episodic memory) and durable house knowledge (semantic memory), while never freezing a figure that has a live owner (the pointer model).
5. **Stay inside the perimeter** mechanically: write tools stripped from the advice catalogue, hedged language, tax figures only via tools, per-user token budgets so cost cannot run away.

---

## 6. How success is measured

The north star is Paid Active Households: at least one paid subscription, logged in within 30 days, at least three modules populated. Household, not user, "because the couple/family view is the product's asset". Free-tier health is reported alongside, because free users are clients, not a cost centre (`.claude/agents/intelligence-lead.md`; `06-commercials.md`).
