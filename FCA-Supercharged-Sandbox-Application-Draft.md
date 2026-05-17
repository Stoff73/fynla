# Fynla — FCA AI Supercharged Sandbox (Cohort 2) Application Draft

**Applicant:** Fynla — UK holistic personal financial planning platform (https://fynla.org)
**Programme:** AI Supercharged Sandbox, Cohort 2 (FCA × NVIDIA, 5-month TechSprint-style cohort)
**Draft prepared:** May 2026

> Each answer is sized to roughly 150 words to fit typical FCA portal field limits. Trim or expand per the live form.

---

## 1. Relevance and strategic fit

### Is the solution relevant to financial services, financial market infrastructure, or regulatory/compliance activity?

Fynla is a UK-domiciled holistic personal financial planning platform covering seven integrated advice domains: Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and a Coordinating layer that reasons across them. It implements UK-specific tax mechanics — Inheritance Tax with the Nil Rate Band and Residence Nil Rate Band, ISA allowances, the pension Annual Allowance and Money Purchase Annual Allowance, dividend and capital gains thresholds — through a single tax configuration service updated each tax year. Compliance considerations are first-class throughout: full audit logging on every state change, structured logging for traceability, Consumer Duty framing in the AI assistant's outputs, and explicit guidance/advice boundary controls. The product targets the UK advice gap: approximately 13.1 million UK adults with investable assets between £50,000 and £5 million who do not receive financial advice, yet still face complex coordination across mortgages, pensions, ISAs, life cover, and estate planning. Every surface is purpose-built for FCA-regulated retail financial services.

### Is the applicant engaging, or intending to engage, with the UK market?

Fynla is exclusively UK-focused. The product is incorporated in the UK, hosted on UK infrastructure, and live in production at fynla.org. The tax engine, life expectancy tables, mortgage logic, pension rules, and estate planning calculations are calibrated to HMRC, DWP, and FCA frameworks — there is no parallel non-UK build, and no intention to introduce one until UK product-market fit is established. We design against six representative UK life-stage personas (young family, peak earners, entrepreneur, young saver, retired couple, student) and intend to pursue the FCA authorisation pathway appropriate to a holistic guidance product once the AI components have been validated. The Sandbox cohort would directly accelerate this UK engagement by providing the structured regulatory dialogue we need to make those authorisation and product-perimeter decisions rigorously rather than speculatively.

### Is the proposed work appropriate for development, testing, or validation within the Supercharged Sandbox?

Yes — Fynla sits at the precise stage the cohort is designed for. The deterministic financial planning engine is production-ready; the next step is to layer probabilistic and generative AI on top: Monte Carlo retirement simulations, goal-achievement probability models, conversational planning agents grounded in a user's own data, and personalised next-best-action recommendations. These components require compute-intensive training, realistic synthetic UK consumer datasets, and sustained regulatory dialogue around explainability, model risk, and Consumer Duty alignment. None can be validated rigorously against live user data without first being hardened in a controlled environment. The Sandbox's GPU compute, curated synthetic datasets, and dedicated FCA coordinator map directly onto these needs. Our development plan is concrete, scoped to five months, and produces tangible AI artefacts and evaluation evidence we can demonstrate at Demo Day.

---

## 2. Degree of innovation

### Is the solution genuinely innovative or materially differentiated from existing market practice?

Fynla is built around a multi-agent architecture: nine specialised domain agents (ProtectionAgent, SavingsAgent, InvestmentAgent, RetirementAgent, EstateAgent, GoalsAgent and supporting agents) orchestrated by a CoordinatingAgent that arbitrates trade-offs across them. The system reasons about a user's full balance sheet rather than treating product silos independently. This is fundamentally different from the prevailing UK consumer fintech pattern of single-purpose tools — pension dashboards, ISA platforms, mortgage comparison sites, will-writing services — that each optimise locally and force the consumer to perform the integration. The coordinating layer is what unlocks genuinely useful AI: an assistant that knows about a user's mortgage *and* their pension contributions *and* their Inheritance Tax exposure can deliver materially better guidance than one which sees only one domain. We believe the architecture is novel in the UK consumer financial planning market.

### Has the applicant clearly articulated how the solution differs from existing products, services, or approaches?

Existing UK propositions cluster into three patterns: single-domain tools (pension calculators, ISA platforms, savings aggregators) that solve one problem in isolation; open-banking aggregators that surface transactions but offer little forward-looking planning; and traditional regulated advice, which is high-quality but priced out of reach for most consumers. Fynla differs on three axes simultaneously. First, scope: seven integrated domains under one model of the user. Second, architecture: a multi-agent system in which domain experts negotiate trade-offs (should marginal savings go to ISA, pension, or mortgage overpayment?) rather than a flat ruleset. Third, accessibility: the AI assistant brings holistic guidance to the 13.1 million UK adults in the advice gap. Each axis is incremental on its own; combined, they represent a meaningful step beyond current market practice and a substantively differentiated approach.

### Does AI play a meaningful and substantive role in the solution?

AI is structural, not decorative. The agent architecture is already in the production codebase: nine domain agents orchestrated by a coordinating agent, with an AI Chat module that lets users ask natural-language questions and receive contextualised answers grounded in their own financial data. The Sandbox work would extend this in three substantive directions. First, large language model reasoning over the user's full financial graph for explainable, personalised guidance, with model-risk controls suited to FCA Consumer Duty. Second, probabilistic models for goal achievement and retirement adequacy, trained and back-tested on synthetic consumer trajectories. Third, anomaly detection over user activity to surface fraud signals and vulnerability flags. Each is impossible to validate rigorously without GPU compute and realistic synthetic data — exactly the Sandbox's offering. AI is not an add-on feature; it is the mechanism by which holistic planning becomes economically deliverable to mass-market UK consumers.

---

## 3. Feasibility and readiness

### Is the solution at an appropriate stage of development for the Sandbox?

Yes. Fynla v1.0 is in production at fynla.org with all seven planning modules functional, a hardened UK tax engine, full authentication and payment infrastructure, an iOS application, and an extensive automated test suite. The deterministic foundation is built. What remains is the AI layer: probabilistic models, generative explanation, agent reasoning improvements — work that benefits enormously from GPU compute and synthetic datasets but does not require them to demonstrate end-to-end. We can ship working AI features into the existing platform from week one of the cohort and iterate against synthetic data and FCA feedback throughout. We are not pre-product, where the Sandbox would have nothing to test against, and we are not so far post-launch that regulatory exposure forecloses safe experimentation. We sit at the inflection point the cohort is explicitly designed to support.

### Has the applicant clearly articulated the support, tooling, data, and/or compute required?

**Compute:** GPU-backed virtual machines for fine-tuning small open-weight LLMs on UK financial planning corpora and for training probabilistic retirement and goal-achievement models. **Data:** synthetic UK consumer trajectories spanning life stages — earnings, pensions, ISA contributions, property purchases, inheritance events — to validate model behaviour across realistic edge cases without exposing real users; plus synthetic transaction streams for fraud and vulnerability prototypes. **Tooling:** privacy-enhancing technologies (differential privacy libraries, synthetic data generators) and integrated APIs for evaluation harnesses. **Regulatory support:** a dedicated FCA coordinator to advise on Consumer Duty alignment, model explainability, and the boundary between guidance and personal recommendation as we scope the AI assistant. **Mentorship:** industry experts in regulatory technology, AI model risk, and large language model safety to pressure-test our approach before any live-market exposure. Each requirement maps directly onto the cohort's published provisions.

### Is the proposed testing plan realistic and achievable within the cohort timeframe?

Across five months we propose three evaluation tracks run in parallel. (1) **Conversational AI assistant:** deploy a large language model–backed planning copilot grounded in the user's own data, evaluated against held-out synthetic personas and a rubric covering accuracy, regulatory alignment, and Consumer Duty principles. (2) **Probabilistic retirement modelling:** train and back-test goal-achievement and retirement adequacy models on synthetic trajectories, comparing against the deterministic baseline already in production. (3) **Anomaly and vulnerability detection:** prototype a fraud-signal and vulnerability-flagging layer on synthetic transaction data, with the FCA coordinator advising on flag thresholds and fairness. Each track has weekly engineering milestones, monthly checkpoints with the FCA coordinator, and a defined Demo Day deliverable. Scope is bounded: we are testing AI components inside the Sandbox, not migrating live users. The plan is achievable with the team and infrastructure already in place.

### Does the applicant appear to have the capacity and capability to participate effectively in the programme?

Fynla is built and shipped. The codebase comprises 706 Vue components, 258 PHP services, 106 controllers, 102 models, and 940+ automated tests, all delivered to a production-grade v1.0 release. The team has demonstrated end-to-end capability: domain modelling, UK tax law implementation, AI agent architecture, mobile delivery (Capacitor iOS with biometric authentication), payment integration, deployment automation, and ongoing operational support. We have the technical capacity to absorb GPU-based experimentation alongside production work, the regulatory awareness to engage substantively with FCA feedback, and the engineering discipline (PSR-12, automated testing, design system governance, full audit logging, structured logging) that the Sandbox's regulatory environment expects. We are committing dedicated engineering time to the cohort and will treat the FCA coordinator's input as a first-class input into our roadmap rather than as an external add-on or reporting overhead.

---

## 4. Potential impact

### Does the solution have credible potential to deliver positive outcomes for consumers, firms, or markets?

The UK advice gap is well-documented: approximately 13.1 million UK adults with investable assets between £50,000 and £5 million do not receive financial advice, yet face the most consequential planning decisions of their lives — first home, first pension, first life cover, estate planning around the family home. Fynla's thesis is that an AI-enabled multi-agent system can deliver coherent, holistic guidance to that under-served majority at a price point reachable by ordinary UK consumers. **For consumers:** better-informed decisions and fewer costly mistakes around tax wrappers, mortgages, and inheritance. **For firms:** a high-quality, well-prepared referral pipeline of consumers whose needs grow beyond the platform, plus a potential embedded planning capability for advisers, lenders, insurers, and pension providers. **For markets:** raised baseline financial literacy and a working template for AI-enabled holistic guidance under Consumer Duty. The Consumer Duty framework explicitly contemplates this kind of outcomes-driven service.

### Are the intended outcomes clearly articulated, realistic, and capable of being assessed?

We will assess outcomes along three concrete dimensions, each with a defined baseline and stretch target agreed with the FCA coordinator at intake. (1) **Decision quality:** blind comparisons of Fynla AI guidance against a reference panel of qualified financial planners on a battery of synthetic UK personas, evaluated on technical correctness, completeness, and Consumer Duty alignment. (2) **Coverage:** the proportion of a user's planning surface (the seven modules) for which the AI surfaces actionable, personalised guidance, versus the deterministic baseline. (3) **Safety:** the rate of hallucination, regulated-advice-boundary violations, and inappropriate recommendations on adversarial test cases, measured continuously across the cohort. Each dimension carries a quantitative metric and a documented evaluation harness. We are deliberately avoiding fuzzy outcome claims: the goal is evidence the FCA, future commercial partners, and we ourselves can act on.

### Is there a credible pathway to further development, adoption, or live-market testing following the Sandbox?

Post-Sandbox, the pathway has three stages. First, a controlled rollout of validated AI features to existing Fynla users in production, governed by the model-risk and Consumer Duty frameworks established during the cohort. Second, a structured engagement with the FCA on the appropriate authorisation perimeter for an AI-enabled holistic guidance product — we anticipate this is a substantive conversation about where guidance ends and personal recommendation begins, not a tick-box exercise. Third, partnership conversations with regulated firms (advisers, lenders, insurers, pension providers) where Fynla's AI layer can sit either as a consumer-facing front end or as an embedded planning capability inside an existing regulated proposition. The Sandbox provides the technical evidence and regulatory dialogue all three stages depend on; without the cohort, each stage becomes substantially slower, more speculative, and riskier to attempt.

---

## 5. Need for support

### Is there a clear need for the Sandbox environment, rather than more general innovation support?

Yes — our requirements are specifically regulatory and infrastructural, not generic startup support. We do not need market validation; we have a live UK product. We do not need help raising capital; that is a separate workstream. What we cannot easily obtain elsewhere are: (a) realistic synthetic UK consumer datasets at the scale and fidelity required to train and stress-test AI models without exposing real users — the Sandbox's curated datasets across consumer behaviour, fraud, Environmental, Social and Governance (ESG) analytics, and digital identity are uniquely useful; (b) GPU-scale compute on demand for model training and evaluation against those datasets; (c) sustained regulatory dialogue with an FCA coordinator focused specifically on AI model risk, explainability, and the guidance/advice boundary. General accelerators and innovation programmes provide none of these. The Supercharged Sandbox does, which is why it is the right vehicle for this stage of Fynla's development.

### Does the applicant have, or can they reasonably obtain, the data required for the proposed work, including through the data available on the platform?

Our working assumption is that the Sandbox-provided synthetic datasets — consumer behaviour modelling, fraud detection, ESG analytics, and digital identity — meet the bulk of our training and evaluation needs. We will supplement with our own deterministic test fixtures: six richly modelled preview personas (young family, peak earners, entrepreneur, young saver, retired couple, student) already encoded in the platform with full multi-module financial profiles, suitable for synthetic-style evaluation across UK life stages. For domains where Sandbox datasets do not extend (for example UK-specific Inheritance Tax planning across joint property ownership and trust structures), we can generate additional synthetic trajectories using Fynla's existing tax engine as a ground-truth oracle. We do not need access to real customer data at any point during the cohort; the synthetic-only path is intentional and aligns with our privacy-by-design posture.

### Is the support required by the applicant within the scope and operational bounds of the programme?

Yes. Our requirements map cleanly onto the cohort's published offering: GPU-backed virtual machines (for fine-tuning and evaluation), curated synthetic datasets (for training and back-testing), FCA coordinator engagement (treated as a first-class roadmap input), industry mentorship in regulatory technology, AI model risk, and large language model safety (to pressure-test our approach), and the Demo Day showcase (to present validated AI components to regulators and ecosystem stakeholders). We are not asking for bespoke regulatory rulings, live customer access, FCA authorisation as a programme outcome, or hardware/data outside the published scope. The cohort, as advertised, is precisely what we need at this stage — and we believe Fynla offers the cohort a substantive, advanced, UK-centred holistic planning use case that complements its existing alumni base.

---

## Notes for the user before submitting

- **Word counts** sit between roughly 140 and 155 words per answer. Trim to whatever the live FCA portal field limits actually allow.
- **AI angle is led hard throughout** — the agent architecture, AI Chat module, three Sandbox tracks (LLM copilot, probabilistic retirement modelling, anomaly detection) are repeated as the connective tissue across all five sections, per your direction.
- **No user/MAU numbers claimed** anywhere. The product evidence is build-side: 706 components, 258 services, 940+ tests, v1.0 in production at fynla.org.
- **Acronyms** are spelled out per Fynla house rules (Annual Allowance, Money Purchase Annual Allowance, Nil Rate Band, large language model). ISA is left abbreviated per the rule.
- **Two factual claims worth double-checking** before you submit:
  1. The "13.1 million UK adults / £50k–£5m investable assets" advice gap figure — verify against the most recent FCA Financial Lives data (or whichever industry source you intend to cite) and confirm the survey year before submission.
  2. Whether you want to commit explicitly to "FCA authorisation pathway" language at this stage, or soften to "appropriate regulatory perimeter to be determined during the cohort". I have left it leaning forward; soften if you would rather not pre-commit.
- Final pass for the live form: paste each answer into the matching portal field, then re-read each one *standalone* — assessors will read them in isolation, not together, so any cross-references between sections need to be resolvable from a single answer.
