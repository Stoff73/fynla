# Fynla — FCA AI Supercharged Sandbox (Cohort 2) Application

**Applicant:** Fynla — UK holistic personal financial planning platform
**Site:** https://fynla.org
**Programme:** AI Supercharged Sandbox, Cohort 2 (FCA × NVIDIA — five-month TechSprint-style cohort, Demo Day November)
**Compiled:** May 2026

**Founders / team (three):**
- **Chris Slater-Jones** — financial planner; sole coder, back-end builder, system designer
- **Az** — front-end design (look and feel)
- **Brett (Phailanx)** — quality assurance

> Companion reference document with the long-form 5-criteria evaluation draft: `FCA-Supercharged-Sandbox-Application-Draft.md`.

---

## 1. Describe your solution. *(max 50 words)*

Fynla is a UK personal financial planning platform powered by a multi-agent AI architecture: nine domain agents (Protection, Savings, Investment, Retirement, Estate, Goals) coordinated by a reasoning layer that sees the user's full balance sheet — bringing holistic, Consumer Duty-aligned guidance to consumers priced out of regulated advice.

*(47 words)*

---

## 2. What specific problem are you solving and why is it important? *(max 300 words)*

**The problem: the UK advice gap.**

Approximately 13.1 million UK adults with investable assets between £50,000 and £5 million do not receive financial advice. They face the most consequential planning decisions of their lives — first home, first pension, first life cover, estate planning around the family home — without coordinated professional guidance. Paying £1,000+ for a regulated planner is not realistic for most, and the alternative — a fragmented landscape of single-purpose tools (pension calculators, ISA platforms, mortgage comparison sites, will-writing services) — leaves the integration work to the consumer.

That integration is exactly where the costly mistakes happen. Should a marginal £200 a month go to ISA, pension, or mortgage overpayment? Does property held jointly with a spouse use both Residence Nil Rate Bands or only one? Is existing life cover sufficient if the mortgage is repaid early? These are domain-spanning questions, and no single-domain tool can answer them — but over a lifetime they determine tens or hundreds of thousands of pounds of outcome difference per household.

**Why it matters.** First, *scale*: 13.1 million is roughly a quarter of the UK adult population, and the squeeze tightens as Defined Benefit pensions disappear and individual responsibility for retirement adequacy grows. Second, *Consumer Duty*: the FCA's outcomes-focused framework explicitly contemplates consumers making informed decisions, but the infrastructure to support that for non-advised consumers does not yet exist at scale. Third, *vulnerability*: those least equipped to navigate fragmented tools — older consumers, the financially anxious, those with low numeracy — are precisely those most exposed to the cost of getting these decisions wrong.

Fynla solves this by collapsing the seven planning domains into one coherent, AI-mediated model of the user, making holistic guidance economically deliverable to mass-market UK consumers.

*(295 words)*

---

## 3. What outcomes do you expect your solution to deliver, and for whom? *(max 300 words)*

**Consumers (primary beneficiaries — the 13.1 million UK adults in the advice gap):** better-informed decisions across their full financial life, with the integration that single-purpose tools cannot provide. Concrete outcomes include appropriate sequencing of tax wrappers (ISA, Lifetime ISA, pension) for the user's circumstances; full use of Inheritance Tax allowances including both Residence Nil Rate Bands where applicable; protection cover sized to actual exposure rather than rule-of-thumb defaults; and earlier identification of retirement adequacy shortfalls while there is still time to act. Vulnerable consumers — older adults, the financially anxious, those with low numeracy — gain access to guidance previously available only at adviser-grade price points.

**The FCA and the wider regulatory community:** a working, evaluated template for AI-enabled holistic guidance under Consumer Duty — including the model-risk controls, explainability mechanisms, and guidance/advice boundary controls developed during the cohort. We expect Sandbox outputs (evaluation harnesses, adversarial test results, Consumer Duty alignment evidence) to serve as useful precedents for other applicants and for FCA thinking on AI in retail finance.

**Regulated firms (advisers, lenders, insurers, pension providers):** a referral pipeline of well-prepared consumers whose needs have grown beyond a non-advised platform; an embedded planning capability they can integrate into their own propositions; and a reduction in mis-selling and complaint exposure that flows from better-informed customers.

**The wider market:** a raised baseline of UK financial literacy and a meaningful contribution to closing the advice gap — measured in coverage, not marketing claims. We commit to publishing aggregate, anonymised outcome metrics post-cohort so the impact can be independently assessed by the FCA and the broader ecosystem.

*(266 words)*

---

## 4. What role does AI play in helping you to solve this problem? *(max 300 words)*

AI is the enabling mechanism for the entire proposition, not a feature bolted onto a deterministic platform. Without AI, the economics of delivering holistic, personalised guidance across seven domains to mass-market consumers do not work — which is precisely why the advice gap exists. AI is what makes the proposition viable.

It plays four structural roles.

**Multi-agent reasoning across domains.** Fynla is built around nine specialised AI agents — one per advice domain (Protection, Savings, Investment, Retirement, Estate, Goals) plus a CoordinatingAgent — that reason about a user's full balance sheet and negotiate trade-offs between them. A flat ruleset cannot capture that reasoning; an AI agent system can.

**A domain-trained LLM for UK regulated advice.** A large language model fine-tuned specifically for UK financial planning — trained across the regulatory advice landscape (Conduct of Business Sourcebook, Consumer Duty, Senior Managers and Certification Regime, suitability, investment management, pension transfers, Inheritance Tax, protection) with built-in regulatory-oversight, compliance-checking, and professionalism layers — produces traceable, explainable guidance constrained to the guidance/advice boundary.

**Behavioural AI integrated into the recommendation engine.** A behavioural layer feeds disengagement risk, life-event likelihood, and predicted nudge response into Fynla's existing recommendation engine, so the prioritisation, timing, and tone of recommendations adapt to each user — additive, with the engine's underlying logic and audit trail preserved.

**Anomaly and vulnerability detection.** AI surfaces fraud signals and vulnerability indicators (financial distress, cognitive decline, coercion) from user activity, calibrated with the FCA against Consumer Duty and the vulnerability framework.

In short: AI turns Fynla from a calculator into a planner. Without it, the advice gap remains structural. With it — validated rigorously inside the Sandbox — it becomes addressable.

*(approximately 268 words)*

---

## 5. What is novel or distinctive about your approach compared with existing market solutions or internal alternatives? *(max 300 words)*

**Compared with existing market solutions.** UK consumer fintech today clusters into three patterns: single-domain tools (pension calculators, ISA platforms, mortgage comparison sites, will-writing services) that solve one problem in isolation; open-banking aggregators that surface transactions but offer little forward-looking planning; and traditional regulated advice, which is high-quality but priced out of reach for most consumers. None addresses the integration problem — the fact that every domain decision affects every other. Fynla differs on three axes simultaneously: scope (seven integrated domains under one model of the user), architecture (multi-agent reasoning rather than flat rules or a single chatbot), and accessibility (designed for mass-market price points from day one, not a stripped-down tier of an adviser product).

**Compared with internal alternatives we could have pursued.** We considered three. (1) A single large language model wrapped over a tax engine — rejected because it cannot reason rigorously across competing domain constraints and offers no auditable trace of how a recommendation was reached. (2) A flat rules engine without AI — rejected because the combinatorial complexity of UK financial planning makes hand-coded rules unmaintainable and unable to handle natural-language interaction. (3) A single-domain product extended later — rejected because it would have re-created the advice-gap problem we exist to solve.

The chosen approach — a multi-agent system with a coordinating layer, a deterministic UK tax engine as ground truth, and an AI Chat surface — combines AI reasoning where it adds value (cross-domain trade-offs, conversational guidance, probabilistic projections) with deterministic logic where it must be exact (tax calculations, allowance limits, regulatory constraints). That hybrid is what is novel and distinctive: AI for judgment, code for arithmetic, with explainability built in for FCA Consumer Duty.

*(283 words)*

---

## 6. What specifically do you want to develop, test, validate, or demonstrate during the Supercharged Sandbox?

Three workstreams over the five-month cohort, each with Demo Day deliverables.

**Track 1 — A domain-trained LLM for UK financial planning.**
*Develop:* a large language model fine-tuned for UK financial planning, trained across UK regulatory advice — Conduct of Business Sourcebook, Consumer Duty, Senior Managers and Certification Regime, suitability and appropriateness, investment management, pension transfers, Inheritance Tax, protection advice — with regulatory-oversight, compliance-checking, and professionalism layers. Grounded in the user's profile, the seven domain agents, and the UK tax engine; constrained to the guidance/advice boundary.
*Test:* held-out synthetic personas with adversarial probes for hallucination, advice-boundary breaches, and Consumer Duty failures.
*Validate:* blind comparisons with qualified UK planners on correctness, regulatory alignment, and professionalism; compliance layer validated against worked FCA case studies.
*Demonstrate:* live cross-domain planning conversations with traceable reasoning, in-line compliance trace, and an auditable record for FCA inspection.

**Track 2 — Behavioural AI integrated into Fynla's recommendation engine.**
*Develop:* a behavioural AI layer feeding three signals — disengagement risk, life-event likelihood, predicted nudge response — into Fynla's existing recommendation engine, so prioritisation, timing, and tone adapt per user. Additive: engine logic and audit trail preserved. Trained on the Sandbox's consumer behaviour datasets.
*Test:* predictive accuracy on held-out trajectories; recommendation lift versus the deterministic-only baseline.
*Validate:* against Consumer Duty, with the FCA coordinator advising on the personalisation/dark-patterns boundary.
*Demonstrate:* live recommendations reordered and re-timed by the behavioural layer, with contributing signals visible in the audit trail.

**Track 3 — Anomaly and vulnerability detection.**
*Develop:* an AI detection layer surfacing fraud and vulnerability indicators (financial distress, cognitive decline, coercion) from synthetic transaction streams. *Test:* false-positive/negative rates against labelled adversarial cases. *Validate:* with the FCA coordinator, calibrating thresholds against Consumer Duty and the vulnerability framework. *Demonstrate:* a flagging-and-intervention workflow that respects user agency.

**Cross-cutting:** a reusable model-risk and explainability framework showing how each AI component meets FCA expectations.

*(299 words)*

---

## 7. Why is the Supercharged Sandbox needed for this work?

The Sandbox is uniquely required for this work because no other venue solves the three blockers Fynla faces simultaneously: data, compute, and regulatory dialogue. Each track depends on at least two of the three.

**Synthetic UK consumer data at scale.** Track 1 (the domain-trained large language model) and Track 2 (the behavioural AI feeding the recommendation engine) both require large volumes of realistic UK financial trajectories — earnings, pensions, ISAs, property purchases, life events — to train and evaluate against. We cannot generate this at sufficient fidelity alone, and we will not train AI models against live user data. The Sandbox's curated synthetic datasets across consumer behaviour, fraud, and digital identity are unique to the cohort and address this constraint.

**GPU compute for AI training and evaluation.** Fine-tuning a domain-specific language model and training behavioural models is compute-intensive. Renting commercial GPUs is possible but expensive at this stage, lacks integrated tooling, and produces no regulatory dialogue alongside it. The Sandbox's NVIDIA-backed compute and integrated APIs are the right environment to run all three tracks in parallel.

**Sustained FCA dialogue at development stage.** The questions we need answered — where the guidance/advice boundary sits for an AI assistant, how Consumer Duty applies to AI-driven personalisation, what model-risk framework satisfies the FCA — are not resolvable through public guidance. Most FCA engagement happens through authorisation, which presupposes the work is finished. The Sandbox is uniquely a development-stage venue for regulatory dialogue, and the dedicated FCA coordinator is irreplaceable.

**Why all three together.** General accelerators offer none of these. Cloud GPU rental offers compute alone. Industry regulatory-technology mentorship offers dialogue but no compute or data. Only the Supercharged Sandbox combines all three with mentorship, Demo Day exposure, and an alumni network that supports the post-cohort path.

*(289 words)*

---

## 8. Why is now the right time for your organisation to undertake this work?

Three things have lined up at the same time, and that timing is unlikely to recur. Acting now means contributing to the framework as it forms; acting later means complying with whatever it becomes.

**Fynla is at the right product stage — and only just.** Fynla v1.0 — built in six months — is production-ready as a deterministic financial planning engine, with all seven domains, the UK tax engine, and the multi-agent architecture. That deterministic foundation is the precondition for layering AI on top — without it, an AI assistant has nothing to ground itself in. Twelve months from now, the AI layer should be live, and doing regulatory and validation work after public exposure is materially harder.

**AI capability has crossed the threshold this work requires.** Domain-specific fine-tuning of large language models produces useful, controllable outputs only over the past 12–18 months. Reasoning across structured data with explainable traces is now within reach for a small team, and behavioural AI in recommendation engines is mature. None of this would have been viable at the standard FCA Consumer Duty requires three years ago.

**UK regulation is being shaped now.** Consumer Duty came into force in 2023, the FCA's *AI Update* was published in April 2024, and the Supercharged Sandbox is in only its second cohort. The framework for AI in UK retail finance is being written now. Participating in the cohort means contributing to that framework rather than reacting to it. For a UK-only, AI-first, holistic-planning proposition, that influence window is uniquely valuable — and time-limited.

The advice gap is widening as Defined Benefit pensions disappear and Consumer Duty raises expectations. The 13.1 million underserved are not getting smaller. Other AI fintech entrants are emerging. Product, technical, and regulatory readiness are aligned now — and the cohort is the venue that brings all three together.

*(298 words)*

---

## 9. What data does your project require?

**Type of data needed.** Three categories. (1) UK regulatory text and guidance for the domain-trained large language model — Conduct of Business Sourcebook, Consumer Duty handbook, Senior Managers and Certification Regime, pension transfer rules, suitability rules, FCA Final Notices for compliance examples; all public. (2) Realistic UK consumer behavioural trajectories for the behavioural AI — engagement patterns, life-event sequences, nudge responses across UK life stages. (3) Synthetic transaction streams and labelled vulnerability indicators for anomaly detection — fraud signals, financial distress, coercion, cognitive decline markers.

**Existing access.** We have full access to category (1) — public regulatory text — and our own preview-persona fixtures (young family, peak earners, entrepreneur, young saver, retired couple, student) supplying small-scale synthetic profiles. Categories (2) and (3) depend on Sandbox-provided datasets; we have no commercial alternative at the required fidelity and scale.

**Personal or confidential information.** None of the cohort work uses real personal data. We will not train AI models against live Fynla user data at any point. This is a deliberate privacy-by-design posture.

**Synthetic or anonymised alternatives.** Synthetic data is the intended path throughout. The Sandbox's curated synthetic datasets are the primary inputs for Tracks 2 and 3. Where Sandbox data does not extend (e.g. UK-specific Inheritance Tax planning across joint property and trusts), we generate additional synthetic trajectories using Fynla's existing tax engine as a ground-truth oracle.

**Innovation Platform datasets identified.** Several Innovation Platform ecosystems map directly onto our work — **Wealth**, **Customer Experience**, **RegTech**, **Generative AI**, **Fraud**, **Consumer Lending**, **Insurance**, **Open Banking**, and **ESG** all contain datasets and resources relevant to one or more of the three tracks. We will finalise the specific datasets, fields, and access scope with the FCA coordinator during onboarding.

*(approximately 280 words)*

---

## 10. What compute resources do you expect to need?

**Model type and approximate size.** A single foundation model serves all three tracks: an open-weight base with a minimum of 70B parameters (e.g. Llama 3 70B, or equivalent) fine-tuned for UK financial planning, regulatory advice, and compliance reasoning. The 70B floor is required for the quality bar of FCA Consumer Duty-aligned outputs; smaller models do not produce sufficiently controllable, explainable guidance at the standard the cohort requires. The same fine-tuned model is then applied across the tracks — Track 1 (conversational planning copilot), Track 2 (behavioural AI feeding the recommendation engine), Track 3 (anomaly and vulnerability detection) — via track-specific prompting, retrieval context, and lightweight scoring layers. We train one model, not three.

**Activity mix.** Mostly fine-tuning and inference, not training from scratch.
- Single fine-tuning run: parameter-efficient fine-tuning (LoRA / QLoRA) on the 70B base, with full supervised fine-tuning for selected configurations.
- Track-specific inference: extensive evaluation across all three tracks — synthetic personas and adversarial probes (Track 1), held-out behavioural trajectories (Track 2), labelled adversarial cases (Track 3).
- Lightweight auxiliary scoring layers (embeddings, gradient-boosted classifiers) on top of the model's outputs where speed or cost requires.

**Expected GPU requirements.** NVIDIA A100 80GB or H100 class hardware. Single fine-tuning track at 70B: 4–8 GPUs for LoRA / QLoRA, 8–16 GPUs for full supervised fine-tuning runs. Inference and evaluation across all three tracks runs against the same fine-tuned weights. Peak concurrent demand during the cohort: roughly 8–16 GPUs during training pushes; steady-state 4–8 GPUs for daily experimentation and parallel evaluation, plus 1–2 reserved for live demonstration during Demo Day.

**Post-cohort inference.** Production inference will run via a managed API rather than self-hosted infrastructure, so security, scaling, observability, and uptime are covered by the provider's controls. The Sandbox compute is therefore needed for fine-tuning and evaluation only — not for serving the model live.

*(approximately 295 words)*

---

## 11. What development tooling are you likely to require?

The tooling stack splits into things the Sandbox is expected to provide and things we bring or integrate.

**From the Sandbox / NVIDIA stack (assumed available):**
- GPU drivers, CUDA, cuDNN, NCCL for distributed training.
- NVIDIA NeMo framework (or equivalent) for fine-tuning at scale.
- Containerised compute environments (NGC catalogue images, Docker).
- Optionally NVIDIA NIM microservices for served inference during evaluation.
- Privacy-enhancing tooling provided by the platform.

**Foundation model and training:**
- Hugging Face *transformers*, *PEFT* (for LoRA / QLoRA), *TRL*, *accelerate*.
- PyTorch with DeepSpeed / FSDP for distributed fine-tuning of the 70B base.
- vLLM or TGI for batched inference during evaluation runs.

**Data engineering:**
- pandas, Polars, DuckDB for synthetic-dataset preprocessing.
- Differential privacy libraries (Opacus, OpenDP) where applicable.
- Tokenisation utilities aligned to the chosen base model.

**Evaluation, observability, and audit:**
- *lm-evaluation-harness* and custom rubrics for held-out persona scoring.
- Adversarial probing tools (garak, PyRIT, Promptfoo) for hallucination, advice-boundary breaches, and Consumer Duty failure modes.
- Trace logging via LangSmith or Langfuse to produce the explainability and compliance-trace evidence an FCA assessor expects.
- Weights & Biases or MLflow for experiment tracking.

**Behavioural AI auxiliary layer:**
- A vector database (Postgres pgvector, Qdrant, or equivalent) for retrieval.
- XGBoost or LightGBM for gradient-boosted scoring layers on top of the model's outputs.
- Embedding models (potentially fine-tuned from the same 70B base).

**Integration with Fynla's existing stack:**
- A thin Python service layer that the existing Laravel + Vue.js application calls into via API for both inference and evaluation feedback, so the Sandbox work plugs back into the production codebase rather than living in isolation.

Where any of the above is already provided as a managed Sandbox capability, we will use that in preference to bringing our own.

*(approximately 250 words)*

---

## 12. Are there any specific blockers, dependencies, or risks that could prevent successful delivery of your solution (including regulatory)?

We have considered five categories of risk. Each is mitigated but not eliminated.

**Regulatory risk.** The biggest open question is where the FCA places the line between guidance and personal recommendation for an AI assistant grounded in a user's own financial data. If the line moves mid-cohort, parts of the conversational planning copilot may need re-scoping. *Mitigation:* this is what the Sandbox FCA coordinator is for — early, continuous engagement so we adapt the design rather than rework the build.

**Synthetic data fitness.** Sandbox datasets may not cover all UK-specific edge cases — trust structures, joint property with split Residence Nil Rate Bands, defined-benefit transfers, niche estate scenarios. *Mitigation:* where coverage is thin we generate supplementary synthetic trajectories using Fynla's existing tax engine as a ground-truth oracle, and we will agree dataset gaps with the coordinator at intake.

**Foundation model licensing and hosting.** Llama 3 70B (and most 70B+ open-weight alternatives) carry community licences with usage thresholds and API-hosting constraints. *Mitigation:* we will validate the licence terms for the chosen base model and chosen API provider before fine-tuning begins, including the path for serving custom-fine-tuned weights post-cohort.

**Capacity risk.** Running Sandbox work alongside production maintenance creates execution-bandwidth risk. *Mitigation:* bounded deliverables per track and an explicit cross-cutting framework deliverable, not open-ended ambition. We have committed dedicated time and treat the FCA coordinator's input as a first-class roadmap input.

**AI safety and Consumer Duty failure modes.** Hallucination, advice-boundary breaches, vulnerability false positives, and bias are real risks for any large language model–driven retail finance product. *Mitigation:* each is an explicit evaluation track in our cohort plan, with adversarial probing, FCA coordinator calibration, and a published model-risk and explainability framework as deliverables.

*(approximately 290 words)*

---

## 13. Please outline your expected milestones across the cohort period.

Five months, three parallel tracks, one fine-tuned 70B model, four FCA coordinator checkpoints. Each month closes with a specific deliverable.

**Month 1 — Onboarding and foundations.** Sandbox onboarding (GPU access, dataset access, technical environment). FCA coordinator intake meeting; agree success metrics, evaluation rubric, and Consumer Duty alignment criteria. Curate the UK regulatory training corpus (Conduct of Business Sourcebook, Consumer Duty handbook, suitability rules). Validate base model choice. Stand up the LoRA / QLoRA fine-tuning pipeline and evaluation harness. *Deliverable:* baseline-evaluation report against deterministic Fynla outputs.

**Month 2 — First fine-tuning pass and Track 1 prototype.** Complete first parameter-efficient fine-tuning run. Working prototype of the conversational planning copilot (Track 1) answering cross-domain questions over synthetic personas. Initial adversarial probing for hallucination and advice-boundary breaches. *Deliverable:* Track 1 prototype and FCA coordinator review.

**Month 3 — Behavioural AI and anomaly detection prototypes.** Stand up the behavioural AI layer feeding Fynla's existing recommendation engine (Track 2). Prototype the anomaly and vulnerability detection layer over synthetic transaction streams (Track 3). Iterate Track 1 on Month 2 feedback. Mid-cohort FCA coordinator checkpoint. *Deliverable:* working prototypes of all three tracks running off the same fine-tuned model.

**Month 4 — Validation and hardening.** Blind comparisons of Track 1 outputs against a panel of qualified UK financial planners. Track 2 recommendation-lift evaluation against the deterministic-only baseline. Track 3 false-positive/negative calibration with the FCA coordinator. Stress testing for adversarial probes, edge cases, and Consumer Duty alignment. *Deliverable:* validation report and first draft of the model-risk and explainability framework.

**Month 5 — Demo Day preparation and showcase.** Final tuning pass. Build live demo workflows. Finalise the model-risk and explainability framework. Demo Day in November: present three integrated AI components — conversational copilot, behavioural AI, and anomaly detection — to FCA, NVIDIA, sponsors, and ecosystem stakeholders. Publish the post-cohort pathway.

*(approximately 295 words)*

---

## 14. What specific outcomes do you aim to achieve by the end of the cohort?

By the end of the cohort, we aim to have produced five concrete outcomes — technical artefacts, validation evidence, and regulatory deliverables that together justify a controlled production rollout post-Sandbox.

**A fine-tuned 70B foundation model for UK financial planning.** Trained on UK regulatory advice (Conduct of Business Sourcebook, Consumer Duty, Senior Managers and Certification Regime, suitability rules, pension transfers, Inheritance Tax, protection) with regulatory-oversight, compliance-checking, and professionalism layers. The model serves as the single AI engine across all three tracks.

**Three working AI components.** A conversational planning copilot reasoning across the seven domains; a behavioural AI layer feeding Fynla's existing recommendation engine; an anomaly and vulnerability detection layer over synthetic transaction streams. All three integrated, demonstrable, and traceable.

**Quantified validation evidence.** Decision-quality scores from blind comparisons against a panel of qualified UK financial planners (correctness, regulatory alignment, professionalism); recommendation-lift metrics versus the deterministic-only baseline; false-positive/negative rates for vulnerability and fraud detection; Consumer Duty alignment evidence on adversarial test cases. Numbers, not narrative.

**A model-risk and explainability framework.** Documenting how each AI component meets FCA expectations — controls, evaluation rubrics, audit trails, and explainability mechanisms developed during the cohort. Published as the cross-cutting deliverable and as precedent for future cohorts.

**A clear post-cohort pathway.** Documented plan for controlled production rollout to existing Fynla users, with the regulatory perimeter for an AI-enabled holistic guidance product agreed (or scoped) with the FCA coordinator.

By Demo Day we present a working, validated, regulator-aligned AI layer ready to deploy back into Fynla's production product, with quantified evidence to support that decision.

*(approximately 250 words)*

---

## 15. Does your team have the technical and operational capacity to participate fully during the cohort? *(max 250 words)*

Yes. Fynla is a three-founder team with deliberately complementary roles, and the cohort scope is matched to that capacity.

**Chris Slater-Jones — domain expertise, full-stack build, system design.** Chris is Fynla's financial planner — the source of the domain knowledge encoded in the seven modules and tax engine — and is also the sole coder, back-end builder, and system architect. The combination is unusual and is what makes a project at Fynla's scope possible from a small team: the same person who specifies the regulatory logic also implements it, removing the typical handoff cost between domain expert and developer. Chris will lead all Sandbox technical work, FCA coordinator engagement, and Demo Day presentation.

**Az — front-end design.** Az is Fynla's front-end designer and owns the look-and-feel of the production product. Within the cohort Az will own the user-facing surfaces of the AI components built in the cohort, ensuring outputs land in a product context rather than a notebook.

**Brett — quality assurance.** Brett owns QA across Fynla and will lead test coverage and acceptance verification for the Sandbox deliverables, including the adversarial probes and Consumer Duty alignment evaluation.

**Operational capacity.** Six months from concept to a production-ready v1.0 (706 Vue components, 258 PHP services, 940+ automated tests, iOS application) is concrete evidence of throughput. The cohort plan has been deliberately scoped — one fine-tuned model serving three tracks, bounded monthly deliverables, an explicit cross-cutting framework — so the workload fits a small, focused team operating at proven velocity.

*(approximately 240 words)*

---

## 16. How would participation in the Sandbox help your organisation progress after the cohort ends?

The cohort produces both immediate post-cohort moves and longer-arc benefits.

**Immediate: a validated AI layer ready to ship.** The fine-tuned 70B model, the three components (conversational copilot, behavioural AI in the recommendation engine, anomaly detection), and the model-risk and explainability framework are all designed to deploy back into Fynla's live product. The Sandbox makes that deployment safe — without the cohort's evaluation evidence and FCA-aligned controls, a controlled rollout to real users would carry materially more risk and take much longer.

**Regulatory pathway.** A working relationship with the FCA at development stage (rather than first contact at authorisation) and a documented view of where the guidance/advice perimeter sits for an AI-enabled holistic guidance product. This unblocks roadmap decisions otherwise speculative for years.

**Commercial credibility.** Being a Supercharged Sandbox alumnus opens conversations Fynla cannot otherwise reach: investors looking for regulator-engaged AI fintech, partner firms (advisers, lenders, insurers, pension providers) considering embedded AI planning, and policy stakeholders shaping the UK AI-in-finance narrative.

**Partnership pipeline.** Demo Day exposure to regulated firms creates a natural funnel for the embedded-planning use case. The same AI components built in the cohort can either sit behind Fynla's consumer surface or be licensed to a regulated partner — both pathways become viable rather than hypothetical.

**Continued ecosystem engagement.** Alumni network access, the cohort mentor pool, and the Fynla-published model-risk framework become a continuing platform: future cohorts to mentor, FCA AI working groups to contribute to, and a positioning that compounds over time rather than ending at Demo Day.

The cumulative effect: the cohort accelerates Fynla from credible plan to validated execution, with regulatory and commercial pathways open at the end of it — a shift hard to engineer any other way.

*(approximately 270 words)*

---

## Pre-submission checklist

Items to verify or update before submitting:

1. **The 13.1m / £50k–£5m advice gap stat** — verify against the most recent FCA Financial Lives data (or whichever industry source you intend to cite) and confirm the survey year.
2. **FCA AI Update citation** — confirm the exact title and date the portal expects (the April 2024 *AI Update* is the intended reference; check whether the assessor wants a URL).
3. **fynla.org/about page** — currently misrepresents team roles. Update to match the team description in §15 (Chris = financial planner / sole coder / system designer; Az = front-end design; Brett = QA) before an assessor clicks through.
4. **Surnames for Az and Brett** — if any portal field requires full names, add them.
5. **Innovation Platform datasets** — paragraph in §9 names the broad ecosystems (Wealth, Customer Experience, RegTech, Generative AI, Fraud, Consumer Lending, Insurance, Open Banking, ESG); finalise the specific datasets with the FCA coordinator at onboarding.
6. **Word counts** — every answer is at or under its stated limit; if portal field limits are tighter, the document above is easy to trim further.
7. **Foundation-model API hosting** — flagged in §12 (risks): confirm a 70B+ custom-fine-tuned weights API path (AWS Bedrock custom model import for Llama 3 70B, Together.ai, Fireworks, Replicate, or similar) before fine-tuning begins.
8. **Cohort calendar alignment** — if the FCA's published cohort schedule uses different month/sprint labels than §13's "Month 1–5", re-align the milestones to the official cadence before submitting.

---

## Appendix — Companion documents

- **Long-form 5-criteria evaluation draft** (16 sub-questions across Relevance, Innovation, Feasibility, Impact, Need): `FCA-Supercharged-Sandbox-Application-Draft.md` in the same folder.
