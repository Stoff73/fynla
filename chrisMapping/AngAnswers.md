# Answers for Ang — evidence-based

**Stamp:** commit `a7c13608a`, 2026-09-22, 10:05 BST.
**Method:** every number below was either counted in the production database this morning (read-only queries, aggregate only, no personal data) or read from a named file. Where a figure in the question could not be found anywhere in the codebase, the vault or the workforce records, it says so rather than guessing.

**Headline caveat before the answers.** Three figures in the questions, "48 registered users", "99%" and "90%", do not appear in the repository, the fynlaBrain vault, the workforce board, the daily briefs or the persona test reports. I searched all of them. The numbers below are what the system actually records today. If those three figures came from a pitch deck or a conversation, the deck needs updating to match this file, not the other way round.

---

## The business problem

### 1. What was wrong with existing financial-planning systems?

Four things, each written into Fynla's ratified constitution (`workforce/core/constitution/`) and its public pages:

- **Planning was rationed by price.** The wealthiest families see business, pension, property and estate as one picture because they pay an adviser; everyone else has the same problems and no tool. Vision: "pay £20/month for it, not £20,000/year" (`01-mission.md` §1). About page: "financial clarity should not be a luxury reserved for the wealthy" (`public/pages/about.php`).
- **Data was scattered across providers**, so nobody could answer "what am I worth and where am I exposed" without a spreadsheet (`public/pages/index.php` "One financial view").
- **Couples were treated as two disconnected accounts.** "Couples are 60% of UK wealth and every incumbent treats them as two disconnected users" (`03-hard-nos.md` §1). Transferable allowances, joint assets and survivor planning fell through the gap.
- **Free tools were paid for by selling.** Advertising, assets-under-management fees and referral kickbacks put the tool on the opposite side to its user; Fynla bans all three (`02-values.md` V3; `03-hard-nos.md` §2).

A fifth, product-level problem: existing dashboards summarised until nothing actionable survived. Fynla bans scores and ratings for this reason: "Someone who is told 72/100 has learned nothing about their own money" (`02-values.md` V2).

### 2. Why couldn't a normal chatbot or single AI agent solve it?

Because the hard part is not the conversation, it is the arithmetic and the perimeter, and both have to be deterministic:

- **The calculations must be exact and current.** Inheritance Tax, ISA and pension allowances, income tax bands and State Pension rules change every Budget. Fynla loads every figure per tax year through `TaxConfigService` and forbids hardcoding (`CLAUDE.md` Rule 2). Fyn is explicitly forbidden from quoting any tax figure from memory and must call the `get_tax_information` tool (`app/Services/AI/Prompts/ComplianceRules.php:36`). A chatbot answering from its training data would be wrong the day after a Budget.
- **The engines are separate from the language model.** Seven module agents (`app/Agents/`) do the analysis, recommendations and what-if scenarios; a Coordinating agent (6,951 lines) reconciles them across the household. Fyn "surfaces the outputs of Fynla's financial-planning engines" (`FynSystemPrompt.php`, identity block); it does not compute them.
- **Writing to a financial record needs a gate, not a prompt.** Fyn runs in two states: Onboarding Fyn can write, Advice Fyn is read-only, and the read-only state has every write tool physically removed from its catalogue (`AdviceFyn::WRITE_TOOLS`; `.claude/skills/fyn-architecture/SKILL.md`). Write-safety is enforced at dispatch and tool-gating, "never by prompt content".
- **Regulatory perimeter.** Fynla is not FCA-authorised and runs in fail-closed guidance mode (`05-perimeter.md` §1). Seven regulatory rules (hedging, no product names, signposting, risk warnings, tax caveats, no market timing, tax figures only via tool) are injected into every turn (`ComplianceRules.php:30-37`). A general chatbot cannot be made to hold that line reliably.
- **Memory that points rather than copies.** Fyn's memory holds pointers to live sources (the ISA allowance lives in `TaxConfigService`, a balance lives in the account record) and fetches at the moment of need, so figures never go stale inside the model's context (`fyn-memory/README.md` "The pointer model").
- **Cost has to be bounded per user.** Free users get a 100,000-token weekly budget with a 500,000 daily backstop, premium 500,000 and 2,000,000, enforced in `tier_configurations` (`06-commercials.md` §3).

### 3. What work was previously manual, disconnected or dependent on individual advisers?

From the About and How-it-works pages and the module agents:

- **Consolidation.** Gathering pension, property, savings, investment, insurance and debt values into one balance sheet (net worth, `app/Services/NetWorth/`).
- **Gap analysis.** Protection cover gaps, ISA and pension allowance headroom, retirement income shortfall, Inheritance Tax exposure (`ProtectionAgent`, `SavingsAgent`, `RetirementAgent`, `EstateAgent`).
- **Scenario modelling.** "What if I retire at 58, overpay the mortgage by £200, gift £50,000 now" (`public/pages/how-it-works.php`; `buildScenarios` on every agent; Monte Carlo job `app/Jobs/RunMonteCarloSimulation.php`).
- **Household coordination.** Spouse allowances, joint ownership splits, mirror wills, the letter to a surviving spouse (`CoordinatingAgent`, `app/Http/Controllers/Api/LetterToSpouseController.php`).
- **Ongoing monitoring.** Renewal reminders, savings-rate and mortgage-rate alerts, estate alerts, business filing deadlines: eleven scheduled alert commands run daily (`app/Console/Kernel.php:37-46`).
- **Document reading.** Statement upload with AI extraction into the right fields (`app/Services/Documents/AIExtractionService.php`).

The founders describe the previous state directly: tools "were either locked behind professional gatekeepers or too complicated for a kitchen-table conversation" (`public/pages/about.php`).

---

## The workforce

There are three distinct populations that the word "agent" covers in this codebase. The counts differ, so they are separated here.

### 4. How many agents currently exist?

| Population | Count | Where |
|---|---|---|
| **Module agents** inside the product (PHP classes that run the financial engines) | 8 concrete + 1 abstract base | `app/Agents/`: `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`, `TaxOptimisationAgent`, `CoordinatingAgent`, plus `BaseAgent` |
| **Fyn** (the AI companion) | 1 assistant, 2 write states | `app/Services/AI/AdviceFyn.php` (read-only) and `app/Services/Onboarding/OnboardingChatDirector.php` (writes), one prompt, one endpoint |
| **Workforce agents** (Claude Code agent definitions that build and run Fynla) | 20 definitions | `.claude/agents/*.md`: 8 governance/lead roles (chief-of-staff "Myrtle", build-lead, quality-lead, compliance-lead, design-lead, growth-lead, intelligence-lead, product-lead), 4 infrastructure roles (cartographer, quartermaster, archivist, persona-tester), 8 specialist tools (security-reviewer, tax-compliance-reviewer, database-optimizer, premium-ui-designer, ux-writing-expert, frontend-developer, laravel-stack-deployer, product-manager) |

Full descriptions of every one are in `agentsMap.md` in this folder.

### 5. Which agents are genuinely operational rather than experimental?

Measured from the workforce event log (`workforce/ops/log/*.jsonl`) and the board (`workforce/ops/board/`, 347 items):

| Agent | Log events | Board items owned | Verdict |
|---|---|---|---|
| build-lead | 126 | 244 (plus 7 under cycle sub-labels) | **Operational.** Does most of the delivery. |
| chief-of-staff (Myrtle) | 56 (23 daily briefs, 7 decisions, 4 gate approvals) | 3 | **Operational for governance, not for Slack.** 12 `slack_failed` events, the latest yesterday; the Slack connector has never been authorised (`charter.md` §13.3), so the "ambient" channel-reading design is not live. |
| compliance-lead | 16 | 2 | **Operational**, used on tax and perimeter reviews (for example `handoffs/W-0008-W-0205/tax-compliance-reviewer-2026-08-25.md`). |
| quality-lead | 3 | 1 | **Operational but thin.** Its evidence-pack gate exists on paper (`08-process.md` §2); it has authored four certification files (`handoffs/quality-lead/`), one of which is "CANNOT CERTIFY". |
| design-lead, product-lead | 0 logged | 8 and 5 | Used directly by CSJ rather than through the log. |
| archivist, cartographer | 1 and 0 | 3 and 1 | **Built, lightly used.** The nightly sweep is a runnable script (`workforce/ops/sweep.sh`) not a scheduled process. |
| persona-tester | 1 (`persona-passA3`) | 0 | **Operational.** Produced the 20 August and 7 September persona runs (`tests/Persona/*/reports/`). |
| intelligence-lead, growth-lead, quartermaster | 0 | 0 | **Defined, not yet exercised.** No log events, no board items. |
| Module agents and Fyn | n/a | n/a | **In production**, serving the users counted in questions 10 and 11. |

Board status today: 329 done, 6 deferred to iOS, 3 deferred, 2 queued, 1 in review, 7 closed as duplicate or invalid.

### 6. One clear example of work passing between several agents

**W-0134, "The estate column does not add up"** (`workforce/ops/board/W-0134-*.md`), found on 20 August 2026 by the persona-tester driving David and Sarah Jones through the Inheritance Tax page.

1. **persona-tester** found that four allowance rows summing to £1,000,000 sat under a subtotal of £850,000, and the £10,000 charitable legacy had no row. It wrote the board item with screenshots and database ids.
2. **build-lead** claimed it at 19:05 on 21 August (`log: claimed, "cycle1-estate; same dispatch as W-0136"`), ran the prior-art check (found W-0154 and W-0132, outcome "extend"), fixed the calculation service and the table, wrote 17 backend and 10 frontend tests, and measured the result read-only against both accounts.
3. At 20:44 build-lead handed to **quality-lead** with a written handoff note (`handoffs/W-0134/build-to-quality-2026-08-21.md`) listing what was done, what was not done ("Not browser-verified. Build does not write its own evidence"), and five further findings it raised but did not build.
4. **quality-lead** attempted certification on 23 August and recorded **"CANNOT CERTIFY"** (`handoffs/quality-lead/cycle4-certification-2026-08-23.md`), which is written into the item's frontmatter.
5. **tax-compliance-reviewer** was run over the same estate cycle (`handoffs/W-0368/tax-compliance-reviewer-recheck-2026-08-25.md`).
6. The item's board status is now `done`. I COULD NOT VERIFY in this run which production release carried the fix.

The rule that makes this a chain rather than one agent: "Build writes; Quality runs and authors the evidence; the Chief of Staff judges the pack" (`08-process.md` §2.4).

### 7. What does the managing agent do?

The chief-of-staff, named **Myrtle** (`.claude/agents/chief-of-staff.md`):

- **Mission intake.** Turns an intention from CSJ into specified work items, asking at most five questions (what does done look like, which surfaces, what is out of scope, what is it blocking, what would make you reject it). An underspecified mission parks as blocked rather than starting.
- **Judgement.** Every item entering review is judged on goal fit, trunk fit (values, hard nos, voice, perimeter), the quality bar and blast radius. "A named gap is a pass. A hidden gap is a failure."
- **Gates.** Holds production-class gates (migrations, auth, payments, tax services, AI prompts, public claims) and never approves its own gates; a founder does.
- **Liveness.** Watches every agent through the event log; probes at 45 minutes of silence or the third unchanged loop.
- **Daily brief.** 17:30, five sections (Shipped, Moving, Needs you, Watch, Read), generated from state. 23 briefs are logged; the latest is `workforce/ops/reports/brief-2026-09-21.md`.
- **Sole voice.** The only agent permitted to read or speak in Slack, WhatsApp, GitHub issues or email. In practice Slack is still blocked (question 5).
- **Never writes code, copy or specs.** "An agent that does the work cannot judge it."

### 8. What happens when agents disagree or one returns weak work?

Four written mechanisms:

- **Weak work fails the gate.** The evidence pack must contain artefacts "that cannot be written from imagination": raw test output with exit codes, database rows before and after, timestamped screenshots. "I COULD NOT TEST THIS" is a valid entry that blocks the merge; silently omitting a journey is the serious offence (`08-process.md` §2.3). Live example: quality-lead's "CANNOT CERTIFY" on W-0134.
- **Doctrinal disagreement goes to the trunk, never to the agents.** If two agents apply different rules, the Archivist's sweep classes it as a contradiction with exactly two outcomes: the branch is wrong, or the trunk is out of date and a founder amends it. "Leave both and note the difference is forbidden" (`.claude/agents/archivist.md`). The Chief of Staff's "stall rule": if it cannot decide from the trunk, it raises a doctrine question and waits rather than guessing.
- **Compliance can block and only a founder can overrule.** A compliance-lead block on tax services, AI prompt files or public claims "is overridable only by a founder — the Chief of Staff cannot overrule you" (`.claude/agents/compliance-lead.md`).
- **Thrashing agents are repaired, not argued with.** The Quartermaster diagnoses silence or looping to root cause and restarts, splits or reassigns; the same root cause three times in a week stops being a bug and goes to CSJ as a design problem (`.claude/agents/quartermaster.md`).

Underneath all of it is the engineering rule for the model itself: "loop until correct", with only two exits, green per the plan or a question only CSJ can answer (`CLAUDE.md` Rule 14).

### 9. What information is stored in each of the four types of memory?

Fyn's memory follows the CoALA model (Cognitive Architectures for Language Agents). Paths are configured in `config/fyn.php:49-58`; the design is stated in `fyn-memory/README.md`.

| Memory | What it holds | Who writes it | Where |
|---|---|---|---|
| **Working** | The live context for one turn: the user's first name, the selected context buckets of their own data, the active procedure, the recent conversation and any summary | The system, per turn (`FynTurnContext`, `FynContextAssembler`, `FynContextSelector` in `app/Services/AI/Fyn/`) | In the prompt only; nothing persists |
| **Procedural** | *How* Fyn does things. At its heart the **pointer registry**: for each piece of data, which live source owns it and how to fetch it. Also recommendation routing, system-prompt overlays, tool schemas and workflows | **CSJ authors** as committed markdown; never agent-generated | `fyn-memory/procedural/` (`pointers/`, `recommendation-routing.md`, `system_prompt_overlay/`, `tool_schema/`, `workflow/`) |
| **Episodic** | *What happened*: salient per-interaction episodes and fetch provenance (what was fetched, from which source at which version), written only if they pass a CSJ-authored rubric | **Fyn writes** at runtime, governed by `episodic/RUBRIC.md` | `fyn-memory/episodic/episodes/` and `storage/app/episodic/`; reconciled nightly and cold-archived after 12 months (`Kernel.php:70-71`) |
| **Semantic** | Durable knowledge with **no live owner**: FCA and house-view narrative, allowance, product and tax explanations. Anything with a live owner is a pointer, never frozen here. Plus a per-user semantic store for facts learned about that person, erasable under GDPR (`fyn:user:erase`) | **CSJ authors** the corpus; a learning path can propose facts for promotion, but `FYN_LEARNING_ENABLED` defaults to `false` | `fyn-memory/semantic/` (`allowance/`, `fca/`, `house_view/`, `product/`, `tax/`), index at `storage/app/memory/semantic/index.json`, per-user at `storage/app/memory/semantic-user/` |

The rule that ties them together: **"Memory holds pointers, not copies."** The £20,000 ISA allowance lives in `TaxConfigService`; a balance lives in the account record; a recommendation is generated live. The write-safety boundary stays in code, never in procedural memory (`fyn-memory/README.md` "Authoring boundary").

Three facts about the current state, read from code this run, that should be stated honestly if memory is presented as a feature:

- **Retrieval is keyword matching, not embeddings.** There is no vector table anywhere; dense retrieval was deliberately deferred until roughly 500 concurrent users (`app/Services/AI/Memory/Recall/RecallScorer.php:12`).
- **The episodic rubric is a draft** (`fyn-memory/episodic/RUBRIC.md`, `version: 0`), so the store returns an empty rubric and the planner never receives it (`FynMemoryStore.php:147-149`). The 414 local episode files are test artefacts, not real user episodes.
- **Learning is switched off** (`FYN_LEARNING_ENABLED` defaults false, `config/fyn.php:68`). When on, proposed facts are staged as pending and promoted only by a human (`fyn:semantic:promote --reviewer=`); nothing self-modifies. Two content stores are populated today: the procedural tool schemas and pointers, and 20 house-view semantic files. The `allowance`, `fca`, `product` and `tax` semantic folders are empty.

Conversation history is separate from these four: it lives in `ai_conversations` and `ai_messages` in the database (307 conversations, 1,061 messages in production today), summarised every thirty minutes when stale (`Kernel.php:65`).

---

## Evidence and results

All production figures below were counted at 10:00 BST on 22 September 2026 with read-only aggregate queries. "Real" means not a seeded preview persona and not one of the three founder admin accounts.

### 10. How many of the registered users actively used the system?

The system holds 82 user rows, not 48. After removing 11 preview personas and 3 admin accounts, **68 real registered users**, the first created 7 January 2026 and 20 of them in the last 30 days.

| Measure of "active" | Users |
|---|---|
| Signed in at least once (ever held an API token) | 33 |
| Signed in within the last 90 days | 18 |
| Signed in within the last 30 days | 10 |
| Entered data into at least one module | 39 |
| Entered data into three or more modules | 19 |
| Completed onboarding | 11 |
| Had a Fyn conversation (ever) | 32 |
| Had a Fyn conversation in the last 90 days | 26 |
| Had a Fyn conversation in the last 30 days | 18 |
| Currently on a paid subscription | 5 (21 further subscriptions have expired) |

There is no `last_login_at` column, so "signed in" is measured from token use.

### 11. Approximately how many plans, analyses or workflows have been completed?

Recommendations, net worth, projections and Inheritance Tax analyses are computed live and cached, not stored as rows, so they cannot be counted after the fact. What is stored:

| Stored artefact | Real users | Total rows |
|---|---|---|
| Fyn conversations | 81 conversations from 32 real users (48 in the last 30 days) | 307 including founder and preview accounts |
| Fyn messages | 370 user turns, 489 assistant turns | 1,061 |
| Onboarding progress records | 335 rows across 45 users | 344 |
| Wills built | 9 | 21 |
| Goals set | 15 | 50 |
| Savings accounts entered | 67 rows across 18 users | |
| Investment accounts | 39 rows across 13 users | |
| Pensions (defined contribution + defined benefit) | 43 rows across 15 users | |
| Properties | 29 rows across 12 users | |
| Mortgages | 11 users | |
| Gamification point awards | 455 across 35 users | 589 |
| Level crossings (since 7 September) | 65 across 23 users | 70 |
| Documents uploaded and AI-extracted | 2 (one user, April) | 2 |
| What-if scenarios saved | 1 | 1 |
| Holistic-plan recommendation tracking | 0 | 0 |

Two honest readings: real-user engagement is concentrated in data entry, onboarding and Fyn chat; the stored-plan features (what-if, documents, tracked holistic plans) have almost no real usage. Founder and preview accounts generate the majority of Fyn conversations (226 of 307).

### 12. How long would one of these tasks take manually versus through Fynla?

**Measured in Fynla:** the persona-tester entered the full "peak earners" household (two adults, two children, two properties, a mortgage, four savings accounts, three investment accounts, four pensions, wills, gifts and a charitable bequest) through the web forms in **81 minutes**, 21:44 to 23:05 on 20 August 2026 (`tests/Persona/20-08-2026_run/reports/R-01-pass-a-entry.md:5`). Verification of every figure on web and `/m` for both spouses then took 30 minutes (`R-02:6`). The marketing claim for a basic first picture is "Three steps, 15 minutes" (`public/pages/how-it-works.php`).

**Manual baseline: I COULD NOT VERIFY.** Nothing in the repository or vault records how long an adviser takes to produce the equivalent fact-find, net worth statement, Inheritance Tax calculation and projection. Chris and Brett can state that from professional experience; the About page cites over 40 years' combined experience. Any figure put in front of Ang for the manual side should be attributed to them, not to the system.

### 13. How were the 99% and 90% figures calculated?

**I could not find either figure.** Searches across the repository, the fynlaBrain vault (1,514 documents), the workforce board, handoffs, briefs and persona reports returned no "99%" or "90%" that describes an outcome of Fynla. The nearest things that exist:

- The Fyn evaluation harness sets a **95% floor** for entity-count recall and field precision per module, with 100% hard-fail floors for record validity, monetary value accuracy and cross-entity consistency, and a 0% fabrication floor (`config/fyn_eval.php:27-40`; `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/README.md`). A planned extension adds twelve more entity types "at 90% baseline" (`April/April28Updates/CSJTODO.md:606`). That is a threshold, not a result.
- The intelligence-lead guardrails include "crash-free ≥99.5% web and mobile" (`.claude/agents/intelligence-lead.md`), again a target.

If the 99% and 90% were stated to Ang as results, they need a source before they are repeated.

### 14. How large were the test samples?

| Test | Sample | Evidence |
|---|---|---|
| Fyn eval scenarios defined | 9 YAML scenarios on disk across 10 categories (query types, insight quality, preview personas, multi-entity, handoffs, cancel/timeout, prompt injection, regulatory, provider parity, canonical behaviour); the rubric describes a "65-scenario Mode 2 run" as the design target | `tests/Feature/Fyn/Eval/scenarios/`; `fynlaBrain/April/April24Updates/fyn-rubrics.md` |
| Fyn eval recordings persisted | **0** sessions in both the local and the production `eval_recording_sessions` table | queried this run |
| Automated backend tests | 486 unit and 556 feature test files; 39 unit test files under tax, 87 estate test files | `find tests/...` |
| Browser scenarios | 24 BS-NN Playwright contracts, 5 module E2E specs | `tests/Browser/scenarios/`; `tests/E2E/` |
| Persona regression runs | 2 full runs (20 August: 12 reports; 7 September: 5 reports), each one household of two linked accounts | `tests/Persona/*/reports/` |
| Human-account QA | 1 report on Brett's live account, 21 May 2026, 19 bugs found | `docs/archive/brettTesting/Fynla_QA_Test_Report.md` |
| Real users | 68 (question 10) | production database |

So the AI accuracy evidence is a defined rubric plus automated tests, not a recorded eval corpus. The 0-row eval tables are the gap to close before quoting an accuracy percentage.

### 15. Were calculations checked by Chris, another adviser or an automated test?

All three, at different depths:

- **Automated.** The Pest suite runs on every pull request in CI (`.github/workflows/quality.yml`), including the estate and tax unit tests above. Example: W-0134 added 17 backend and 10 frontend tests and a regression sweep of 1,046 passing tests (`handoffs/W-0134/build-to-quality-2026-08-21.md`).
- **Independent review agent.** `tax-compliance-reviewer` is run on tax diffs and writes its findings to the handoff folder (for example `handoffs/W-0008-W-0205/tax-compliance-reviewer-2026-08-25.md`, `handoffs/W-0368/tax-compliance-reviewer-recheck-2026-08-25.md`).
- **Hand recomputation.** The persona verification report recomputes the household wealth summary line by line by hand and records each as OK or raises a defect (`R-02-pass-a-verification.md`, "The hand-recomputed wealth summary").
- **Chris.** Every release goes feature branch -> `dev` -> `main`, and only Chris performs the `dev` -> `main` merge after testing on the dev server in his own browser (`CLAUDE.md` "Branching and deployment"; Rule 21 "green where CSJ tests"). The constitution assigns tax and regulatory rulings to him (`05-perimeter.md` owner line).
- **Brett.** The 21 May QA report was run on Brett Isenberg's own live account and reconciled net worth, pensions, properties and investments; it found 19 bugs.

What has **not** happened: no external regulated adviser outside the founders has signed off the calculations, and the Gift Aid, tapered allowance and National Insurance threshold figures added on 21 September are unit-tested only, not yet walked in the browser (project memory, 2026-09-21).

### 16. Can we show one anonymised output, workflow log or evaluation result?

Yes. Three candidates, all using fictional persona data (David and Sarah Jones are test personas from `tests/Persona/peak_earners.md`, not customers):

**(a) A verified household output, web and `/m` matching, from `R-02-pass-a-verification.md`:**

```
                 David       Sarah      Total     hand-check
Pensions       £500,000   DB only    £500,000     180,000 + 320,000            OK
Property       £425,000   £425,000   £850,000     850,000 joint 50/50          OK
Investments     £47,500   £132,500   £180,000     85,000 + 95,000              see W-0015
Cash            £47,500    £28,780    £76,280     25,000+22,500 / 6,280+22,500 OK
Valuables            £0    £18,000     £18,000     engagement ring             OK
Total Assets £1,020,000   £604,280 £1,624,280                                  OK
Mortgages       £32,500    £32,500     £65,000    65,000 joint 50/50           OK
Net Worth      £987,500   £571,780 £1,559,280                                  OK
```

Net worth on web £987,500 = `/m` £987,500. One defect (joint investment share) was raised as W-0014/W-0015 and later fixed.

**(b) A workflow log, W-0134, from `workforce/ops/log/2026-08.jsonl`:**

```
2026-08-21T19:05:05Z  build-lead  W-0134  claimed   "cycle1-estate; same dispatch as W-0136"
2026-08-21T20:44:05Z  build-lead  W-0134  handoff   to quality-lead, handoffs/W-0134/build-to-quality-2026-08-21.md
2026-08-23            quality-lead W-0134 certification: CANNOT CERTIFY (frontmatter)
```

**(c) An evaluation scenario definition, `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/savings_3x_mixed.yaml`:** one user message ("I've got £5,000 in a Nationwide easy access, £15,000 in a HSBC cash ISA, and £2,000 in a Marcus regular saver") must produce exactly three `create_savings_account` tool calls with the right provider, type and balance, three database rows, and no `persona_state_change` or `handoff` event. This shows what is measured; a recorded result for it does not yet exist (question 14).

Any of these can be shown as-is. The Brett QA report contains his real holdings and should not be shared without his consent.
