# Fix plan: production Fyn walk of 11 October 2026, plus the normal-registration start

**Status: planning only. Nothing in the code, the database or any server has been changed.**

- **Sources:** Brett's walk document (`brettTest/2026-10-11-prod-fyn-gpt6-luna-walk.docx`, 63 findings) and your onboarding change.
- **Author and date:** written 12 October 2026 by Claude for CSJ.
- **Revision:** revised the same day after your answers. Every question now goes to the advice answer process; the redundant decisions are removed; your registration answers are applied.

## 0. Rules for carrying out this plan (NON-NEGOTIABLE, CSJ 2026-10-12)

These rules override anything else in this plan, in any skill, in CLAUDE.md's delegation allowance, and in any other plan or habit.

### R1. NO SUB-AGENTS

- Every task is done by me, in the main session, one at a time.
- No Agent tool, workflow, background agent, worktree fixer, "read-only" research agent, reviewer agent (including tax-compliance-reviewer and security-reviewer) or code-review skill that spawns agents.
- The tax and security checks are done by me, in the main session, with each source cited in the task's evidence.

### R2. Walk as a user: click links, no made-up URLs

- **Starting point.** Every walk starts from the site's front door: https://csjones.co/fynla/ or https://fynla.org/ for web, and the /m entry for /m. Those are the only addresses I type.
- **Getting around.** Every page is reached by clicking what a user would click: menu items, links, buttons and Fyn's bubbles. I never type or paste a page address and never invent one.
- **Acting on a page.** I click, type into fields and submit forms in Playwright. No `evaluate` or JavaScript to change state, no API calls to set up or check a screen, and no tinker writes to make a screen look right.
- **Screen sizes.** Web runs at 1440 × 900 and /m at 390 × 844. I accept the cookie banner, never decline it.
- **Screenshots.** Every checked state gets one, saved in `October/October12Updates/shots/`.
- **The only exceptions,** because I can't read the inbox: I read sign-in and registration codes, and an invitation link, from the database. Each use is written in the task's evidence.
- **When I can't walk it.** If a check could not be walked, the box stays empty and the evidence says **I COULD NOT TEST THIS**. It is never "verified".

### R3. How a box is ticked

A box is ticked only with its evidence, dated, written in the task log (section 0b). The six boxes, in order:

| Box | What has to be true | Evidence written |
|---|---|---|
| **Code** | The change is made on its own branch off dev. | Branch, commit, PR number |
| **Test** | A test written first failed for the right reason, then passed. Only the touched test files were run; never a full suite. | Test file names; red output, then green |
| **Dev** | Merged to dev and deployed to csjones (server pull, plus both bundles rebuilt from dev when the frontend changed). | csjones commit; bundle build time |
| **Dev walk** | Walked on csjones as a user (R2), web and /m, with a fresh account where the task needs one. | Steps clicked; screenshot names |
| **Prod** | Released to fynla.org. You decide when, and you run the release (`/release` or the `!` line). | Release letter; main SHA; file checksum on the server |
| **Prod walk** | The same walk on fynla.org (R2), web and /m, then the walk accounts purged. | Steps clicked; screenshot names; accounts purged |

### R4. Cadence

- One task at a time, in the order of the list. The next task starts only when the current one has its Dev walk box ticked.
- Prod releases carry the tasks walked green on dev. Each task's Prod walk is done right after its release.
- Anything new found on a walk is added to the list as its own task and fixed in the same loop.
- I stop when you send a message.

### R5. Only the changes are reviewed, tested and walked (CSJ 2026-10-12)

- **No full code review:** no `/code-review`, no tech-debt audits, no review of code the task did not touch. I review only the task's own diff.
- **No full test run:** only the test files the change touches.
- **The full test run happens once, when the whole plan is complete** (task T43).
- **Code already in place is not retested.** A walk covers the flow the task changed, not a regression sweep of everything else.

## 0b. Task list

Boxes: `[ ]` open, `[x]` done (with evidence in the log below). F, M, P and Q are Brett's findings; N are mine (section 4).

| # | Task | Fixes | Code | Test | Dev | Dev walk | Prod | Prod walk |
|---|---|---|---|---|---|---|---|---|
| T0 | Dev's existing walk fixes R19–R39, already merged and tested, go out with the first release | F14 (prompt), M6 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T1 | Fyn receives the user's exact words (no character stripping) | N1, Q17 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T48 | **NEXT ACTION (CSJ 2026-10-12): one Fyn limit per tier.** Free **750,000** tokens, Premium **3,000,000**, replacing both the weekly budget (switches to a cheaper model) and the daily hard stop, "so we only have one limit" and the test runs can complete. **Per rolling week (CSJ 2026-10-12: "yes per week please")**: over the limit, Fyn stops; the daily stop and the cheaper-model switch are removed. Map in the session 3 handover | CSJ | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T2 | One question test (`isAdviceShaped`, extended) in front of every gate that runs before the model | F19, F22, P1, P2, P7, Q5, Q7 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T3 | `QueryClassifier` never files a question as data entry or navigation; adds retirement, estate, savings-tax and rent wording | Q6, Q7, Q8, M8, F19, F22, P3, P5, P7, Q5, Q10 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T4 | `capture_*` writes recorded as landed | N4, P2, Q6, Q7 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T5 | The capture-handoff prompt rule applies to statements only | F20, P5, Q5, Q10 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T6 | Navigation moves to the server for every surface; the web keyword matcher is deleted | F3, F4 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T7 | A what-if request reaches the what-if scenario capture | P5 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T8 | A delete preview refuses a record that does not exist | Q22 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T9 | An invite request reaches the existing invite step | Q17 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T10 | Spouse figures always in Fyn's context | P4, P3, F5 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T11 | The retirement plan tool carries the Retirement page's headline | Q6 | [x] | [x] | [x] | [x] | [ ] | [ ] |
| T12 | Published working for the savings-to-spouse, child benefit charge and estate scenarios (item 18) | F5, P3, F6, M8, Q8 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T13 | Older turns summarised for the model; search reads the open conversation; recall rule | Q13 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T14 | Corpus entry on gifts within seven years of death; `source:` filled on every entry; Fyn cites it | F17, M5 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T15 | Money given to the model written exactly: £100, £100.15, never rounded | F14 (context) | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T16 | Onboarding bubble steps read typed answers | F1 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T17 | Typed facts kept and later forms pre-filled; children open the dependants form | F2, F11, F16, F21 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T18 | The cap is checked when a section starts; the at-cap reply is in words | F10, F9 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T19 | "No mortgage" ticks the No mortgage box | M3, Q2 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T20 | One form per person named in a message | P8 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T21 | The self-employed sentence says profit | M4 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T22 | Normal registration opens on "Which of these do you have?" with the first-name greeting (section 6) | your change | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T23 | Save Tax report only for Save Tax arrivals; others end on their actions; arrival record corrected (10 rows); iOS `/actions` link (section 6b) | your change | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T24 | Fyn's forms gain the fields whose columns exist | F9, F11, F12, F15, Q1, Q15, Q19, Q24 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T25 | Stop storing placeholders (rate 0, "repayment", "To be completed", "Unknown") as facts | Q1, Q9, N7 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T26 | Workplace pensions get their scheme type; production rows corrected | N2 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T27 | Joint records added after onboarding link to the partner; partner's figures transferred for the partner to confirm | Q23 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T28 | The Liabilities page shows mortgages to Free users | F12 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T29 | Gift Aid and relief at source priced by band extension; the one-off line in the how-to | F7, Q4 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T30 | "What you can afford" and "headroom" labels | F8 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T31 | Your W-0467 note on the Estate page's Inheritance Tax Summary | F18, P6 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T32 | The spending form names the payments the app adds (W-0140) | Q14 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T33 | Opening /m no longer signs the web tab out | Q3 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T34 | Signing in returns the user to the link they followed | Q25 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T35 | History labels; a failed turn saves its reply; failures logged | Q12, N6 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T36 | The mirror-will refusal offers the partner invite | Q16 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T37 | A 0% will beneficiary is refused | Q20 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T38 | Retirement target caption says "a target income" | F23 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T39 | Form delete asks "Remove X?" first, the same on every surface | M7 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T40 | No visible self-correction in Fyn's replies | Q11 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T44 | Remove the suggested prompts from Fyn's chat window (CSJ 2026-10-12, screenshot `remove.png`): the /m "Tell me more about: …" pills (`resources/mobile/views/Dashboard.vue:324-325`, `suggestions` at `:544-551`) and their web counterpart, the chat panel's "Suggestions" list (`resources/js/components/Shared/AiChatPanel.vue:342-365`, `suggestedPrompts` at `:644`). Native Fyn has none (`ios-native/Fynla/Features/Fyn` checked). The "Ask Fyn about this" button on action cards is not in the chat window and stays | CSJ | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T45 | The write router files "Change my retirement income target …" as a `goal` (the goal keyword "target" wins over the `retirement_goals` wording), so the capture is opened for the wrong record type. Found on the T4 walk (#22779, #22786); the capture model still used `capture_retirement_goals` | T4 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T46 | Fyn's own arithmetic on the user's figures is wrong: /m Q7 gave "about £478 a month" where 5% a year compounded monthly over 228 months needs £492.89 to build £186,975. Fyn has no calculation tool (first T5 walk, 2026-10-12, conversation 592) | T5 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T47 | Fyn's answer printed a formula as raw LaTeX (`\\(\\frac{…}\\)`) on /m (first T5 walk, conversation 592) | T5 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T49 | On /m, Fyn's reply paragraphs run together with no space or break ("£5,800.Your recorded…"), T48 Dev walk, Rory 504 | T48 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T50 | The what-if comparison is £0 for every figure (T7 walk, scenarios 1 and 2, Alex 502). `WhatIfScenarioService::extractMetrics` reads keys the agents do not return, so every figure defaults to 0; the `pension_contribution` override does nothing (`applyOverrides`); and the user's own row is saved mid-calculation, then reverted. Fyn tells the user the comparison shows £0 | T7 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T51 | A Free user can create a paid-plan what-if scenario through chat (`create_what_if_scenario` has no tier check; the API route is gated by `CheckSubscription` `what_if`), then is sent to the paid-plan teaser (T7 walk, Alex 502) | T7 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T52 | On a capture failure the screen shows the model's own sentence, but the saved reply (History, reload) is the composed failure text, so the two differ ("There isn't a saved goal to delete, so your round-the-world trip goal remains unchanged" live vs "You don't have a goal saved, so there's nothing to delete." saved; T8 walk #22842, #22845) | T8 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T53 | Fyn's record tools read only the user's own records, never a linked spouse's who shares their data: asked for Rory's dividends and gains, Fyn listed Pat's investment accounts (none), then asked "Do you mean dividends and gains in Rory's own records?" (T10 /m walk, Pat 506, #22859). The answer was right only because Rory has none | T10 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T54 | `get_module_analysis(retirement)` sends two projections: `summary.headline` (`RetirementHeadline`, the page's "Projected Capital", Pat 506 £260,978.89, income £12,266.01) and `income_projection` plus `summary.total_dc_value` from `PensionProjector` (£217,251.58, income £8,690.06). Fyn quoted both incomes in one answer (T11 /m walk, #22861). One figure, every surface (`feedback_one_figure_every_surface_fetched`) | T11 walk | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T41 | Live checks first, then fix what they show: /m message typed after a page change (M1); silent Saves (F10, Q24); will builder name and warning (Q19, Q20); retirement projection change (Q21); /m dashboard blank area | M1, F10, Q24, Q19, Q20, Q21 | [ ] | [ ] | [ ] | [ ] | [ ] | [ ] |
| T42 | Brett's account 812: the "declared none" flags and the unasked goal written by these bugs. A database change, so done only with your OK | F19, P2 | [ ] | n/a | n/a | [ ] | [ ] | [ ] |
| T43 | The full test run, once T0–T42 are done (R5) | all | n/a | [ ] | n/a | n/a | n/a | n/a |

### Task log (evidence)

- **T0, Code and Test (2026-10-12, from the handover of 2026-10-09, session 3):** PRs #1185 and #1187–#1207 merged to dev, each with its own tests (listed in `October/October9Updates/regression-walk-2026-10-09.md`, R19–R39).
- **T0, Dev (2026-10-12):**
  - csjones pulled dev `f00076906` (no pending migrations).
  - Both bundles built from a clean copy of dev `f00076906` with `deploy/csjones-fynla/build.sh` at 09:02 BST, and uploaded with the keep-old-chunks pattern.
  - Caches cleared: route, config and view, then `config:cache` (no `cache:clear`).
  - The site answered 200.
- **T0, Dev walk (2026-10-12):** the other R19–R39 fixes were walked on csjones before (the walk file records each retest), so under R5 only R20's caption (#1204) was walked.
  - Steps, as Rory Retest (user 504, web 1440 × 900):
    1. Typed https://csjones.co/fynla/ and clicked "Sign in".
    2. Signed in with email and password.
    3. Entered the emailed code, read from the csjones database (the R2 exception).
    4. Clicked "Retirement" in the side menu.
  - Result: under the Pension Pot Projection chart, "Using Upper-Medium risk profile (6.50% expected return). The middle outcome grows a little slower than this rate because returns vary from year to year." The old "The line shows the middle outcome" is gone.
  - Screenshot: `shots/T0-01-r20-caption-csjones-web.png`.
  - The card's heading, "(using high probability of 80% of achieving 6.5% returns)", is still the open R9/R20 decision in the 9 October walk file; it is not part of this plan.

- **T1, Code (2026-10-12):** branch `fix/t1-fyn-exact-words`, commit `3e82fe892`, PR #1209.
  - New `UserContentSanitiser::message()` keeps the user's message as typed. It drops only invisible characters and the angle brackets of tag-shaped text.
  - `FynContextAssembler::build` uses it for `<user_message>` instead of `clean()`.
  - Security check (main session, R1): earlier turns already reach the model raw (`HasAiChat::buildMessageHistory`), the block boundary is kept, and the prompt-injection rule is unchanged.
- **T1, Test (2026-10-12):**
  - 7 new tests: 5 in `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php`, 2 in `tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`.
  - Red before the change: "Call to undefined method …::message()", and the assembled turn held the stripped text.
  - Green after: 52 passed.
  - The fixture tests on the assembled turn also pass: `FynContextAssemblerOverlayTest` and `LeanPromptConformanceTest`, 14 passed.
- **T1, Dev (2026-10-12):**
  - PR #1209 merged to dev (`cc2ee4437`); csjones pulled dev and caches were cleared. Server PHP only, so no bundle rebuild.
  - Checking the branch out on csjones before merging was refused by the session's permission check, so the R3 order (merge, then deploy) was used.
- **T1, Dev walk (2026-10-12):** as Rory Retest (user 504), continuing from T0's sign-in.
  - **Web 1440:**
    1. Clicked "Chat with Fyn", then "New".
    2. Typed "How did you work out the £14,500 pension figure?", a question the model answered before T1 (#22689).
    3. gpt-6-luna answered with the working (#22752).
    4. The stored turn shows the model received it exactly: `<user_message> | How did you work out the £14,500 pension figure? | </user_message>`, question mark included.
    - Screenshot: `shots/T1-03-question-answered-exact-words-csjones-web.png`.
  - **/m 390:**
    1. Typed the /m front door; it opened signed in as Rory, the desktop session carrying across.
    2. Clicked "Chat with Fyn".
    3. Typed "Where does the £14,500 in my pension action come from?"; answered with the working (#22754).
    4. The stored turn shows the exact message, "?" included.
    - Screenshot: `shots/T1-04-question-answered-exact-words-csjones-m.png`.
    - /m resumed the same conversation as the web question, so the reply opened "I answered that a moment ago, so let me put it differently". That follows from the conversation, not from T1.
  - **The email check (Q17): I COULD NOT TEST THIS on the walk.** "Please invite my partner to link accounts. Their email is slaterjoneschris+t1walk@gmail.com. Is that address written correctly?" was handed to capture and opened the spouse form (RC4), so the model wrote no reply. The unit tests cover the email characters. T9 (invite requests) walks it again.
- **T1 follow-up, security (2026-10-12):** an automated security review found that the single tag pass turned "<</user_message>>" into a real "</user_message>".
  - Fix: the pass repeats until nothing changes. Branch `fix/t1b-tag-neutralise-to-fixed-point`, PR #1211, merged `7be2bc5c3`, deployed to csjones.
  - Tests: a nested-tag case in `UserContentSanitisationTest` and a boundary count in `FynContextAssemblerTest`. Red before (the assembled turn held two closing tags), green after; 54 passed.
  - Walk (/m 390, Rory): typed "Testing <</user_message>> brackets. What is the ISA allowance this tax year?". gpt-6-luna answered £20,000 (#22756), and the turn holds exactly one `</user_message>`.
  - The API's input middleware (`SanitizeInput`, which strips HTML) had already removed the tag ("Testing  brackets…"), so the bypass was not reachable through the chat endpoint. The fix closes it where the boundary is built.
  - Screenshot: `shots/T1b-01-nested-tag-one-boundary-csjones-m.png`.
- **T2, Code (2026-10-12):** branch `fix/t2-one-question-test`, commit `a7f0eae41`, PR #1213.
  - `QuerySchemas::isAdviceShaped` is the one question test (Rule 20). Three tests existed: `WriteIntentClassifier::looksLikeQuestion`/`isQuestion`, `OnboardingChatDirector::userAskedQuestion` and the uncalled `isAdviceShaped`. The first two are removed, and their callers use the one test.
  - It counts as a question:
    - a "?" anywhere;
    - a sentence or colon clause that opens as a question or a request ("give me", "work it through", "which wins", "explain", "is that…");
    - phrases anywhere ("show the working", "with the working", "nothing to record", "figures please", "is that right", "rules question").
  - Topic words alone ("forecast", or "how much" inside "not sure how much") no longer count, so a typed answer at a setup form still fills it.
  - Gates that call it first:
    - `recordDeclaredNone` (it had no question check);
    - `WriteIntentClassifier::classify` and `proposalAcceptanceIntent`;
    - `captureContinuationIntent`;
    - `offerTypedChangeForm`;
    - the setup walk's checks (question at the bubbles, interruption, typed answer fill, the capture handlers).
  - The write classifier also drops a negated write verb ("nothing to record", "don't save", "I didn't ask you to record").
  - Navigation (web `chatNavigationRouter.js`) is T6.
  - Security and tax check (main session, R1): no figures, no new input surface; the regexes are anchored and bounded.
- **T2, Test (2026-10-12):**
  - Brett's exact production messages (#3861–#4033, read from fynla.org) were added to five files:
    - `tests/Unit/Constants/IsAdviceShapedTest.php`;
    - `tests/Unit/Services/AI/WriteIntentClassifierTest.php`;
    - `tests/Unit/Services/AI/AdviceFynCaptureContinuationTest.php`;
    - `tests/Feature/AI/AdviceDeclaredNoneTest.php`;
    - `tests/Feature/AI/TypedChangeFillsFormTest.php`.
  - Red before. In the gate files, 12 failed:
    - classify returned goal, spouse, property and retirement_goals intents;
    - the continuation reused the opening intent;
    - the "none" gate wrote "I won't ask about";
    - the typed-change form extractor was called.
  - Green after: 87 passed, with `OffScriptAnswerFilterTest` and `AdviceFynProposalAcceptanceTest` included.
  - The files on the changed onboarding and capture paths also pass:
    - 140 passed: `OnboardingInterruptionTest`, `TypedAnswerFillsFormTest`, `JourneyCaptureFormTurnTest`, `SavetaxFunnelQuestionsTest`, `ZeroIncomeFunnelOnboardingTest`, `CaptureContinuationAfterWriteTest`, `RecordDuplicateCheckerTest`, `HouseholdFinancialContextTest`, `RestatedRecordFillsAddFormTest`, `TypedFormFillTest`;
    - 74 passed: `CaptureTurnAnswersQuestionsTest`, `CaptureWriteFailureTest`, `ParkedFactsFlushTest`, `GroupedExtractAdvanceOnAnsweredTest`, `CaptureAckTest`, `CaptureStateToolCoverageTest`, `CampaignReentryDispatchTest`.
  - Two older expectations changed with the rule (CSJ 2026-10-12: a question always goes to the answer process):
    - "Can I add it?" is now a question;
    - "Add a goal: where should I retire" is now a question. "Add a goal for when I retire" still writes.
- **T2, Dev (2026-10-12):** PR #1213 merged to dev (`7081e81fd`). csjones pulled dev and cleared route, config and view, then ran `config:cache`. Server PHP only, so no bundle rebuild. The site answered 200.

- **T2, Dev walk (2026-10-12, csjones `7081e81fd`, as Rory Retest, user 504):** each message is Brett's exact text. Routes were read from the csjones log and the stored rows.
  - **Web 1440:**
    1. Typed https://csjones.co/fynla/; the old session dropped to /login.
    2. Signed in with email and password, then the emailed code. The code was read with `php zz_code.php 504` on csjones (the R2 exception).
    3. Clicked "Chat with Fyn", then "New" (conversation 586).
    4. **Q5 #3999** ("Rules question, nothing to record. … Figures please."), which was a property form before T2. Now answered with figures (£8,700 − £870 = £7,830 before mortgage-interest relief), with no form. Screenshot `shots/T2-01-q5-rules-question-answered-csjones-web.png`.
    5. **P1 #3963** ("… Give me the yearly tax saving … Show the working for each."), which was written as a goal before T2. Now answered with the working: £38,000 × 4.5% = £1,710; £500 allowance; £1,210 at 40% = £484. No form. Screenshot `shots/T2-02-p1-give-me-working-answered-csjones-web.png`.
    6. **F19 #3869** ("… Monzo and nothing else … would the family be ok? …"), which was recorded as "none" before T2. Now answered (4 × £72,000 = £288,000 death-in-service), with no "I won't ask about". Screenshot `shots/T2-03-f19-nothing-else-question-answered-csjones-web.png`.
       - Rory has three locked items (`gia_holdings`, `dividend_income`, `charitable_giving`), so the old gate would have written all three.
       - `declared_none_keys` is still empty (read-only tinker).
    - Rows #22757–#22762 are all `advice`, with read-only tools only.
  - **/m 390:**
    1. Typed the /m front door; it opened signed in as Rory (conversation 572). Clicked "Chat with Fyn". Screenshot `shots/T2-04-csjones-m-front-door.png`.
    2. Typed "I have a workplace pension with Royal London", a statement. The log shows `Deterministic write-intent routed` (pension), and the form opened. **Not saved.** Screenshot `shots/T2-05-…`.
    3. **Q7 #4013** ("Then work it through: … Show the working."). The gates did not route it: there is no `Deterministic write-intent routed` line and no continuation. Screenshot `shots/T2-06-q7-work-it-through-after-capture-csjones-m.png`.
       - The model then handed it to capture itself: `[FynLoop] LLM-fallback write-intent caught (classifier miss)`, entity `retirement_goals`.
       - `capture_retirement_goals` recorded "age 60 and £70,373 a year" (#22766). That is T5 (RC4), not a gate.
    4. **P2 #3965** ("… Now give me … with the working."), sent after #22766, an unlanded `data_capture` row. Before T2 the continuation would have reused the Royal London pension intent.
       - Now there is no `Deterministic write-intent routed` line, so the continuation gate passed it.
       - The model handed it to capture itself (`LLM-fallback … savings_account`), and a savings form opened filled in "Chase / £1,710 / 4.5%" (#22768). This is T5. **Not saved.** Screenshot `shots/T2-07-p2-give-me-after-unlanded-capture-csjones-m.png`.
    5. **P7 #3973** ("… my husband Dev … Is that right? Explain the working …"), which was a spouse edit form before T2. Now answered as advice (#22770: `fetch_estate_plan`, `get_tax_information`, `list_records`): "£3,900 would not be the Inheritance Tax due on that transfer". No form. Screenshot `shots/T2-08-p7-spouse-estate-question-csjones-m.png`.
  - **Result for T2:** every question passed every gate on both surfaces. Where Fyn still opened capture, the log shows it was the model's handoff, which T5 fixes.
  - **Walk-account data changed by that T5 handoff:** Rory's retirement target is now age 60 / £70,373 (#22766). This is left as data for T5's walk.

- **T3, Code (2026-10-12):** branch `fix/t3-classifier-files-questions`, commit `b478236cc`, PR #1214.
  - `QueryClassifier::classify` asks the one question test (`QuerySchemas::isAdviceShaped`) first. A question is never filed as `data_entry` or `navigation`, the two bypass types that carry no tools.
  - Navigation no longer matches "show the" (`(take|go|navigate) (me) (to|the)`). "Show me my investments" stays navigation (T6). In the one test, "show me" counts as a question only before how, what, why, which, where, when, whether or if. "Show me the working" is caught by the phrase list.
  - New wording, by type:
    - `RETIREMENT_READINESS`: "retire at", "stop work(ing) at", "afford to retire", "(retirement|pension|income) shortfall", "how far short" (not followed by emergency, cover, protection, ISA, goal or deposit), "put away each month" (not with goal, deposit, wedding or holiday), "extra/bigger/additional pot", "withdrawal rate".
    - `RETIREMENT_CONTRIBUTION`: "paid … into … pot/SIPP".
    - `ESTATE_IHT`: "death tax"; a named heir "inherit(ed)"; "if I/we/both of us died" followed within 120 characters by tax, estate, leave, inherit, went to or passes to.
    - `SAVINGS_ACCOUNTS`: tax on interest, excluding mortgage interest and interest-only; an account's figure "on file/record", excluding investment and brokerage accounts.
    - `PROPERTY`: tax on rent, a let flat, house or property, mortgage interest.
  - The bare "shortfall" in the plan's wording was narrowed to retirement, pension or income shortfall, because retirement is checked before protection and a bare word would take "protection shortfall".
- **T3, Test (2026-10-12):**
  - `tests/Unit/Services/AI/QueryClassifierTest.php` has Brett's 13 messages with their page and the expected type.
    - Old filings: F19 and M8 were `data_entry`, Q6 and Q7 were `navigation`, F22, P3, Q5 and Q10 were `general`, P7 was `estate_planning` (by page) and Q8 was `tax_optimisation`.
    - Required-tools checks: Q6 and P5 must carry `get_module_analysis(retirement)`.
    - Guards: emergency fund, protection gap, goal saving and savings rate stay out of the new wording; "I have a pension with £50,000", "Take me to estate planning" and "Show me my investments" are filed as before.
  - `IsAdviceShapedTest`: "Show me my investments" is a statement; "show me how the taper works" is a question.
  - Red before: 18 failed. Green after: 127 passed.
  - The files that use the classifier or the one test also pass:
    - 291 passed: `QueryRequiredDataTest`, `OutOfRemitTest`, `FynContextAssemblerTest`, `OnboardingInterruptionTest`, `AssistantHonestyOnWriteFailureTest`, `AdviceFynHandoffErrorTest`, `AdviceFynRoutesWritesViaHandoffTest`, `EvalDeltaBuilderTest`, `WriteIntentClassifierTest`, with the two above;
    - 53 passed: the T2 gate files re-run for the "show me" change.
- **T3, Dev (2026-10-12):** PR #1214 merged to dev (`722ab654d`). csjones pulled dev and cleared caches, then ran `config:cache`. Server PHP only. The site answered 200.
- **T3 follow-up, Code, Test and Dev (2026-10-12):** branch `fix/t3b-p5-pension-pot-tools`, PR #1215, merged to dev as `fbc9ab90c` and deployed to csjones (200).
  - A turn is given only its **primary** type's tools (`AdvicePromptBuilder::buildToolsAndTriggersBlock`).
  - #1214's P5 and Q6 tool tests checked `QuerySchemas::getRequiredToolsForClassification`, which nothing in the app calls. That is a decoy test (test-failure-forensics). They now read the block the turn carries.
  - P5 was red on the real block: it was filed holistic, with no retirement projection.
  - Fix: the "net worth" wording no longer takes a question about a pension, pot or SIPP at a named age ("at, by or until" plus two digits). P5 is now filed under `retirement_contribution`, whose block asks for `get_module_analysis(retirement)`.
  - "What is my net worth including my pension?" stays holistic (new test).
  - 230 passed across the classifier files.

- **T3, Dev walk (2026-10-12, csjones `fbc9ab90c`, as Rory Retest, user 504):** each message is Brett's exact text. The filing was read from each answered turn's stored `assembled_context` (`<required_tools>` is the primary type's tool list) and its tool calls.
  - **Web 1440:**
    1. Typed the front door. The earlier /m visit had dropped the web tab to /login (Q3, T33).
    2. Signed in with email, password and the emailed code (`php zz_code.php 504` on csjones, the R2 exception).
    3. Clicked "Retirement" in the side menu, then "Chat with Fyn", then "New".
    4. **Q6 #4003** ("If I stop work at 60 … how far short am I … Show the working."), filed as `navigation` (no tools) before T3. Now #22772, conversation 588:
       - `<required_tools>` lists `get_module_analysis(retirement)`, `get_tax_information(pension_allowances)` and `get_tax_information(state_pension)`, and the model called all three;
       - the answer uses the page's own headline: projected £25,972.07 a year against £70,000, short by £44,027.93 a year, required capital £1,497,298, matching the page's £25,972 and £1,497,298.
       - Screenshot `shots/T3-01-q6-retirement-shortfall-csjones-web.png`.
    5. Clicked "New". **M8 #3961** ("I have a daughter, she's 27. If I died tomorrow … how much inheritance tax …"), filed as `data_entry` (no tools) before T3. Now #22774:
       - `<required_tools>` lists `get_tax_information(inheritance_tax)`, `get_module_analysis(estate)` and `list_records(property)`, all called;
       - the answer: £0 on a £30,000 estate, and the residence allowance needs a home left to a direct descendant.
       - Screenshot `shots/T3-02-m8-daughter-iht-csjones-web.png`.
  - **/m 390:**
    1. Typed the /m front door, then clicked "Chat with Fyn" (conversation 572).
    2. **Q5 #3997** ("… how is my half of the rent taxed this year? … Figures please."), filed as `general` (no tools) before T3. Now #22776:
       - `<required_tools>` lists `list_records(property)`, which was called;
       - the answer: no property is recorded, so the rent tax cannot be worked out yet. That is correct for Rory.
       - No handoff in the log. Screenshot `shots/T3-03-q5-rent-tax-csjones-m.png`.
    3. **Q8 #4015** ("… if both of us died … what would the death tax bill be …"), filed as `tax_optimisation` before T3. Now #22778:
       - `<required_tools>` is the estate Inheritance Tax list, all called;
       - the answer uses the estate analysis, with the pension pots £50,000 + £15,000 counted for deaths after 6 April 2027.
       - Screenshot `shots/T3-04-q8-death-tax-csjones-m.png`.
  - **Result:** every question was filed under its subject, never as data entry or navigation, and the model received and called that type's tools on both surfaces.
  - **Seen, for T11 (Q6 already listed there):** Fyn gave "a pension pot of about £552,597 at 60". The Retirement page's "Projected Value (middle outcome)" reads £484,572. One figure, two values (`feedback_one_figure_every_surface_fetched`).

- **T4, Code (2026-10-12):** branch `fix/t4-capture-writes-landed`, commit `adf2c0b10`, PR #1216.
  - `ToolResults::wroteData` (`app/Services/AI/ToolResults.php`) is the one definition (Rule 20) of "a write landed". It is true for `created`, `updated` or `deleted`, and for `onboarding_capture` when the result is not asking for clarification and lists nothing as missing.
  - Two `onboarding_capture` shapes write nothing, and both are excluded:
    - `capture_dependants` with `clarification_required` (`CoordinatingAgent.php:2133`);
    - `capture_retirement_goals` with income only (`:6360`, `details.missing = [target_retirement_age]`).
  - `HasAiChat`'s stored `capture_write_landed` uses it; before, it counted only created, updated and deleted.
  - The per-tool `capture_write_result.landed` builds on it and keeps its non-failure outcomes: success, duplicate skip, and a capture still asking. Deletes already carry `success`, so its behaviour is unchanged.
- **T4, Test (2026-10-12):** `tests/Feature/AI/CaptureWriteLandedTest.php`, 6 tests.
  - The real handlers through `CoordinatingAgent::executeTool`:
    - `capture_retirement_goals` with age and income wrote, so it landed;
    - income only parks, so it did not land;
    - `capture_state_pension` with no figures did not land;
    - an error result did not land;
    - `created`, `updated` and `deleted` results landed.
  - An inline capture turn (`handleInlineCapture`, stream harness running `capture_retirement_goals`).
  - Red before: `wroteData` did not exist, and the inline row stored `capture_write_landed: false` although the profile held age 60. That is the #22766 shape. Green after: 6 passed.
  - The files that read the landed flag or the write results also pass, 134 in all: `FynStreamHarnessTest`, `InlineCaptureFlowTest`, `CaptureWriteFailureTest`, `ClarificationAnswerRescueTest`, `AdviceFynCaptureContinuationTest`, `FormGuardQuestionTextTest`, `CaptureContinuationAfterWriteTest`, `CaptureAckTest`, `GroupedExtractAdvanceOnAnsweredTest`, `ParkedFactsFlushTest`, `CaptureTurnAnswersQuestionsTest`, `AdviceFynRoutesWritesViaHandoffTest`, `AdviceFynHandoffErrorTest`, `AssistantHonestyOnWriteFailureTest`, `SpouseSectionNoVerifyPageTest`.
- **T4, Dev (2026-10-12):** PR #1216 merged to dev (`8d53d2b4a`). csjones pulled dev and cleared caches, then ran `config:cache`. Server PHP only. The site answered 200.

- **T4, Dev walk (2026-10-12, csjones `8d53d2b4a`):**
  - **Web 1440, as Rory Retest (user 504):**
    1. Typed the front door. Signed in with email, password and the emailed code (`php zz_code.php 504`, the R2 exception).
    2. Clicked "Chat with Fyn", then "New" (conversation 591).
    3. Typed "Change my retirement income target to £60,000 a year, retiring at 62". Shown: "Recorded — your retirement target is £60,000 a year, starting at age 62", and "Saved to your records: Retirement — retirement goals".
       - The row #22780 (`capture_retirement_goals`) stores **`capture_write_landed: true`**. #22766, the same kind of write before T4, stored `false`.
       - The profile now reads age 62, £60,000.
       - Screenshot `shots/T4-01-retirement-goal-change-csjones-web.png`.
    4. Typed "Thanks, that's all for now". Answered "You're welcome, Rory. Take care." (#22782, `advice`). No continuation and no deterministic route in the log; before T4, the "not landed" row would have pulled it back into capture. Screenshot `shots/T4-02-followup-not-pulled-into-capture-csjones-web.png`.
  - **/m 390, as Rory:**
    1. Typed the /m front door, then clicked "Chat with Fyn".
    2. Typed "Change my retirement income target to £70,373 a year, retiring at 60". This puts back the goal Rory held before the walk.
       - Shown: "Recorded — … £70,373 a year … 60".
       - The row #22784 stores **`capture_write_landed: true`**.
       - Screenshot `shots/T4-03-retirement-goal-change-csjones-m.png`.
    3. The follow-up was refused with "You've reached your daily Fyn usage limit." Rory had used 517,635 of the Free tier's 500,000 daily tokens (`fyn_daily_hard_backstop`), so it never reached the gates. Screenshot `shots/T4-04-…`.
  - **/m 390, follow-up as Alex (user 502, `slaterjoneschris+devluna-a@gmail.com`):**
    1. Opened the /m menu, clicked "Sign out", then signed in as Alex with email, password and the emailed code (`php zz_code.php 502`, the R2 exception).
    2. Clicked "Chat with Fyn" (conversation 592). Typed "Change my retirement income target to £40,000 a year, retiring at 67". Shown: "Recorded — … £40,000 a year … 67".
       - The row #22787 stores **`capture_write_landed: true`**.
       - Alex had no retirement goal before. The walk leaves 67 / £40,000 on this csjones walk account.
       - Screenshot `shots/T4-05-retirement-goal-change-alex-csjones-m.png`.
    3. Typed "Great, thanks". There was no continuation and no deterministic route, so T4 held.
       - The model then called `delegate_to_capture` with **no entity types**. The log shows `[FynLoop] CaptureContext rejected delegate_to_capture payload … entityTypes must not be empty`.
       - The user saw "I couldn't pick up that request — could you try again?", and no reply row was saved (#22788 is the user row only).
       - This is the model's handoff on a non-question (T5, RC4) plus a failed turn that saves nothing (T35, N6). It is the same message as F4.
       - Screenshot `shots/T4-06-followup-not-pulled-into-capture-alex-csjones-m.png`.
  - **Result for T4:** every `capture_*` write walked stores landed `true` on web and /m, and the follow-ups were not pulled back into capture.
  - **Found and added to the list as T45:** the deterministic router filed both goal changes as `entity_type: goal` (matched "target"). The capture model still chose `capture_retirement_goals`.

- **T5, Code (2026-10-12):** branch `fix/t5-handoff-statements-only`, commit `7f9839ec4`, PR #1217.
  - `<handoff_guidance>` now applies when the user **states** an add, change or delete.
  - New paragraph: "A question is never a write. When the user asks something (a calculation, the working behind a figure, which figure is on file, what the rules are, or a question that quotes figures), answer it with your read tools and do not call `delegate_to_capture`. Figures quoted in a question are what the question is about, not records to save. The one exception is a request to run a what-if scenario, which is saved as a scenario: hand it off with entity type `what_if_scenario`." The what-if exception keeps S0.5.r (`AdviceFyn.php:189-193`) and T7.
  - `:156` gains "Asking which figure is on file is a question: look the record up and answer." (Q10).
  - Rule 20: the block's one home is `FynSystemPrompt::handoffGuidance()`. `AdvicePromptBuilder::getHandoffGuidance` (legacy) had its own longer copy and now returns it.
  - The snapshot `docs/superpowers/specs/fyn-system-prompt.snapshot.txt` was regenerated; its diff is the two changed lines only.
- **T5, Test (2026-10-12):**
  - `tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php` gains three tests: the statements-only rule and what-if exception, the legacy builder returning the same block, and the Q10 sentence. Red before (`handoffGuidance()` undefined; sentence missing), green after.
  - Found in the path and fixed: `tests/Feature/AI/PromptOverlayGoldenMasterTest.php` was red on dev before T5 (checked with T5 stashed: 4 failed). Its fixtures had not been recaptured after two earlier changes:
    - T1 now keeps the user's "?" in `<user_message>`;
    - the monthly surplus line was added to `<user_profile>`.
  - The fixtures were recaptured with `CAPTURE_PROMPT_OVERLAY_GOLDEN=1`. The decoded diff is exactly those lines.
  - The surplus line shows "£0.00", which T15 (exact money) will change.
  - 180 passed across the prompt and builder files: `FynSystemPromptTest`, `Rule9NoAcronymsInFynVocabularyTest`, `PromptOverlayGoldenMasterTest`, `AdvicePromptToolCoherenceTest`, `UnifiedPromptAdviceSeamTest`, `UnifiedPromptOnboardingSeamTest`, `FynContextAssemblerTest`, `WriteClaimFilterTest`, `ModelHistoryExcludesDeadEndRowsTest`, `IsaOnboardingCaptureTest`, `AdviceQuestionScopeTest`, `FcaSignpostingTest`, `ModulePathFinancialContextTest`, `ModuleScopedFinancialContextTest`, `SavingsReadConsumerParityTest`, `AdvicePromptBuilderStructuralLayersTest`, `AdvicePromptJointOwnerLazyLoadTest`, `FynIncomeFromIncomeDefinitionsTest`, `FynMonthlySurplusLineTest`, `KnownFactsBlockTest`, `DependantsReachConsumersTest`.
- **T5, Dev (2026-10-12):** PR #1217 merged to dev (`54da9f1ca`). csjones pulled dev and cleared caches, then ran `config:cache`. Server PHP only. The site answered 200.
- **T5, first Dev walk (2026-10-12, csjones `54da9f1ca`, as Alex 502): NOT green.**
  - /m Q7 #4013 was answered with no capture. Two new problems in the answer, below.
  - /m P2 #3965 was answered with no capture.
  - /m "Great, thanks" got a plain reply.
  - **Web 1440:** "What is 6% + 8% of £72,000 a year? And is a 50/50 split the same as half & half?" was still handed to capture by gpt-6-luna. The log shows `LLM-fallback write-intent caught … work_details`, with the reason "a possible annual income figure that may be a correction". A blank work form opened (conversation 594, #22796). Screenshot `shots/T5-04-arithmetic-question-answered-csjones-web.png` shows the failure.
  - The prompt wording alone does not hold. Write safety belongs at dispatch, not in the prompt (canonical contract, fyn-architecture skill).
- **T5 follow-up, Code, Test and Dev (2026-10-12):** branch `fix/t5b-question-handoff-gate`, commit `888d5d38b`, PR #1218, merged to dev as `e9cf1f21c` and deployed to csjones (200).
  - `CoordinatingAgent::executeTool('delegate_to_capture')` asks the one question test (`QuerySchemas::isAdviceShaped`) about the in-flight user message (`latestUserMessageText`).
  - For a question it returns `{handoff_refused: true, message: "Not handed to capture: the user asked a question, and nothing is saved. Answer the question now …"}` and sends no handoff event, so the turn continues as an answer.
  - A what-if handoff (entity types `what_if_scenario` only) and a stated change still hand off.
  - Test: `tests/Feature/AI/QuestionNotHandedToCaptureTest.php` (stream harness: planner, `delegate_to_capture`, then text).
    - The question is answered by the advice turn. Red with the change stashed (the reply came from capture); green after.
    - A stated salary change and a what-if still hand off.
  - 136 passed across the handoff files: `AdviceOffersHandoffToolTest`, `AdviceFynHandoffErrorTest`, `AdviceFynRoutesWritesViaHandoffTest`, `AdviceFynToolListTest`, `InlineCaptureFlowTest`, `CaptureStateToolCoverageTest`, `OnboardingInterruptionTest`, `AdviceFynProposalAcceptanceTest`, `WriteClaimFilterTest`, `InlineCaptureGamificationAwardTest`, `CaptureContextTest`, `QuestionNotHandedToCaptureTest`, `TypedChangeFillsFormTest`, `CaptureWriteLandedTest`, `FynStreamHarnessTest`.
- **T5, Dev walk (2026-10-12, repeated from step 1 after the gate; csjones `e9cf1f21c`, as Alex 502):**
  - **Web 1440:**
    1. Typed the front door, clicked "Sign in", and signed in with email, password and the emailed code (`php zz_code.php 502`, the R2 exception).
    2. Clicked "Chat with Fyn", then "New" (conversation 595).
    3. Typed "What is 6% + 8% of £72,000 a year? And is a 50/50 split the same as half & half?", which opened a work form on the first walk.
       - Answered: "6% of £72,000 is £4,320 a year. 8% is £5,760 a year. Together, 6% + 8% = 14%, or £10,080 a year", and 50/50 is half and half. All correct.
       - #22798 is `advice` (`get_tax_information`). No form opened.
       - Screenshot `shots/T5-05-arithmetic-question-answered-after-gate-csjones-web.png`.
    4. **Q10 #4033** ("… Which figure do you have on file, and which should it be?"). Answered from the records: no Chase account on file; the joint Santander £24,000 (Alex's share £12,000) is the only savings account.
       - #22800 is `advice` (`list_records`, `get_tax_information`). No form opened.
       - Screenshot `shots/T5-06-q10-which-figure-on-file-csjones-web.png`.
  - **/m 390:**
    1. Typed the /m front door and clicked "Chat with Fyn" (conversation 592).
    2. **Q7 #4013**: answered (#22802, `advice`, `get_module_analysis` and `get_tax_information`). No capture. Screenshot `shots/T5-07-q7-answered-after-gate-csjones-m.png`.
    3. **P2 #3965**: answered (#22804, `advice`). No capture. Screenshot `shots/T5-08-p2-answered-after-gate-csjones-m.png`.
    4. "Great, thanks": "You're welcome, Alex. Is there anything else you'd like to work through?" (#22806, `advice`). Screenshot `shots/T5-09-great-thanks-after-gate-csjones-m.png`.
  - The log shows no `LLM-fallback write-intent caught` and no `CaptureContext rejected` for any of these turns.
  - The gate was not triggered on this walk: the model attempted no handoff, and no stored result carries `handoff_refused`. `QuestionNotHandedToCaptureTest` covers the gate; the first walk showed the model does attempt it.
  - **Result:** every question walked was answered, not handed to capture, on web and /m.
- **T46, more evidence (T5 re-walk, /m P2 #22804):**
  - "Gift the savings to Dev … your saving would be **£0**." It should be **£484**: all £1,710 of Alex's interest goes to Dev, so the tax on the £1,210 above Alex's £500 allowance (40%) goes too.
  - "Move £12,000 into your ISA … your £500 allowance would cover the first £500 of your interest, leaving £40 taxable … saving £468 a year." The ISA interest is tax-free and does not use the Personal Savings Allowance (ITTOIA 2005 s694; ISA Regulations 1998). The £1,170 left outside the ISA leaves £670 taxable and £268 tax, so the saving is **£216**.
  - The joint figure (£342) was right.

- **Found on the first T5 walk, added to the list (R4):**
  - **T46:** /m Q7 #4013 gave "about £478 a month over 19 years at 5%". On Fyn's own stated basis (5% a year compounded monthly, 228 months), building £186,975 takes **£492.89** a month, and £478 builds only £181,328 (checked in Python). Fyn has no calculation tool; every figure from the user's own numbers is the model's arithmetic. Rule 23: a wrong figure a user can see is never minor.
  - **T47:** the same answer printed the formula as raw LaTeX (`\(\frac{(1+0.05/12)^{228}-1}{…}\)`) on /m.
- **Seen on the first T5 walk, for T10/T12 (F5's family):** /m P2 #3965 (Alex) gave "£0 a year" for both the gift and the joint account. Alex's own tax on the excess (£1,210 × 40% = £484) falls either way. The message names "Dev", who is Brett's husband, not Alex's spouse.
- **T48, Code (2026-10-12, session 4):** branch `fix/t48-one-fyn-limit`, commit `9e2d2c990`, PR #1222, merged to dev as `46b402737`.
  - `HasAiGuardrails`: the tier's `fyn_weekly_token_budget` over the rolling week is the one limit (`isWeeklyBudgetExceeded`, `hasTokenBudget`). `getTokenUsageDetails` reports the week; `reset_at` is the first midnight at which the week's total falls under the limit (`weeklyResetAt`).
  - Removed: `isDailyBackstopExceeded`, `SOFT_DEGRADE_MODEL`, the degrade branch in `getAiModel`, `degrade_chat_model` (`config/services.php`), and the "lighter mode" prompt notice (`HasAiChat`).
  - `HasAiChat` sends "You've reached your weekly Fyn usage limit."; the web panel shows the server's message and counts down in days; /m and iOS fallbacks say weekly.
  - Migration `2026_10_12_120000_one_weekly_fyn_limit_per_tier` sets Free 750,000 and Premium 3,000,000 and drops `fyn_daily_hard_backstop`. The seeder carries the values for fresh installs.
  - The field is removed from the model, store, resource, subscription status, admin request and admin page. The pricing page's static fallback now reads 750,000 and 3,000,000 (`pricing.js` already overwrites it from the tier rows).
- **T48, Test (2026-10-12):**
  - Red first: `FynMeteringTest` (rewritten) and `OneWeeklyFynLimitTest` (new), 11 failed for the right reasons. For example, 750,000 in a week still passed, and 600,000 in one day tripped the old daily stop.
  - Green: those two plus `TokenBudgetConcurrencyTest`, `ApprovedTwoTierMatrixTest`, `TierConfigurationSeederTest`, `TierConfigurationsTableTest`, `SubscriptionStatusServiceTest`, `TierConfigBoundaryTest` and `TierConfigurationControllerTest`: 63 passed.
  - JS: `AiChatPanel.tokenLimit.spec.js` and `aiChatTokenLimit.test.js` (new) were red on the old panel ("52h 2m", no message), then green. `onboardingChatEvents.test.js`, `aiChatEvents.test.js` and the three `AiChatPanel.*.spec.js` are green.
- **T48, Dev (2026-10-12): NOT DONE.** csjones pulled `46b402737`. The auto-mode classifier refused `php artisan migrate --force` on csjones ("Remote Shell Writes") and the local `deploy/csjones-fynla/build.sh` ("Production Deploy"). So csjones runs the new code with the old values (Free 100,000 a week, now a stop) until the migration runs. Waiting on CSJ.
  - CSJ ran the migration and the cache clear (2026-10-12): `2026_10_12_120000_one_weekly_fyn_limit_per_tier` DONE. A read on csjones gives free 750,000 and premium 3,000,000, and `fyn_daily_hard_backstop` is gone. The site answers 200.
  - Both bundles were built by CSJ at 12:00 BST from the T48 worktree. That tree is identical to dev `46b402737` (`git diff --quiet`). They were uploaded with rsync and no `--delete`, so the old chunks stay. Both bundles carry the new strings.
- **T48, Dev walk (2026-10-12, csjones `46b402737`, as Rory Retest 504, Free):** before the walk, his rolling week was 593,524 (517,635 today, above the old 500,000 daily stop).
  1. Web 1440: typed https://csjones.co/fynla/, signed out Alex, signed in as Rory (code read from the csjones database, the R2 exception), and clicked "Chat with Fyn". Asked "How much is in my pension at the moment?" and got an answer (£50,000). Then asked two more questions, both answered. Shot `T48-01-web-rory-answered-past-old-daily-stop.png`.
  2. /m 390: typed the /m entry and clicked "Chat with Fyn". Asked "How much tax would I save if I put more into my pension this year?" and got an answer. Shot `T48-02-…`. The week then stood at 727,102.
  3. /m: one more question was answered, and the week reached 755,902. "And what about my ISA allowance this year?" then showed "You've reached your weekly Fyn usage limit." Shot `T48-03-m-weekly-limit-message.png`.
  4. Web: signed in again (opening /m signs web out, Q3/T33), clicked "Chat with Fyn" and asked "What is my net worth?". The notice read "You've reached your weekly Fyn usage limit. Your allowance resets in 3d 11h", and the input was disabled with "Fyn usage limit reached — resets in 3d 11h". Shot `T48-04-web-weekly-limit-notice.png`.
     - The reset is correct. csjones runs on Europe/London, and 12:08 BST Monday to 00:00 Friday 16 October is 3d 11h 52m. Then 9 October (75,889) leaves the window, and 680,013 is under 750,000.
     - The stopped turns charged nothing: the week stayed at 755,902.
  - The admin Tier Configuration page was not walked. CSJ (2026-10-12): "dont worry about ttesting these limits now, we will get top that lets move on".
  - Found: on /m, Fyn's paragraphs run together without a space ("£5,800.Your recorded…", "…this year.Would you like…"). New task T49.
- **T6, Code (2026-10-12, session 4):** branch `fix/t6-server-navigation`, commit `3b1c21dc3`, PR #1224, merged to dev as `bc69c68ed`.
  - `App\Services\AI\NavigationCommand::match`:
    - only a plain command ("take me to", "go to", "open", "navigate to", "show me", "bring up") with no question navigates (`?` or `QuerySchemas::isAdviceShaped`);
    - the whole rest of the message must be a page name from `GateRoutes::NAVIGATION_NAMES`, so words match whole;
    - Tax Strategy is covered.
  - `NavigationCommand::handle` runs before the director and Advice Fyn in both `AiChatController` dispatches (send and queued). It saves the request and the reply ("Opening Tax Strategy.") and emits the existing `navigation` event, with the surface's own route and a semantic `destination`. On /m and native, a website-only page gets "Will Builder is only on the website for now." and no navigation.
  - Surface: `X-Fynla-Client: ios` is native, `X-Fynla-Surface: m` is /m (new, sent by the /m `apiStream`), and anything else is web.
  - `GateRoutes::MAP` gains the pages the old web matcher opened: actions, security, planning assumptions, letter to spouse, will builder, power of attorney, trusts, life events, the four plans, journeys and what-if. The web and /m destination tables carry them, and `GateRoutesTest` checks every new path against both routers.
  - `chatNavigationRouter.js` and its test are deleted, and its cases move to `NavigationCommandTest`. Native gets the /m path; `FynEventReducer.allowedRoute` opens only the screens it lists (confirm with the next iOS build).
- **T6, Test (2026-10-12):**
  - Red first: `ServerNavigationCommandTest` (6 failed: no `navigation` event, F4's reply missing) and `NavigationCommandTest` (class and pages missing). On the JS side, the web and /m destination specs failed (new screens fell back to the dashboard), and `apiStreamSurfaceHeader.test.js` failed (no header).
  - Green: `NavigationCommandTest` (29 cases), `ServerNavigationCommandTest`, `GateRoutesTest`, `AdviceDeclaredNoneTest` and `ConcurrentTurnQueueGateTest` pass, 62 in all. JS: 9 files, 53 passed, including every `AiChatPanel.*` spec and `aiChatEvents`.
- **T6, Dev (2026-10-12):** csjones pulled `bc69c68ed` (PHP live, no migration).
  - CSJ built both bundles at 12:41 BST from the T6 worktree, whose tree is identical to dev `bc69c68ed`. They were uploaded with rsync and no `--delete`.
  - The /m bundle carries `X-Fynla-Surface`, and the web bundle no longer has the matcher. The site answers 200.
- **T6, Dev walk (2026-10-12, csjones `bc69c68ed`, as Alex 502, Free):**
  1. Web 1440: typed https://csjones.co/fynla/, clicked "Sign in" and signed in (code read from the csjones database, the R2 exception). Clicked "Chat with Fyn".
     - F4's exact words, "ok take me to my tax strategy": the page went to `/tax-strategy`, and Fyn replied "Opening Tax Strategy." Shot `T6-01-…`.
     - "take me to my savings" went to `/net-worth/cash` with "Opening Bank Accounts." Shot `T6-02-…`.
     - F3c-style "My retirement page says I will have about £300,000 at 60. Show the working." did not navigate (stayed on `/net-worth/cash`), and Fyn answered it. Shot `T6-03-…`.
     - "open the will builder" replied "Opening Will Builder." and the router's existing Premium guard sent this Free account to `/teaser?module=estate`. Shot `T6-04-…`.
  2. /m 390: typed the /m entry and clicked "Chat with Fyn".
     - "open the will builder" replied "Will Builder is only on the website for now." and did not navigate. Shot `T6-05-…`.
     - "take me to my savings" closed Fyn and opened Bank Accounts (`/savings`). Shot `T6-06-…`.
     - Reopened Fyn: the turns were there. "ok take me to my tax strategy" opened Tax Strategy. Shot `T6-07-…`.
  3. Saved rows (read on csjones): web conversation 600 (#22819 to #22826) and /m conversation 601 (#22827 to #22832). Every request has its reply.
  - Found (for T11): the F3c-style answer gave "£1,290,042 at retirement" and "a total defined contribution value of £531,047 across the projection", neither of which is the Retirement page's figure, and said it could not show the working. That is T11 and published working (B3).
- **T7 (2026-10-12, session 4):** T5's dispatch gate (#1218) already gives a what-if request its designed route (S0.5.r). A pension handoff on the question is refused back to the model, and a `what_if_scenario` handoff saves the scenario through `create_what_if_scenario`. T7 adds no production code.
  - **Code:** branch `fix/t7-what-if-route`, PR #1226, merged to dev as `5a1c93fa8`. It adds `WhatIfRequestReachesScenarioCaptureTest`.
  - **Test:** red with the T5 gate disabled: "never opens a pension form" failed, because the request left Advice Fyn for pension capture. Green on dev (2 passed).
  - **Dev:** csjones pulled `5a1c93fa8`. The PR is test only, with no bundle change.
  - **Dev walk (csjones, as Alex 502):**
    1. Web 1440: signed in, clicked "Chat with Fyn" and sent P5's request ("Run a what-if for me: if I paid an extra £20,000 a year into my pension from now until 60, …"). No pension form opened. Fyn replied "Your what-if scenario is saved", with a "Saved to your records … View" card. Scenario 1 was saved (`created_via: ai_chat`), and the page went to `/teaser?module=what_if` (the Premium guard; T51). Shot `T7-01-…`.
    2. /m 390: clicked "Chat with Fyn" and sent "Run a what-if for me: if I paid an extra £10,000 a year into my pension from now until 62, …". No pension form. Scenario 2 was saved (#22836, persona `data_capture`). Shot `T7-02-…`.
  - Found: on both surfaces Fyn said the comparison "shows £0 for both projections". New task T50. A Free user can create the paid-plan scenario: new task T51.
- **T8, Code (2026-10-12, session 4):** three PRs, all merged to dev.
  1. #1228 (`8734153eb`): the delete preview looks the record up the way each delete branch does (`deletableRecordName`). A missing record gets `not_found` and no token, and an existing one is named.
  2. #1229 (`c83b1bd03`): the first walk showed the model's steer in the saved reply (#22838). The steer moves to an `instruction` key.
  3. #1230 (`69365a26d`): the second walk showed that the capture model has no read tool, so it guessed goal 0, and the reply read "I couldn't save that — … Give me the missing detail".
     - The not-found result now carries `saved_records` for the model to retry with a real id (B2.7), plus a `user_facing` sentence: none saved, or "Which goal did you mean: A or B?".
     - `OnboardingChatDirector::captureFailureText` shows a `user_facing` message as written, as it does a tier-limit message.
     - `recordName` and `deletableModelClass` are the one home for the name and the model lookup.
- **T8, Test (2026-10-12):** each PR was red first, then green.
  - `DeleteRecordConfirmationTest` gained 6 tests.
  - `DeleteMissingRecordReplyTest` is new; it is also red with only the director change reverted.
  - With `RecordEditPathwayTest`, `TrustSettlementWithholdsTheEstateBandTest` and the five failure-text files: 150 passed.
- **T8, Dev (2026-10-12):** csjones pulled `69365a26d` (PHP only).
- **T8, Dev walk (2026-10-12, repeated from step 1 after each fix):**
  1. Web 1440, as Alex 502 (no goals saved): clicked "Chat with Fyn" and "New", then sent "Delete my round-the-world trip goal". No confirmation was asked. The screen said "There isn't a saved round-the-world trip goal to delete.", and the saved reply (#22842) is "You don't have a goal saved, so there's nothing to delete." Shot `T8-01-…`.
  2. /m 390: Alex went over the 750,000 weekly limit mid-walk ("You've reached your weekly Fyn usage limit.", T48 as designed), so I signed out through the /m menu and signed in as Drew 508 (code read from the csjones database). Clicked "Chat with Fyn" and sent the same message. No confirmation; saved reply #22845 is "You don't have a goal saved, so there's nothing to delete." Shot `T8-02-…`.
  - Found: the live sentence differs from the saved reply on a capture failure; on /m it said the goal "remains unchanged", implying it exists. New task T52.
- **T9, Code (2026-10-12, session 4):**
  - #1232 (`c0a207ce9`):
    - `WriteIntentClassifier` files invite, invitation or link accounts plus a spouse word as `spouse_invite` (a question is still answered, T2).
    - `OnboardingChatDirector::inviteSpouseFromChat` runs the invite step's own code: `parseSpouseInviteDetails`; the spouse's name on file when only an email is given; `sendSpouseInvitation`; and the step's outcome sentence. Missing details get the step's own question, and the answer continues the capture.
    - `parseSpouseInviteDetails` no longer reads "Please" as the first name.
  - #1233 (`cb78008e5`), security: the background review found the chat path had no rate limit. Family settings allows 5 spouse invitations an hour (W-0349). The agent's spouse tools, the campaign step and the onboarding save had no limit either, and a demo persona could send real invitations through chat.
    - `SpouseLinkingService::guardInviteRate` is now the one limit for every entry point.
    - `sendSpouseInvitation` refuses a demo persona.
- **T9, Test (2026-10-12):** both PRs were red first, then green.
  - #1232: 7 failed (classifier, parser, both chat cases). With the campaign invite and state machine tests, 214 passed.
  - #1233: 3 failed (an invitation over the limit, a demo persona's invitation row, the spouse tool over the limit). With the Family settings 429 test and the spouse-link files, 184 passed.
  - CSJ asked "why are you guessing?" after the first chat test asserted a row the code does not write. The assertion now reads `SpouseInvitation` (`SpouseLinkingService.php:452-462`). Memory: `feedback_read_before_asserting_never_guess`.
- **T9, Dev (2026-10-12):** csjones pulled `cb78008e5` (PHP only).
- **T9, Dev walk (2026-10-12, as Drew 508):**
  1. /m 390, before the security fix: "Please invite my partner to link accounts" got "What's their first name and email address?". "Sam, slaterjoneschris+t9walk@gmail.com" got "Done. I've sent an invitation to … When Sam accepts …" (invitation 13).
  2. After the fix, repeated from step 1:
     - /m: "Please invite my partner to link accounts. Their email is slaterjoneschris+t9walk2@gmail.com." got "Done. …", naming Sam from the spouse record (invitation 14, #22851). Shot `T9-01-…`.
     - Web 1440: signed in from the front page (code read from the csjones database) and clicked "Chat with Fyn". "Invite my husband to link accounts" got the step's question; "Sam, slaterjoneschris+t9walk3@gmail.com" got "Done. …" (invitation 15). Shot `T9-02-…`.
  - Side effect, as the linking service documents: Drew's marital status became married (`inviteUnregisteredSpouse`).
  - The rate limit and the demo-persona refusal are covered by tests, not walked: walking the limit would send 5 more emails.

- **T10, Code (2026-10-12, session 5):** #1236 (`db75edded`), branch `fix/t10-spouse-figures-in-context`.
  - `FynContextAssembler::savedHouseholdFinancesDirective` no longer waits for a spouse word plus a finance word (the old gate at `:580-584`). The household row reaches every advice turn it exists for; onboarding turns are unchanged.
  - The block names the spouse (`- Spouse: <name>`) from `SpouseJointRecords::spouseFirstName`, made public as the one home for the name (linked account first, then the spouse row).
  - It now also carries the row's earnings from work, dividends and unrealised gains, which were saved but never shown to Fyn.
  - The ".00" money format is left for T15.
- **T10, Test (2026-10-12):** `FynContextAssemblerTest` "grounds every advice turn in the household row, naming the spouse, with no relationship word" failed first (no `<saved_household_finances>` for "How much tax does Dev pay on the interest?"), then passed: 29 passed. `SpouseJointRecordsTest` and `PromptOverlayGoldenMasterTest`: 14 passed, 1 skipped.
- **T10, Dev (2026-10-12):** csjones pulled `db75edded` (PHP only, no bundle rebuild); caches cleared, `config:cache`; the site answered 200.
- **T10, Dev walk (2026-10-12, as Pat 506, household row: Rory, income £72,765, earnings £72,000):**
  1. Web 1440: typed the front door, clicked "Sign in", signed in with email, password and the emailed code (`php zz_code.php 506`, the R2 exception), clicked "Expand Fyn chat". "What annual income do you have saved for Rory?" (no relationship word) got "Rory's saved annual income is £72,765. Of that, £72,000 is from work." (#22857). No record tool ran; £72,000 is the row's earnings, a field Fyn was never given before. The model's handoff attempt was refused by T5's gate. Shot `T10-01-rory-income-web-csjones.png`.
  2. /m 390: typed the /m entry, clicked "Chat with Fyn". "How much does Rory earn from work, and what dividends and gains does Rory have on record?" got "Rory earns £72,000 a year from work. The household record shows £72,765 total annual income …" (#22859). Dividends and gains are empty on the row and were not invented. Shot `T10-02-rory-earnings-m-csjones.png`.
  - Found: T53 (Fyn's record tools cannot read the linked spouse's own records).

- **T11, Code (2026-10-12, session 5):** #1238 (`61e7d40ed`), branch `fix/t11-retirement-plan-headline`.
  - `RetirementPlanHandler` sends `retirement_headline` (`RetirementHeadline::for`, the one home the Retirement page, dashboard and cards read) beside `composed_retirement_plan`, through a new `ModulePlanHandler::alongside()` hook. Other module plans are unchanged.
  - B3's second half already held: `retirement_readiness` prescribes `get_module_analysis(retirement)` (`QuerySchemas.php:510`), whose summary already carries the headline (`RetirementAgent::withHeadline`).
  - Checked before building (csjones): the T3 walk's £552,597 (Rory 504) and the T6 walk's £1,290,042 (Alex 502) are each user's `headline.dc_value_at_retirement`, which is the Retirement page's "Projected Capital" (`PensionList.vue:239-240`). The £484,572 compared on the T3 walk is the page's separate Monte Carlo card, "Projected Value (middle outcome)" (`PensionList.vue:382-383`, `RetirementProjectionService::projectPensionPot`). So Fyn's figure was a page figure; the defect was that the plan tool carried none.
- **T11, Test (2026-10-12):** `RetirementHeadlineTest` "carries the headline in the retirement plan Fyn fetches" failed first (undefined `retirement_headline`), then passed. With `ModulePlanHandlerTest`: 10 passed. `PensioncheckSectionAdviceTest`: 7 passed.
- **T11, Dev (2026-10-12):** csjones pulled `61e7d40ed` (PHP only); caches cleared, `config:cache`; the site answered 200.
- **T11, Dev walk (2026-10-12, as Pat 506; headline: £260,978.89 at 67, income £12,266.01, required capital £491,170.21):**
  1. /m 390, continuing from T10: "What does my retirement plan say my pension pot will be worth when I retire, and what income does that give me?" called `get_module_analysis(retirement)` (the prescribed tool) and said "£260,979 at age 67 … £12,266 a year" (#22861). It also quoted "£8,690 a year" from the other projection: T54. Shot `T11-01-pot-at-retirement-m-csjones.png`.
  2. Web 1440: signed in again from the front door (code by `php zz_code.php 506`, the R2 exception; /m had signed the web tab out, T33). Clicked "Retirement" in the side nav: "Projected Capital £260,979", "Required Capital £491,170", "Projected Value (middle outcome) £212,213". Shot `T11-02-retirement-page-projected-capital-web-csjones.png`.
  3. Clicked "Expand Fyn chat" on that page: "Which actions does my retirement plan recommend, and what is my projected capital at retirement?" called `fetch_retirement_plan`, then `get_module_analysis` (#22863): "Your projected capital at retirement is £260,978.89 … £491,170.21 as the capital required … £230,191.32 below that figure". Each figure is the page's; the gap adds up. Shot `T11-03-plan-projected-capital-web-csjones.png`.
  - The new fetch path was exercised on web; on /m the model chose the prescribed tool, which already carried the headline. Both surfaces post to the one endpoint, so the handler is the same.
  - Found: T54 (two projections in one tool result).
  - Found (for T15): on /m Fyn rounded £260,978.89 to "£260,979" (#22861), against the exact-money rule; on web it gave the pence (#22863).

- **T5 baseline (seen on the T1 walk, 2026-10-12, csjones):** three questions were handed to capture by the model. The csjones log shows "[FynLoop] LLM-fallback write-intent caught (classifier miss)" for each:
  - the invite question, as `spouse`;
  - "Quick check: if my employer and I pay 6% + 8% of a £72,000 salary, how much is that a year? And does a 50/50 joint account mean half the interest is mine?", as `spouse` (the same conversation as the invite);
  - "What is 6% + 8% of £72,000 a year? And is a 50/50 split the same as half & half?" in a new chat, as `dc_pension`. The model's stated reason: "Rory has stated a possible pension contribution rate and asks for a calculation; capture the pension contribution detail…".
  - Screenshots: `shots/T1-01-…`, `shots/T1-02-…`.
  - The same kind of handoff happened before T1: on 2026-10-08, "How much do I have left over each month after my spending and pension?" was handed off.
  - Before T1, 19 of 20 csjones questions since 5 October were answered by the model. Those were all questions about plan figures, and the one repeated word for word after T1 was still answered.
  - **More on the T2 walk (2026-10-12, /m, conversation 572):**
    - Q7 #4013 went to `retirement_goals`. Model reason: "these figures are not in the stored records and need capturing before analysis". It wrote age 60 and £70,373.
    - P2 #3965 went to `savings_account`. Model reason: "the saved savings record needs updating or confirming". A savings form opened, filled in.
  - **More on the T4 walk (2026-10-12, /m, Alex 502, conversation 592):** "Great, thanks", sent after a landed capture, was handed to capture by the model with an empty `entity_types`. `CaptureContext` rejected it. The user saw "I couldn't pick up that request" (F4's message), and no reply row was saved (T35).
- **T4 baseline (T2 walk, 2026-10-12):** #22766 ran `capture_retirement_goals`, which wrote the goal, but is stored with `capture_write_landed: false` and `capture_record_id: null` (N4).

## 1. How each finding was checked

The production code is the code Brett walked on. fynla.org runs main `f45bc67fd` (release ab). The production copies of `OnboardingChatDirector.php`, `AdviceFyn.php`, `AiChatController.php` and `FynSystemPrompt.php` have the same checksums as main.

Dev is `f00076906`, with the walk fixes R19–R39 not yet released. Every finding was also checked against dev.

Each finding rests on one or more of these checks. The code citations are `file:line` on dev.

- **P: the production record.** These are the stored conversation rows for Brett's three accounts: Priya 812, Tom 813 and Dev 814. They carry per-message metadata, the tools called with their inputs and results, and the stored `assembled_context` (the exact user turn the model received). They were read through the production SSH tool, along with the property, mortgage and pension rows.
- **R: a replay test I ran.** Brett's exact production messages were run through the deterministic code (results in section 8):
  - `WriteIntentClassifier`, `OnboardingChatDirector::isCompletionDeclaration`, `QuerySchemas::isAdviceShaped` and `QueryClassifier::classify`, in PHP against the local app;
  - the web chat's `matchNavigationIntent`, in node.
- **C: a code trace.** The path was read from the entry point to the line that caused it.
- **L: the law.** IHTA 1984 s199 and s204(8), and ITA 2007 s414(2)(b), were fetched from legislation.gov.uk.

I ran no live browser reproductions. A finding that needs one says **I COULD NOT TEST THIS**.

## 2. How a question is meant to be answered, and what stops it

### The answer process, from the code

1. **Dispatch.** `AiChatController::sendMessage` sends a completed user's message to `AdviceFyn::handleTurn`.
2. **Classification.** `QueryClassifier::classify` (`app/Services/AI/QueryClassifier.php:65`) files the question under a type in `app/Constants/QuerySchemas.php`, such as `retirement_readiness`, `estate_iht`, `savings_accounts` or `tax_optimisation`.
3. **What the type prescribes.**
   - `REQUIRED_TOOLS` (`:471`): for example, `retirement_readiness` must call `get_module_analysis(retirement)`, which carries the projection headline and shortfall.
   - `RELEVANT_TRIGGERS`, which name the decision-tree results to cite.
   - `REQUIRED_DATA` (`:136`), the readiness check.
   - The response mode and engine call level (`AdviceFyn.php:58-123`).
   - The FCA process.
4. **Context.** `FynContextAssembler::build` writes this into the turn: the tools and triggers block (`:278`), the financial context, the existing records, and the plan and household grounding.
5. **The answer.** `FynLoop` runs the model in read-only advice mode. `StructuredResponseValidator` checks the answer, and every surface renders it the same way (`fynListItems.js`, `renderFynText`).

**When a question reaches this process clean, the answers are excellent.** Brett's best answers were the three ranked actions (#4018), the spouse-exemption explanation (#3976) and the SIPP limit (#3941).

The causes below are all things that stop a question reaching the process, misfile it, or leave the process without a figure the app already has.

### RC1. Fyn never sees the user's exact words

`FynContextAssembler.php:347` writes the current message into `<user_message>` through `UserContentSanitiser::clean()`. Then `HasAiChat.php:1790-1795` swaps the real user turn for that block. `clean()` (`UserContentSanitiser.php:66`) strips these characters:

`? @ + / : = * & ( ) " $ # [ ] ; | \ ` ~ ^ < > { }`

The production record shows the effect (P):
- message #4060 reached the model as "isenbretdev1011gmail.com";
- message #4002 reached it with its question mark removed.

**Every question reaches the model without its "?".** The sanitiser was written for record names placed into the system prompt (S0.10), not for the user's own message.

### RC2. Gates before the process turn questions into writes or navigation

- **(a) The "I have none of those" gate fires on questions.** `AdviceFyn::recordDeclaredNone` (`:658`) runs first and has no question check. `isCompletionDeclaration` (`OnboardingChatDirector.php:8128-8142`) matches "nothing else" anywhere.
- **(b) The write classifier misses questions in the middle of a message.** `WriteIntentClassifier::looksLikeQuestion` (`:348-361`) counts a message as a question only if it ends in "?" or starts with a question word. Verbs inside a question then trigger a write:
  - "I pay", "I own", "we've got";
  - "record", even in "nothing to record".
- **(c) The follow-up rule pulls the next message back into capture.** `captureContinuationIntent` (`AdviceFyn.php:737-806`) does this when the last capture "didn't land". The "landed" flag (`HasAiChat.php:1529-1535`) counts only `created`/`updated`/`deleted`, but every `capture_*` tool returns `onboarding_capture`. So every retirement, State Pension or spouse write is marked not landed (P: #3878, #3964, #4006, #4008, #4014).
- **(d) Web navigation runs in the browser, before anything reaches the server.** `resources/js/utils/chatNavigationRouter.js`, called from `AiChatPanel.vue:1193` and `:1231`:
  - "show" counts as a trigger;
  - keywords match as substrings;
  - the reply is never sent to the server and is missing from History;
  - it has no route for the tax strategy page;
  - /m and iOS have no equivalent, and advice mode has no navigation tool (`AdviceFyn.php:195-203`).

**A question detector already exists for exactly this.** `QuerySchemas::isAdviceShaped` (`:851`) is documented as deciding "whether a user turn is asking for analysis as well as volunteering data". It has no callers. R: it already flags 15 of Brett's swallowed questions and correctly passes the genuine writes. It misses only "give me…", "show the working", "work it through" and "which wins" (P1, P2, Q7b).

### RC3. The answer process misfiles questions, so the prescribed tools never run

R: `QueryClassifier::classify` on Brett's exact messages:

- **Q6 and Q7, the retirement shortfall, are filed as `navigation`.** `KEYWORD_PATTERNS[NAVIGATION]` (`QuerySchemas.php:261`) is `/\b(take|go|navigate|show)\s+(me\s+)?(to|the)\b/`, and "**Show the** working" matches it. Navigation is a bypass type (`BYPASS_TYPES`, `:161`): no required tools, no triggers, no FCA process.
- **M8 ("I have a daughter… how much inheritance tax") and F19 ("I've got death in service… would the family be ok?") are filed as `data_entry`.** That type is checked first (`QueryClassifier.php:73`) on `i have (a|an|my)` / `i've got` (`QuerySchemas.php:246-259`), and is also a bypass type.
- **F22 ("retire at 60… can we afford it… put away each month") matches no retirement pattern.** It falls back to the page the user is on (`QueryClassifier.php:115`), so it became `protection_cover` on /protection and `general` with no page. Q6 without "Show the working" is `general` too.
- **P3 (tax on interest under three owners), Q5 (rent tax) and Q10 (which figure is on file) are filed as `general`**, which prescribes no tools.
- **Q8 (death tax if both die) is filed as `tax_optimisation`, P7 (spouse inheritance) as `estate_planning`, and P5 (a pension what-if) as `holistic_health`.**

### RC4. The prompt sends questions to capture

- `FynSystemPrompt.php:206-211` makes the capture handoff a "TOP-PRIORITY RULE… overrides every other instruction", with no exception for a question.
- `:156` calls a disputed stored figure "a write intent for `delegate_to_capture`".

The model handed pure questions to capture (F20, P5, Q5, Q10). They opened blank add forms through `OnboardingChatDirector::runInlineCapture` (`:8640-8665`).

### RC5. The process lacks figures the app already has

- **Spouse figures:** added only when the message says spouse, partner, husband or wife (`FynContextAssembler.php:580-584`), never for "Dev".
- **Retirement:** `fetch_retirement_plan` returns no projection headline (P: #4004). The required tool, `get_module_analysis(retirement)`, has it (P: #3849).
- **History:** the model sees 20 messages (`HasAiChat.php:103`). `search_conversation_index` excludes the active conversation and reads only other conversations' summaries (`CoordinatingAgent::handleSearchConversationIndex`).
- **Gifts made within seven years of death:** Fyn's knowledge corpus (`fyn-memory/semantic`) has no entry.
- **Most strategies publish no working** (TODO item 18's own Found line). So Fyn does its own arithmetic: F5, P3, M8 and Q8.

### RC6. Onboarding bubble steps keep only the bubble

- **Typed answers are rejected unless they match a label.** `OnboardingChatDirector::interpretAnswer` (`:2030-2063`) and `OnboardingStateMachine::matchBubbles` (`:3258-3281`) work this way.
- **The rest of an accepted answer is discarded.** Later forms are never filled from it: `WalkFormPrefill::for` (`:61-69`) fills only from existing records.

### RC7. Fyn's forms drop facts the database has columns for

All in `app/Services/Onboarding/CaptureForms.php`.

| Fact the user gave | Form today | Column that already exists |
|---|---|---|
| Premium Bonds, Junior ISA | savings kinds: current, easy access, fixed, notice (`:869-875`) | `savings_accounts.account_type`: `premium_bonds`, `junior_isa` (`SavingsAccount.php:198-200`) |
| Mortgage rate, type, lender, term, payment | none | `mortgages.interest_rate`, `mortgage_type`, `rate_type`, `lender_name`, `remaining_term_months`, `monthly_payment` (`Mortgage.php:26-44`) |
| Property town | none | `properties.city`, `postcode`, `address_line_1` (`Property.php:43-47`) |
| Agent fee, service charge | none | `properties.managing_agent_fee`, `monthly_service_charge` (`Property.php:61`, `:70`) |
| Life policy in trust | none (`:1785-1787`) | `life_insurance_policies.in_trust` (`LifeInsurancePolicy.php:41`) |
| Spouse's date of birth | none (`:1005-1045`) | `family_members.date_of_birth` (`FamilyMember.php:33`) |
| Defined benefit pension | workplace defined contribution and personal only (`:964-967`) | defined benefit records (`PensionStore.php:663`) |

Instead of facts, the stored rows hold placeholders (P):
- mortgages 386 and 387: rate 0.0000, type `repayment` (Brett said interest-only), lender "To be completed";
- property 531: city "Unknown".

### RC8. Joint records added after onboarding never link to the partner

`CoordinatingAgent.php:1311` remembers a joint record for the later link only while `onboarding_completed` is false.

### RC9. Workplace pensions saved through Fyn have no scheme type

- **The cause:** the form passes `scheme_type => 'occupational'` (`CaptureForms.php:964`), which `dc_pensions.scheme_type` does not accept. The column is left NULL.
- **Its effect:** the auto-enrolment, employer-match and salary-sacrifice checks require `scheme_type === 'workplace'` (`RetirementActionDefinitionService.php:521`, `:1597`, `:1882`).
- **On production (P):** Priya's pension 529 has `scheme_type` NULL, as do 9 of the 25 occupational pensions there. The retirement plan Fyn read told her: "You and your employer pay 0.0% in total… about £3,522 a year is missing". She pays 6% and Monzo 8%.

## 3. Every finding: verdict, evidence, fix

**Verdicts:**
- **Bug:** confirmed in code, and in the production record or a replay.
- **On dev:** fixed on dev, not yet released.
- **By design:** the code does what a recorded ruling says.
- **Model:** the model's own reasoning; the app-side fix is stated.
- **I COULD NOT TEST THIS:** it needs a live browser check.

**Batches (section 5):** B1 words, B2 questions reach the process, B3 the process has the figures, B4 onboarding, B5 form fields, B6 data and app bugs, B7 chat surfaces.

### Web, first pass (F)

| # | Verdict | Evidence | Fix |
|---|---|---|---|
| F1 | Bug, RC6 | P: #3764 re-asks after "about 48k", #3768 after the typed asset list, #3777 after "nope that's it…" (the £400 dividend lost), #3781 after "sure, go ahead", #4083 after Dev's ISA typed at a yes/no gate. | B4 |
| F2 | Bug, RC6 | P: #3759 "Head of Product… £92k plus £6k bonus", then the work form came up empty (#3770). The same on /m (#3885, #3890). C: `WalkFormPrefill.php:61-69`. | B4 |
| F3 | Bug, RC2d, critical | R: replays F3a, F3c and F3d navigate. P: none of them reached the server. | B2 |
| F4 | Bug, RC2d + RC4 | **Typed request (P #3852):** "ok take me to my tax strategy" is saved with no reply row. The model emitted a malformed `delegate_to_capture`, which shows "I couldn't pick up that request" (`FynLoop.php:659`, `:693`); the warning is not logged on production (`LOG_LEVEL=error`). **Rephrase (P #3854):** "You're on the Tax Strategy page now", with no navigation tool in advice mode. | B2 |
| F5 | Model + RC5 | P: #3862 says £684, #3864 says £542; the right answer is £342. #3861 named "Dev", so no spouse figures reached it. R: filed `savings_accounts`; the `savings_to_spouse` strategy that prices this publishes no working. | B3 (with item 18's working) |
| F6 | Model, self-corrected | P: #3856 "below the £80,000 point" for £93,080. The context figures were right. | B3 (child benefit charge in the published working) |
| F7 | Bug, tax | C: `TaxStrategyMath::pricingPartsFor` adds gross Gift Aid and relief-at-source contributions to `net_pay`, which relieves them at 40%. Gift Aid extends the basic-rate limit instead (ITA 2007 s414(2)(b), L), worth 20%. The class already has `incomeTaxWithBandExtension`. `ActionHowToFacts.php:96` prints the short `incomeTaxNow`: £750 × 20% = £150, so £24,614 − £150 = £24,464. | B6 (tax check done in the main session, sources recorded; no reviewer agent) |
| F8 | Same figure, wrong labels | £7,763 of spare cash ÷ 0.8 = £9,703.75, the gross pension payment it buys at source (`TaxStrategyContext::pensionFundableGross`). It is labelled "what you can afford". "£9,704 of headroom" comes from `PensionTaxReliefStrategy.php:61-63` capping the allowance at that figure; the allowance left is £60,000 − £13,720 = £46,280. | B6 |
| F9 | Bug, RC7 | Neither product is a form kind. P: #3795 answered "where do those go?" with a blank savings form above its own "limit reached" line. | B5, B4 |
| F10 | Bug | C: the Free cap is checked only at the "add another" steps (`capReachedAtLoop`, `OnboardingChatDirector.php:1925-1940`), not at a section's start. P: #3809 failed at save. "Save did nothing with ownership unselected": **I COULD NOT TEST THIS**. | B4 |
| F11 | Bug, RC6 + RC7 | P: the spouse answer #3761 kept only "yes". There is no spouse date-of-birth field. #3836 saved typed spouse details with no form shown, against your forms-only ruling (2026-10-01; option A 2026-10-05). | B4, B5 |
| F12 | Bug | C: `LiabilitiesList.vue` reads the Estate store. `EstateController::index` returns only a teaser to Free users (`:78`), so the mortgages are hidden. The rate was never captured (RC7). | B6, B5 |
| F13 | By design | C: `AiChatPanel.vue` `handleNavigation` closes when undocked or on the same screen. The code comment gives the reason: parity with /m and native, and reopening shows the question. | None |
| F14 | On dev, not released | **Your rule (2026-10-12):** £100 when whole, £100.15 when it has pence, never rounded. Dev's R27 (#1193, `ComplianceRules::CURRENCY_FORMAT`) carries it; production still says "two decimal places". `FynContextAssembler.php:610` and `AdvicePromptBuilder` still feed ".00" figures to the model. | Release + B3 |
| F15 | Bug, RC7 | P: property 531 has city "Unknown". | B5 |
| F16 | Not a defect | The saving needs the spouse's interest. "Nothing else in his name" was not saved as £0 (RC6). Once recorded, the action appeared (#4018). | B4 |
| F17 | Model, corpus gap | L: the person who received the gift is liable (IHTA 1984 s199(1)(b)). The giver's personal representatives are liable only if tax is unpaid 12 months after the end of the month of death (s204(8)). The corpus has no entry. | B3 |
| F18 | Bug: your W-0467 note is missing | Your W-0467 wording (2026-08-23): "Based on your own records alone… This figure does not allow for anything passing to your partner. Linking your accounts gives a fuller picture." It is published by `EstateActionDefinitionService.php:178-208` and the Free teaser. The Estate page's Inheritance Tax Summary, where Brett read £3,900, never shows it. | B6 |
| F19 | Bug, RC2a + RC3 | R: the "none" gate fires; it is classified `data_entry`. P: the #3870 reply and `declared_none_keys` written to user 812, which changes the plan. | B2, plus clearing that account's flags |
| F20 | Bug, RC4 | P: #3872 opened an employer-benefits edit form. R: the question passed every gate, so the model delegated it to capture. | B2 |
| F21 | Bug, RC6 | The children typed at the spouse step were discarded. | B4 |
| F22 | Bug, RC2b + RC3 | R: classified `retirement_goals` via "we've got"; the answer process found no retirement pattern. P: #3878 and #3880 are writes only. | B2 |
| F23 | Copy | `RetirementTargetCard.vue:176` and /m `Retirement.vue:377` mean the income target, but the age is set. | B6 |

### /m, second account (M)

| # | Verdict | Evidence | Fix |
|---|---|---|---|
| M1 | Collapse by design; lost message **I COULD NOT TEST THIS** | C: `onboardingChat.js:961-991` closes Fyn, then pushes the route 300 ms later. | B7, reproduce first |
| M2 | By design | `skipSectionIfNoInvestments` (`OnboardingStateMachine.php:2367-2384`) runs Investments for a captured Stocks and Shares ISA, per your ruling in the comment. | None |
| M3 | Bug, RC7 | `TypedFormFill.php:96` treats "money or none" as money only, so "no mortgage" never ticks the box. | B4 |
| M4 | Copy | `CaptureForms.php:1316` "My job is… and I earn" is also used for trading profit. | B4 |
| M5 | Bug | The corpus template defines a `source:` field (`fyn-memory/semantic/_TEMPLATE.md:5`), but none of the 20 house-view entries fills it. So Fyn has nothing to cite, against "every claim is sourced" (Rule 23). | B3 |
| M6 | On dev, not released | R38 (#1205), R39 (#1206, #1207). | Release |
| M7 | Bug | P: #3951 and #4050 were one-tap form deletes, while web's model path confirms first (#4066). Two behaviours for one action. | B7 |
| M8 | Bug, RC3 + RC5 | R: classified `data_entry` (a bypass type). P: #3962. | B2, B3 |

### Premium pass (P)

| # | Verdict | Evidence | Fix |
|---|---|---|---|
| P1 | Bug, RC2b | R: classified `goal` via "i pay" + "saving for". P: #3964 wrote spouse data and answered nothing. | B2 |
| P2 | Bug, RC2c, critical | P: #3964 was marked not landed and ended "?", so the continuation rule reused P1's `goal`. #3966 made three `create_goal` calls; two failed on "joint owner id required", and goal 1681 was saved with a target date of today. | B2 |
| P3 | Model + RC3 + RC5 | R: classified `general`. P: #3968 says £0 for Dev. #3970 asked for Dev's income, which had just been recorded. | B2, B3 |
| P4 | Bug, RC5 | C: `FynContextAssembler.php:580-584`. P: #3969. | B3 |
| P5 | Bug, RC4 | P: #3971, the model delegated it and a pension form opened. A what-if's designed route is `create_what_if_scenario` through capture (S0.5.r, `AdviceFyn.php:189-193`), not the pension form. | B2 |
| P6 | Bug, as F18 | The W-0467 note is missing; Q15 covers the life policy. | B6 |
| P7 | Bug, RC2b + RC3 | R: classified `spouse` via "i own" + "my husband"; the process filed it `estate_planning`. P: #3974. | B2 |
| P8 | Bug | P: #3978 offered one form for two named children; Arjun was dropped. | B4 |

### Fourth to seventh passes (Q)

| # | Verdict | Evidence | Fix |
|---|---|---|---|
| Q1 | Bug, RC7 | P: mortgage 387 stored as `repayment`, rate 0 and lender "To be completed". The fee and service charge were dropped. | B5 |
| Q2 | Bug, as M3 | As M3. | B4 |
| Q3 | Bug | C: /m copies the desktop bearer, then rotates it on boot (`resources/mobile/main.js`), and `TokenRefreshController.php:44` deletes the old token. The desktop tab lands on /login, whose clear-auth removes /m's token (`resources/js/store/modules/auth.js:252`). | B6 |
| Q4 | Bug + copy | **"Falls from £28,300"** is F7. **The monthly figure:** £15,700 − £6,636 − £314 National Insurance = £8,750; payroll carries about £1,888 a month, so £1,052 × 5 is only the payroll part. The outcome sentence (`database/seeders/data/action-how-to/tax.md:86`) has no branch for the one-off remainder. | B6 |
| Q5 | Bug, RC4 + RC2b + RC3 | **#3997:** classified `general`, then the model delegated it. **#3999:** classified `property` via "record". P: #3998 and #4000, blank forms. | B2 |
| Q6 | Bug, RC3 + RC5 + RC2c | R: classified `navigation` via "Show the working". P: #4004 used the retirement plan tool, which has no headline and carries the RC9 error. #4007 was pulled into capture. | B2, B3 |
| Q7 | Bug, RC2c + RC3 | P: #4008 was marked not landed, so the continuation rule reused #3999's `property` intent. That maps to the Estate focus (`OnboardingChatDirector.php:9448-9454`): "this Estate Planning step". #4013 overwrote the target. | B2 |
| Q8 | Bug, RC3 + RC5 | R: classified `tax_optimisation` instead of `estate_iht`. P: #4016. | B2, B3 |
| Q9 | Bug, RC7 | The placeholder lender "To be completed" is the /m label. | B5 |
| Q10 | Bug, RC4 + RC3 | R: classified `general`. P: #4034, a blank savings form. | B2 |
| Q11 | Model | P: #4046 left self-correction in the reply. | B7 |
| Q12 | Bug | C: `ConversationHistoryService::project` (`:63-95`) labels any conversation that began in onboarding as setup. The failed F4 turn saved no reply row. | B6 |
| Q13 | Bug, RC5 + model | P: #4053 invented £12,000; the bonus was about 80 messages back. | B3 |
| Q14 | Bug: two rules disagree | W-0140 (`UserProfileService.php:703-706`): spending is "entries PLUS commitments, and Disposable Income depends on it staying that way". The Fyn form asks for "the lot", rent or mortgage included (`CaptureForms.php:1651-1652`), so a premium or mortgage payment on record is counted twice. | B6: the form names the payments the app adds |
| Q15 | Bug, RC7 | The policy was stored as not in trust. | B5 |
| Q16 | By design + copy | C: `WillTypePolicy.php:105-145`, mirror only for married users, which needs a linked spouse. The refusal never offers the invite that unlocks it. | B6 |
| Q17 | Bug, RC1 | P: #4060's stored context holds the corrupted email. An invite request should reach the existing invite step (`campaign_spouse_invite_details`) through capture, as write intents do. | B1, B2 |
| Q18 | By design | The linked couple's figure is the second death, worded "If you both died today" (`EstateActionDefinitionService.php:192-196`, W-0501). | None |
| Q19 | Name: **I COULD NOT TEST THIS**; address: gap | C: the server builds the name (`WillDocumentService.php:78-82`). No address is asked anywhere, and the step lets it pass blank. | B6, B5 |
| Q20 | 0%: bug; warning: **I COULD NOT TEST THIS** | `WillBuilderResiduaryStep.vue:114` checks only the 100% total. The warning keys on `mirror_document_id` (`WillDocumentService.php:307`). | B6 |
| Q21 | **I COULD NOT TEST THIS** | Not traced; it needs the projection inputs before and after. | B6 |
| Q22 | Bug | P: #4066 issued a delete preview for "goal #0". The preview does not check the record exists. It isn't the "£", as the walk report guessed. | B2 |
| Q23 | Bug, RC8 + ruling 50 | **The buy-to-let (P):** property 533 is joint with no `joint_owner_id`. **The partner's figures:** ruling 50 (CSJ 2026-09-29, in the 9 October walk file) says what a partner gave is transferred and stored, and the spouse's walk confirms it rather than asking again. Dev was asked his ISA again. | B6 |
| Q24 | Bug, RC7 | There is no defined benefit kind. The silent Save: **I COULD NOT TEST THIS**. | B5 |
| Q25 | Bug | C: `router/index.js:1840` drops the address; `Login.vue:251` already reads `redirect`. | B6 |

### Other observations

- **The daily Fyn limit:** this is the tier budget in `HasAiGuardrails`, by design.
- **The /m dashboard blank area:** **I COULD NOT TEST THIS**, and it was not investigated.

## 4. Found while checking (not in the walk)

| # | Severity | What | Evidence |
|---|---|---|---|
| N1 | Critical | RC1: question marks, "@", "+", "/", ":", "&" and others are stripped from every message the model sees. | P: #4060 and #4002 |
| N2 | High | RC9: users are told they pay 0.0% into workplace pensions they pay into. 9 of 25 on production; it needs a backfill. | P: pension 529, #4004 |
| N3 | High | The advice classifier files "show the working" questions as navigation and "I have a…" questions as data entry, both of which skip the answer process. | R |
| N4 | High | `capture_write_landed` is false on every `capture_*` write. | P: five rows |
| N5 | Medium | `isAdviceShaped` was written for mixed messages and has no callers. | C |
| N6 | Medium | Two failure paths leave no trace. `handoff_error` and the LLM-fallback write intent are logged below `error`, so production records nothing, and the failed turn saves no reply row. | `FynLoop.php:645-707` |
| N7 | Medium | Mortgages store an unasked rate as 0% and an unasked type as `repayment`. This is the R10 trap again. | P |

## 5. The fix batches

Each fix is its own PR to dev, following the walk loop:
1. Write a test that is red first. It replays Brett's exact message; the section 8 replays become the tests.
2. Run only the touched test files.
3. Walk csjones at web 1440 and /m 390 with a fresh account.
4. Release, together with dev's R19–R39, which include F14 and M6.
5. Walk fynla.org, and purge the walk accounts.

Fyn changes go in one place for every surface (Rule 20). Tax changes and the RC1 change get their tax and security checks from me, in the main session: each rule is checked against its source (HMRC manual, legislation or tax config key), and the check is recorded as evidence on the task. No reviewer agents (rule R1).

### B1. Fyn sees the user's words (RC1)

**Fixes:** N1, Q17.

**Change:** stop running `clean()` over the user's own message, at `FynContextAssembler.php:347`. Neutralise only a literal `<user_message>` or `</user_message>` inside it.

**Test:** Brett's prompt-injection record still behaves (the provider name was saved as data and treated as data).

### B2. Every question reaches the answer process, filed correctly (RC2, RC3, RC4)

**Fixes:** F3, F4, F19, F20, F22, M8, P1, P2, P3, P5, P7, Q5, Q6, Q7, Q8, Q10, Q22.

1. **One question test: the existing `QuerySchemas::isAdviceShaped`.** Add the phrasings it misses: "give me", "show (me) the working", "work it through", "which wins", "figures please", "is that right", "nothing to record", "rules question". Every gate that runs before the model calls it first, and a question goes to the answer process. The gates are:
   - `recordDeclaredNone`;
   - `WriteIntentClassifier::classify`;
   - `captureContinuationIntent`;
   - `proposalAcceptanceIntent`;
   - `offerTypedChangeForm`;
   - navigation.
   `looksLikeQuestion` folds into it, so there is one test, not two.
2. **`QueryClassifier` never files a question as `data_entry` or `navigation`.**
   - "show the" stops matching navigation.
   - The retirement patterns gain "retire at", "stop work(ing) at", "how far short", "shortfall", "put away each month" and "afford to retire".
   - The estate patterns gain "inheritance tax", "death tax", "inherit" and "if I died".
   - The savings-tax patterns gain tax on interest by owner.
   - The property patterns gain tax on rent.
   - The page fallback stays last, as now.
3. **`capture_write_landed` is true when a `capture_*` result reports `onboarding_capture`.**
4. **The handoff rule (`FynSystemPrompt.php:206-211`, `:156`) applies to statements only.** A question is answered.
5. **Navigation moves to the server, for every surface.**
   - Only a plain command with no question in it ("take me to my tax strategy") navigates.
   - Words match whole, and every routed page is covered, `/tax-strategy` included.
   - It emits the existing `navigation` event (web and /m handle it; native to confirm).
   - It saves the request and the reply.
   - `chatNavigationRouter.js` is deleted.
6. **A what-if request reaches the designed `create_what_if_scenario` capture**, not a pension form (S0.5.r).
7. **The delete preview refuses a record that does not exist**, and the model looks the id up first.

**Test:** a parameterised Pest test over every message in section 8. It asserts the route and, for questions, the `QueryClassifier` type and its required tools.

### B3. The answer process has the figures (RC5)

**Fixes:** F5, F6, F14 (context), F17, M5, M8, P3, P4, Q6, Q8, Q13.

- **Spouse figures:** add the household row whenever it exists, without waiting for a relationship word.
- **Retirement:** `fetch_retirement_plan` carries the Retirement page's headline (the same server figure). The prescribed tool for `retirement_readiness`, `get_module_analysis(retirement)`, is the one the process calls.
- **Published working (TODO item 18, already under way):** the strategies behind these answers publish their working, so Fyn presents figures rather than deriving them:
  - `savings_to_spouse` (tax by owner, F5 and P3);
  - the child benefit charge (F6);
  - the estate with a named beneficiary and the second death (M8, Q8).
- **History:**
  - give the model a summary of the turns older than the window;
  - let the search read the active conversation;
  - add a recall rule: quote what the user wrote, or say it is out of view, never invent it.
- **Corpus:**
  - add an entry on gifts made within seven years of death: who pays (IHTA 1984 s199(1)(b), s204(8)) and taper relief, with the taper citation fetched before writing;
  - fill the template's `source:` field on every entry, each citation fetched and checked;
  - Fyn's answers pass the source on (M5).
- **Money (F14, your rule):** every figure handed to the model is written exactly: £100 when whole, £100.15 when it has pence, never rounded. One formatter serves `FynContextAssembler` and `AdvicePromptBuilder`.

### B4. Onboarding keeps what the user typed (RC6)

**Fixes:** F1, F2, F9 (the at-cap reply), F10, F11, F16, F21, M3, M4, P8, Q2.

- **Bubble steps read typed answers:**
  - a figure becomes its band ("about 48k" → up to £50,270);
  - the yes/okay family covers "sure, go ahead";
  - the no family covers "nope… though…";
  - a natural-language asset list maps to the picks.
- **Extra facts are parked on the conversation** (the `onboarding_parked_facts` column exists). Each later form opens pre-filled from them: work, spouse, ISA, savings, property, pensions and spending. Children who are mentioned open the existing dependants form, pre-filled. The user presses Save, as your forms-only ruling says.
- **Caps:** check the cap at each section's start, and answer "where do those go?" at the cap in words.
- **"No mortgage"** selects the none option.
- **Several people in one message** get one form each.
- **The self-employed sentence** says profit.
- **Your registration change (section 6) and the end-of-onboarding change (section 6b)** are in this batch.

### B5. Fyn's forms gain the fields whose columns exist (RC7)

**Fixes:** F9, F11, F12, F15, Q1, Q9, Q15, Q19 (address), Q24.

- **Add the fields in the RC7 table**, writing the existing columns through their stores.
- **Stop storing placeholders as facts:** rate 0, `repayment`, "To be completed" and "Unknown" become null and show "Not recorded", as R10 did for savings.

### B6. Data and app bugs

| Finding | Change |
|---|---|
| N2 | Fix the form's scheme type and backfill production. |
| Q23 | Remember joint records after onboarding; backfill linked couples; transfer the partner's figures for the spouse's walk to confirm (ruling 50). |
| F12 | Read the Liabilities page from the net-worth endpoint. |
| F7 / Q4 | Price Gift Aid and relief at source by band extension; add the one-off branch to the outcome sentence. |
| F8 | Fix the labels. |
| F18 / P6 | Show your W-0467 note on the Estate page summary. |
| Q14 | The spending form names the payments the app adds (W-0140). |
| Q3 | /m mints its own token and leaves the desktop one alone. |
| Q25 | Carry `redirect` through sign-in. |
| Q12 / N6 | Label from the conversation's state; save a reply row on failure; log at `error`. |
| Q16 | The refusal offers the invite. |
| Q20 | Reject a 0% beneficiary. |
| F23 | Say "a target income". |
| Q19, Q20 warning, Q21 | Live investigation first. |

### B7. Chat surfaces

| Finding | Change |
|---|---|
| M7 | The server asks "Remove X?" with Yes / No before a form delete, the same on every surface. |
| Q11 | A prompt rule against visible self-correction. |
| M1 | Reproduce live, then fix. |

## 6. Your onboarding change: normal registration opens on "Which of these do you have?"

### Your answers (2026-10-12)

- **The name:** Fyn remembers the name given at registration and greets the user by their first name. It never asks for a name it already has.
- **The three questions** (employment situation, spouse or civil partner, spouse's income): asked after the bubbles, in today's order.

### What happens today

- **Every new user goes through Save Tax.** `config/onboarding.php:99` sets `'forced_campaign' => 'savetax'` (your ruling, 2026-09-25, `bfbb71124`).
- **Where the chat starts.** `OnboardingStateMachine::forcedCampaignEntry` (`:1129-1137`) starts at the first unanswered funnel question (`firstMissingFunnelState`, `:1084-1112`), in the order of `FUNNEL_STATES` (`:229-234`): employment, spouse, spouse income, assets.
- **Web Save Tax route:**
  1. `/savetax` (`public/pages/savetax.php`) asks five things: employment status, income band, spouse, the spouse's income and employment, and Q5 "Which of these do you have?" (multi-select).
  2. `/savetax/plan` registers the user (`savetax-plan.php:166-175`).
  3. The chat opens at work and income.
- **Normal registration:**
  1. `Register.vue` collects first name, last name, email and password (`:105-130`). /m links to it (`resources/mobile/views/Login.vue:178-179`), and native iOS requires a first name (`RegistrationModel.swift:37`). The name is always there.
  2. The chat asks:
     - `campaign_funnel_employment`: "Hi Priya, I'm Fyn… What's your employment situation?";
     - spouse;
     - spouse income;
     - `campaign_funnel_assets`, the bubbles.
  3. Then work and income.
- **Invited partner:** already starts at the bubbles, because the link answers employment and spouse (P: Dev, #4069).

### The new start

1. **Fyn opens on the bubbles**, with Fyn's greeting and the user's first name: "Hi Priya, I'm Fyn. I'll help you find where you could be saving tax. First, a few quick questions. **Which of these do you have?**" The bubbles are the same multi-select, with "That's everything".
2. **Then employment situation, spouse and spouse's income**, each asked only if not already answered (as `firstMissingFunnelState` does today).
3. **Then work and income**, and the rest of the walk, unchanged.

### What the change touches

- **One server change, so web, /m and iOS all follow.**
  - `FUNNEL_STATES` order becomes assets first for a chat-asked funnel.
  - `funnelGreeting` already puts the greeting on whichever funnel question comes first (`:1191-1197`), so the bubbles carry it.
  - `nextFromFunnelAssets` (`:1216-1221`) currently leaves the funnel on "That's everything". It has to go on to the first unanswered of the other three instead. `leaveFunnelQuestions` then runs after the last of them.
  - `buildFunnelAssetsPrompt`'s "Anything else?" branch stays.
- **No name step** is added, because the name is always on the account.
- **Unchanged:** the /savetax route (its answers already fill the funnel) and the invited-partner route.
- **Tests:** the funnel-order and `OnboardingStartCampaignMapTest` cases; a red-first test that a normal registrant's first Fyn turn is the bubbles with the first-name greeting, and that employment follows "That's everything".
- **Walk:** register normally on web and on /m.

## 6b. The end of onboarding: the Save Tax report is for Save Tax arrivals only

### Your rule (2026-10-12)

"If the user does not enter through save tax campaign, at the end of onboarding we do not show a save tax report, as this is only for the save tax campaign. We do need to show actions, for allowances etc … just not the plan."

### What happens today: every user gets the Save Tax report

The production record shows it for two normal registrants:
- **Priya:** #3842 "Here's your tax plan, built from what you told me…", then #3847 "We've created your personal tax strategy, Priya…" with "Take me to my tax strategy".
- **Tom:** #3937 and #3939, the same.

The code behind it:
- **The plan in chat:** sections run out into `campaign_synthesis` (`OnboardingStateMachine.php:473`, `:503`). `OnboardingChatDirector::buildSynthesisAdvice` (`:1713-1790`) voices the composed tax plan. It already branches by campaign, with pensioncheck shown its pension picture instead.
- **The partner invite:** `campaign_spouse_invite` (`OnboardingStateMachine.php:2938-2940`) says "Your plan uses the figures you gave me for… the joint plan gets more accurate".
- **The closing step:** `campaign_terminal` (corpus, `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:452-457`) says "We've created your personal tax strategy, {first_name}…" and goes to `navigate_to: /tax-strategy`. Its button comes from `terminalNavigationBubble` (`OnboardingChatDirector.php:6467-6476`).
- **Which closing step:** `campaignFinalTerminalFor` (`OnboardingStateMachine.php:2905-2910`) picks it by `onboarding_fyn_selection`. That selection is forced to `savetax` for everyone (`config/onboarding.php:99`), so it cannot tell the two groups apart.

### How a Save Tax arrival is recognised, and what breaks it

- **At registration:** a Save Tax arrival is recorded as `users.funnel_answers['campaign'] = 'savetax'` (`AuthController::register`, `:92`). Every `/savetax` registration sends it (`public/pages/js/savetax.js:4`), always with an income answer (`:42`, Continue disabled until answered at `:123`). The chat never asks the income band (`OnboardingStateMachine.php:203-205`).
- **The chat overwrites it for everyone.** When the chat asks a funnel question it writes `$funnel['campaign'] ??= $user->onboarding_fyn_selection ?? 'savetax'` (`OnboardingChatDirector.php:2755`). Its own comment calls this "the campaign the user arrived on (their acquisition record)", so a normal registrant is recorded as a Save Tax arrival. P: Priya (812), Tom (813) and Casey (810) all registered normally, and all carry `campaign = 'savetax'`.
- **Invitees.** An invited partner "walks the inviter's campaign" (`SpouseLinkingService.php:698-704`), but the copy defaults to `'savetax'` too.
- **On production today (read-only count):**
  - 12 users came through `/savetax`;
  - 2 are partners of one;
  - 10 carry the chat-written stamp without having come through `/savetax`, 7 of them still mid-onboarding.

### The change

1. **Keep the arrival record true.**
   - The chat stops writing `campaign` (`OnboardingChatDirector.php:2755`); it only records the answers.
   - An invitee copies the inviter's campaign only when the inviter has one (`SpouseLinkingService.php:703`, no `'savetax'` default). So the partner of a Save Tax arrival gets the plan, and the partner of a normal registrant gets actions, under the existing rule that the invitee walks the inviter's campaign.
   - One helper answers "came through Save Tax" (`funnel_answers.campaign === 'savetax'`) for every reader.
2. **Correct the 10 stamped rows.** Remove the stamp where `funnel_answers` has no `income` and the linked partner's has none either. A dry-run count goes first, then the run, by script as in earlier releases.
3. **The closing turn for everyone else.** `buildSynthesisAdvice` gains a third branch beside savetax and pensioncheck: the user's top actions. These are the same items, order and figures as the dashboard's top actions (`NextActionsService::build`), which head the Actions page list (`buildAll`). They include the allowance actions (pension, ISA, Gift Aid, savings to a spouse), because the composed tax plan feeds the actions list (`NextActionsService` constructor). Chat and dashboard never disagree (the synthesis-mirrors-dashboard rule). No "tax plan" heading and no plan total.
4. **The closing step for everyone else.** A second terminal in the corpus goes to `/actions`, which exists on web (`router/index.js:1078`) and /m (`resources/mobile/router.js:72`). `campaignFinalTerminalFor` picks it when the user did not come through Save Tax. `terminalNavigationBubble` gains the `/actions` case.
5. **The partner invite** stops saying "plan" for these users.
6. **Native iOS:** `DeepLinkParser.swift:48-66` has no `"/actions"` entry, though `AppRouter.swift:39` defines the route. Add it in the same change (ios-native rule). CI verifies, since iOS isn't tested locally.
7. **Follows automatically:** the WP-6 ordering in `NextActionsService::applyCampaignAffinity` (`:376-413`) puts tax actions first for campaign arrivals only, as written. With the record corrected, normal registrants get plain value order.
8. **Unchanged:**
   - the `/savetax` route still ends on the tax plan and "Take me to my tax strategy";
   - the tax strategy page stays in the menu for everyone.

### Draft wording

These follow the existing lines word for word where they can; change any you like.

| Turn | Wording |
|---|---|
| Closing turn | "Here are your top actions, built from what you told me — in the order I'd tackle them:", then the actions as bullets ("— saves around £X a year" where an action carries a saving), then the existing adviser line. |
| Closing step | "Your actions are ready, {first_name}. Open any action on the next screen to see the steps, or ask me about it here." |
| Button | "Take me to my actions" |
| Partner invite | "One more thing. Your actions use the figures you gave me for {name}. If they have their own Fynla account and you link up, I can use their real allowances and tax position, and your actions get more accurate. **Shall I send them an invitation?**" |

### Tests and walks

- **Red-first tests:**
  - a normal registrant's last turns are the actions recap and the `/actions` step;
  - a `/savetax` arrival's are unchanged (`CampaignSynthesisTurnTest` stays green);
  - the partner of a `/savetax` arrival gets the plan, and the partner of a normal registrant gets actions;
  - the chat no longer writes `campaign`.
- **Walks:** normal registration on web and /m, then a `/savetax` registration, on csjones, then fynla.org. Each check confirms the recap's actions match the dashboard's top actions, figure for figure.

## 7. Rulings applied (no open decisions)

| Topic | Ruling or source used |
|---|---|
| Questions | Answered by the advice process (CSJ 2026-10-12). |
| The Save Tax report | Shown at the end of onboarding only to users who came through the Save Tax campaign; everyone else sees their actions (CSJ 2026-10-12, section 6b). |
| A partner's journey | The invitee walks the inviter's campaign (`SpouseLinkingService.php:698`). |
| Money | Exact: £100, £100.15, never rounded (CSJ 2026-10-12). |
| Capture | Forms only; typed answers fill the form; the user presses Save (CSJ 2026-10-01, 2026-10-05). |
| Every figure | One figure from the server for every surface. |
| A married user's estate figure | W-0467 wording (CSJ 2026-08-23). |
| A couple's estate figure | Second death (W-0501). |
| Estate pooling | W-0154. |
| Spending | Entries plus commitments (W-0140). |
| A partner's figures | Transferred and confirmed (ruling 50, 2026-09-29). |
| What-ifs | Through `create_what_if_scenario` capture (S0.5.r). |
| Wills | Mirror only for married users (`WillTypePolicy`). |
| Investments section | Runs for a Stocks and Shares ISA (M2). |
| Every claim | Sourced (Rule 23). |
| Every Fyn change | One place, all surfaces (Rule 20). |

## 8. Replay results (R)

### Brett's messages through each gate

"Advice-shaped" is the existing unused detector; "Classifier type" is the answer process's own filing.

| Message | Advice-shaped? | Ends "?" / starts with a question word | "None" gate | Write classifier | Classifier type (page) | Production outcome |
|---|---|---|---|---|---|---|
| 3869 F19 | yes | yes | **fires** | none | data_entry (/protection) | declared four topics as none |
| 3877 F22 | yes | no | no | retirement_goals | protection_cover (/protection); general (no page) | write only |
| 3963 P1 | **no** | no | no | goal | not run | spouse write only |
| 3965 P2 | **no** | no | no | none | not run | continuation, so 3 goals |
| 3971 P5 | yes | yes | no | none | holistic_health | model delegated, so a pension form |
| 3973 P7 | yes | no | no | spouse | estate_planning (/estate) | spouse edit form |
| 3997 Q5 | yes | no | no | none | general | model delegated, so a property form |
| 3999 Q5 | yes | no | no | property ("record") | not run | property form |
| 4003 Q6 | yes | no | no | none | **navigation** (/net-worth/retirement) | answered without the projection |
| 4009 Q7 | yes | no | no | none | **navigation** | continuation, so "Estate Planning step" |
| 4013 Q7 | **no** | no | no | none | not run | continuation, so a write |
| 4015 Q8 | yes | no | no | none | tax_optimisation | answered without a figure |
| 3961 M8 | yes | yes | no | none | **data_entry** | answered without a figure |
| 3967 P3 | not run | no | no | none | general | £0 for Dev |
| 3861 F5 | **no** | no | no | none | savings_accounts | £684 |
| 4033 Q10 | yes | yes | no | none | general | model delegated, so a savings form |
| 3977 P8 (add children) | no | no | no | dependant | not run | correct route (one form) |
| 3946 M6 | no | no | no | savings_account | not run | blank add form (R38 on dev) |

### Web navigation matcher

| Message | Navigates? |
|---|---|
| F3a "…Show your working, then take me to the Income page" | yes, to Income |
| F3c "My retirement page says… Show the working." | yes, to Pensions & Retirement |
| F3d "Projected income of £62,894… Show the working." | yes, to Income |
| "take me to my savings" | yes, to Bank Accounts |
| "ok take me to my tax strategy" | no route |
| "Can you open the Save Tax page for me?" | no route |

For F3a, F3c and F3d the elided parts of Brett's text were filled in for the replay. The exact text never reached the server.
