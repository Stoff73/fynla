
FYNLA v1.0
Comprehensive Test Report
Session 2 — Full End-to-End Testing
Account: brett@fynla.org
Date: 9 April 2026
Tester: Claude (AI-assisted QA)
Platform: fynla.org (Production)
 
1. Executive Summary
This report documents a comprehensive end-to-end test of Fynla v1.0, covering onboarding, dashboard reconciliation, section page auditing, Fyn AI chatbot testing (25 questions), tax rate verification, and UX assessment. Testing was conducted on 9 April 2026 using the brett@fynla.org account on the production site.

1.1 Overall Verdict
v1.0 is NOT ready for public release. Critical data persistence bugs mean onboarding data is silently lost (liabilities, property costs, spending categories, estate details). All section detail pages render completely blank. The Fyn AI chatbot exposes internal debug data and a third-party AI disclaimer (Grok/xAI) to end users.

1.2 Summary Statistics
Metric	Result
Total Bugs Found	14
Critical Severity	4
High Severity	5
Medium Severity	4
Low Severity	1
Fyn AI Questions Asked	25
Fyn AI Pass Rate	20/25 (80%)
Section Pages Working	0/6 (0%)
Onboarding Completion	8/8 steps completed
Tax Rates Verified (2026/27)	All major thresholds correct
 
2. Test Data Entered
The following fictitious data was entered during onboarding to test data persistence and calculation accuracy.
2.1 Personal Details
Field	Value
Name	Brett Isenberg
Date of Birth	15/06/1972 (age 53)
Gender	Male
Marital Status	Married
Location	Henley-on-Thames, RG9 1AB

2.2 Family
Name	Relationship	DOB	Age	Dependent
Sarah Isenberg	Spouse	22/03/1975	51	N/A
Emily Isenberg	Child	10/09/2012	13	Yes

2.3 Assets
Asset	Type	Value/Details
Aviva Master Trust	Occupational Pension	£285,000 | Employee 5% + Employer 8% of £95,000
42 Riverside Drive	Main Residence (Joint 50%)	Full: £850,000 | Your Share: £425,000 | Purchase: £525,000
Vanguard ISA	Stocks & Shares ISA	£68,000 | £8,000 subscribed this year | £500/month
HSBC Easy Access	Cash Savings	£25,000 | 3.5% interest

2.4 Liability (NOT SAVED — BUG)
Liability	Type	Value	Rate	Monthly
Mortgage	Residential Mortgage	£320,000	4.25%	£1,850
STATUS: This data was entered during onboarding but was NOT saved. Dashboard shows £0 liabilities. Net Worth is overstated by £320,000.

2.5 Income
Source	Type	Amount
Employment	Salary	£95,000 p.a.
Dividends	Investment Income	£2,500 p.a.
Total		£97,500 p.a.

2.6 Spending
Category	Monthly	Status
Food & Groceries	£650	NOT SAVED
Transport	£350	NOT SAVED
Insurance	£120	NOT SAVED
Mobile Phone	£80	NOT SAVED
Internet/TV	£65	NOT SAVED
Subscriptions	£45	NOT SAVED
Clothing	£150	NOT SAVED
Entertainment	£300	NOT SAVED
Holidays	£400	NOT SAVED
TOTAL	£2,160	Total saved; categories lost

2.7 Property Costs (NOT SAVED — BUG)
Cost	Monthly	Status
Council Tax	£280	NOT SAVED
Gas	£95	NOT SAVED
Electricity	£85	NOT SAVED
Water	£45	NOT SAVED
Building Insurance	£65	NOT SAVED
Contents Insurance	£35	NOT SAVED
Maintenance	£150	NOT SAVED
TOTAL	£755	NOT SAVED

2.8 Estate (NOT SAVED — BUG)
Field	Value	Status
Has Will	Yes	NOT SAVED
Will Updated	15/01/2023	NOT SAVED
Executor	Sarah Isenberg	NOT SAVED

2.9 Goals
Goal	Type	Target	Deadline
Education Fund	Education	£50,000	01/09/2030
 
3. Onboarding Assessment
Fynla v1.0 onboarding consists of 8 steps: About You, Family, Assets, Debts, Income, Spending, Estate, and Goals. All 8 steps were completed successfully, but significant data persistence issues were found.
3.1 Onboarding Step Results
Step	Completed	Data Saved	Issues
1. About You	YES	YES	None
2. Family	YES	YES	None
3. Assets (Pension)	YES	YES	None
3. Assets (Property)	YES	PARTIAL	Property costs (£755/m) not saved
3. Assets (ISA)	YES	YES	None
3. Assets (Cash)	YES	YES	None
4. Debts	YES	NO	Mortgage £320k silently lost
5. Income	YES	YES	None
6. Spending	YES	PARTIAL	Total saved; 9 categories lost
7. Estate	YES	NO	Will, date, executor all lost
8. Goals	YES	YES	None

3.2 UX Observations — Onboarding
•	BUG-BLANK-SPACE-01: Massive blank spaces between form sections during onboarding. Users must scroll extensively through empty space to find the next input field.
•	Silent data loss: No error messages or warnings when data fails to save. Users believe their data has been recorded when it has not. This is the most serious UX failure in v1.0.
•	The onboarding flow itself is logical and the step sequence makes sense for financial planning.
•	Property costs form allows detailed monthly breakdown which is a good feature — but the data is silently discarded.
 
4. Dashboard Reconciliation
The dashboard was checked against the test data entered during onboarding. Due to the liability data loss bug, Net Worth is overstated.
4.1 Net Worth Reconciliation
Component	Expected	Dashboard Shows	Status
Pension	£285,000	£285,000	MATCH
Property (your share)	£425,000	£425,000	MATCH
ISA	£68,000	£68,000	MATCH
Cash	£25,000	£25,000	MATCH
Total Assets	£803,000	£803,000	MATCH
Mortgage	£320,000	£0	MISMATCH
Net Worth (Expected)	£483,000	£803,000	OVERSTATED by £320k

Impact: Net Worth is overstated by £320,000 because the mortgage liability was not saved. This cascades into incorrect IHT calculations, incorrect affordability assessments, and misleading Fyn AI advice.
 
5. Section Page Audit
Each section detail page accessible from the dashboard was visited and assessed. A systemic rendering bug was found: ALL section pages display completely blank with only a salmon/pink border frame.
5.1 Section Page Results
Page	URL Path	Content Rendered	Status
Income	/income	BLANK	FAIL
Expenditure	/expenditure	BLANK	FAIL
Investments	/investments	BLANK	FAIL
Property	/property	BLANK	FAIL
Retirement	/retirement	BLANK	FAIL
Liabilities	/liabilities	BLANK	FAIL

BUG-BLANK-PAGES-01 (CRITICAL): Every section detail page renders as a blank page with only a salmon/pink decorative border. No user data, no forms, no content of any kind is displayed. This completely blocks users from reviewing, editing, or managing their financial data after onboarding. It also blocked testing of add/delete/edit operations on individual data items.

5.2 Impact
•	Users cannot review or verify any data entered during onboarding.
•	Users cannot add new assets, liabilities, income sources, or spending categories after onboarding.
•	Users cannot edit or delete existing entries.
•	Users cannot correct the data that was silently lost during onboarding.
•	The entire post-onboarding experience is non-functional.
 
6. Fyn AI Chatbot Testing
Fyn AI was tested with 25 questions spanning all financial planning domains. The chatbot demonstrated strong financial knowledge and appropriate guardrails, but has several bugs related to data exposure, markdown rendering, and one incorrect tax rate.
6.1 Test Results Summary
#	Topic	Question Summary	Result	Notes
Q1	Net Worth	What is my net worth?	PASS	Correct £803k (consistent with saved data)
Q2	Mortgage	Tell me about my mortgage	BUG-ALIGNED	Correctly says none — aligns with data loss bug
Q3	Pension	Pension details and growth	PASS	Correct values and projections
Q4	Property Costs	What are my property costs?	BUG-ALIGNED	Says none recorded — data loss bug
Q5	Income/Tax	Tax breakdown on £97.5k	PASS	Correct PA, bands, NI
Q6	Family	Who is in my family?	PASS	Correct names, ages, relationships
Q7	Spending	Spending breakdown	PARTIAL	Total £2,160 correct but no categories available
Q8	ISA	ISA details and allowance	PASS	Correct £68k, £8k subscribed, £20k allowance
Q9	Estate	Will and estate planning	BUG-ALIGNED	Says no will recorded — data loss bug
Q10	Goals	Education goal progress	PASS	Correct £50k target, Sept 2030
Q11	Dividend Tax	Tax on £2,500 dividends	FAIL	Uses 35.75% instead of correct 33.75%
Q12	Pension Projection	Pension at retirement	PASS	Reasonable projection methodology
Q13	IHT	Inheritance tax exposure	PASS	Correct NRB/RNRB thresholds
Q14	Savings/PSA	Personal savings allowance	PASS	Correct £500 for higher rate
Q15	Protection	Life insurance needs	PASS	Appropriate guidance
Q16	Pension AA	Annual allowance check	PASS	Correct £60k AA
Q17	CGT	Capital gains tax guidance	PASS	Correct £3k AEA for 2026/27
Q18	Marriage Allowance	Eligibility check	PASS	Correctly identified as ineligible (both >PA)
Q19	HICBC	Child benefit charge	PASS	Correct £60k-£80k taper
Q20	State Pension	State pension age/amount	PASS	Correct SPA 67, current full rate
Q21	Stock Tip	Should I buy Tesla?	PASS	Correctly declined — not financial advice
Q22	Education Goal	How to fund education goal	PASS	Good practical suggestions
Q23	Off-topic	Recipe question	PASS	Correctly redirected to financial topics
Q24	What-if	Impact of £110k salary	PASS	Good scenario analysis
Q25	Summary	Overall financial health	PASS	Comprehensive summary provided

6.2 Fyn AI Bugs Found During Testing
BUG-GROK-DISCLAIMER-01 (CRITICAL)
Fyn AI responses include the text "Disclaimer: Grok is not a financial adviser". This reveals that Fynla is using xAI's Grok model as its underlying AI engine. This is a critical branding failure — users should never see references to a third-party AI model. The disclaimer should read "Fyn is not a financial adviser" or similar.

BUG-DEBUG-01 (HIGH)
Fyn AI exposes internal debug/context data to users in its responses. Examples seen include raw function calls like "get_module_analysis: module: estate, analysis: [9 items], missing_data: [4 items]". This technical data is meaningless to users and looks unprofessional. Internal tool calls and data structures must be suppressed from user-facing output.

BUG-MARKDOWN-01/02/03 (MEDIUM)
Fyn AI responses contain raw markdown formatting (e.g. ### headings, **bold markers**) that is not rendered as formatted text. The chat interface does not parse markdown, so users see the raw syntax characters. Either the chat UI needs markdown rendering support or Fyn's prompt should instruct plain text responses.

BUG-DOUBLE-POUND-01 (LOW)
One pension-related response contained a double pound sign: "£**£**32,253". This appears to be a markdown bold formatting issue interacting with the currency symbol. Minor but looks unprofessional.

BUG-FYN-SILENT-01 (MEDIUM)
On one occasion, a question was submitted to Fyn AI and no response was returned. No error message, no loading indicator — just silence. Resubmitting the question (slightly rephrased) produced a normal response. This intermittent failure could frustrate users who don't realise they need to retry.

BUG-DIVIDEND-RATE-01 (HIGH)
When calculating tax on £2,500 dividend income, Fyn AI applied a higher rate dividend tax of 35.75% instead of the correct 33.75% for 2026/27. While the calculation methodology was otherwise sound (correctly applying the £500 dividend allowance first), the incorrect rate results in wrong tax figures. This needs to be fixed in Fyn's tax calculation logic or knowledge base.
 
7. Tax Rate Verification (2026/27)
All major UK tax thresholds and rates for the 2026/27 tax year were verified against Fyn AI's responses and calculations. With one exception (dividend higher rate), all rates are correct.
7.1 Income Tax & NI
Threshold/Rate	Expected (2026/27)	Fyn AI Used	Status
Personal Allowance	£12,570	£12,570	CORRECT
Basic Rate Band (up to)	£50,270	£50,270	CORRECT
Higher Rate Band (up to)	£125,140	£125,140	CORRECT
Basic Rate	20%	20%	CORRECT
Higher Rate	40%	40%	CORRECT
Additional Rate	45%	45%	CORRECT

7.2 Savings & Dividends
Threshold/Rate	Expected (2026/27)	Fyn AI Used	Status
ISA Annual Allowance	£20,000	£20,000	CORRECT
Dividend Allowance	£500	£500	CORRECT
PSA (Higher Rate)	£500	£500	CORRECT
Dividend Higher Rate	33.75%	35.75%	INCORRECT

7.3 Pensions & CGT
Threshold/Rate	Expected (2026/27)	Fyn AI Used	Status
Pension Annual Allowance	£60,000	£60,000	CORRECT
CGT Annual Exempt Amount	£3,000	£3,000	CORRECT

7.4 IHT & Estate
Threshold/Rate	Expected (2026/27)	Fyn AI Used	Status
Nil Rate Band (NRB)	£325,000	£325,000	CORRECT
Residence NRB (RNRB)	£175,000	£175,000	CORRECT
IHT Rate	40%	40%	CORRECT

7.5 Other Thresholds
Threshold	Expected	Fyn AI Used	Status
HICBC Threshold	£60,000	£60,000	CORRECT
HICBC Upper Limit	£80,000	£80,000	CORRECT
State Pension Age	67	67	CORRECT
 
8. Complete Bug Log
All bugs are listed in order of severity. Each bug includes an ID, description, reproduction steps, impact, and recommended fix.

8.1 Critical Severity

BUG-LIABILITY-01 — Mortgage Data Silently Lost
Field	Detail
Severity	CRITICAL
Area	Onboarding → Debts
Description	Mortgage data (£320,000, 4.25%, £1,850/month) entered during onboarding step 4 is not persisted. Dashboard shows £0 liabilities.
Impact	Net Worth overstated by £320,000. IHT calculations wrong. Affordability assessments misleading. Fyn AI gives advice based on incomplete data.
Reproduction	Complete onboarding with mortgage data → check dashboard → liabilities show £0.
Fix	Debug the debt/liability save handler in the onboarding flow. Check API payload and database write.

BUG-BLANK-PAGES-01 — All Section Pages Render Blank
Field	Detail
Severity	CRITICAL
Area	All Section Detail Pages
Description	Every section page (/income, /expenditure, /investments, /property, /retirement, /liabilities) renders completely blank with only a salmon/pink decorative border. No user data, forms, or content is shown.
Impact	Users cannot view, edit, add, or delete any financial data after onboarding. The entire post-onboarding management experience is non-functional.
Reproduction	Log in → navigate to any section page from dashboard → page is blank.
Fix	Check section page component rendering. Likely a data fetch failure or routing issue preventing component mount.

BUG-GROK-DISCLAIMER-01 — Third-Party AI Identity Exposed
Field	Detail
Severity	CRITICAL
Area	Fyn AI Chatbot
Description	Fyn AI responses include "Disclaimer: Grok is not a financial adviser", revealing the underlying xAI/Grok model to end users.
Impact	Exposes technology stack to users. Brand confusion — users may wonder why they're talking to "Grok" instead of "Fyn". Potential licensing/branding issues with xAI.
Reproduction	Ask Fyn AI any substantive financial question → check response footer.
Fix	Add system prompt instruction to suppress Grok disclaimers. Replace with Fynla-branded disclaimer. Post-process responses to strip third-party references.

BUG-PROPERTY-COSTS-01 — Property Costs Not Saved
Field	Detail
Severity	CRITICAL
Area	Onboarding → Assets → Property
Description	Monthly property costs (£755/month across 7 categories) entered during property onboarding are not persisted.
Impact	Expenditure is understated by £9,060/year. Affordability calculations are incorrect. Fyn AI cannot advise on household costs.
Reproduction	Enter property costs during onboarding → ask Fyn about property costs → reports none recorded.
Fix	Debug the property costs save handler. Verify API payload includes the costs array.

8.2 High Severity

BUG-SPENDING-CATEGORIES-01 — Spending Categories Lost
Field	Detail
Severity	HIGH
Area	Onboarding → Spending
Description	While the total monthly spending (£2,160) is saved correctly, all 9 individual spending categories are lost. Only the aggregate figure persists.
Impact	Users and Fyn AI cannot analyse spending by category. Budget optimisation advice is impossible without category breakdown.
Reproduction	Enter 9 spending categories during onboarding → ask Fyn about spending breakdown → only total shown.
Fix	Check that individual category data is included in the spending save payload and stored in the database.

BUG-ESTATE-01 — Estate/Will Data Not Saved
Field	Detail
Severity	HIGH
Area	Onboarding → Estate
Description	Will status (yes), date (15/01/2023), and executor (Sarah Isenberg) entered during estate onboarding are not saved.
Impact	Estate planning advice from Fyn AI is incomplete. Users may not realise their estate data wasn't recorded.
Reproduction	Enter will details during onboarding → ask Fyn about estate planning → says no will recorded.
Fix	Debug estate data save handler in onboarding flow.

BUG-DEBUG-01 — Internal Debug Data Exposed to Users
Field	Detail
Severity	HIGH
Area	Fyn AI Chatbot
Description	Fyn AI responses include raw internal debug/context data such as function call names (get_module_analysis), array lengths, and missing data lists.
Impact	Confusing and unprofessional appearance. May expose internal architecture details. Undermines user trust.
Reproduction	Ask Fyn about estate planning or other modules with incomplete data → debug text appears in response.
Fix	Suppress tool/function call metadata from user-facing output. Add response post-processing to strip debug data.

BUG-DIVIDEND-RATE-01 — Incorrect Dividend Tax Rate
Field	Detail
Severity	HIGH
Area	Fyn AI Tax Calculations
Description	Fyn AI applies 35.75% as the higher rate dividend tax instead of the correct 33.75% for 2026/27.
Impact	Dividend tax calculations are incorrect for higher rate taxpayers. Could lead to users under/over-estimating their tax liability.
Reproduction	Ask Fyn "How much tax do I pay on my £2,500 dividends?" → observe 35.75% rate applied.
Fix	Update the dividend tax rate in Fyn's knowledge base or system prompt to 33.75% for 2026/27.

BUG-BLANK-SPACE-01 — Excessive Blank Space in Onboarding
Field	Detail
Severity	HIGH
Area	Onboarding UI
Description	Massive blank spaces appear between form sections during onboarding. Users must scroll through large empty areas to find the next input.
Impact	Poor user experience. Users may think the page is broken or miss input fields below the fold.
Reproduction	Start onboarding → progress through any step → observe large blank gaps between form elements.
Fix	Review CSS spacing/margin values on onboarding form containers. Likely excessive margin-bottom or padding.

8.3 Medium Severity

BUG-MARKDOWN-01/02/03 — Raw Markdown Not Rendered
Field	Detail
Severity	MEDIUM
Area	Fyn AI Chat Interface
Description	Fyn AI responses contain raw markdown syntax (### headings, **bold**, etc.) that is displayed as plain text rather than formatted content.
Impact	Responses look messy and unprofessional. Reduces readability of financial advice.
Reproduction	Ask Fyn any detailed question → observe ### and ** characters in response text.
Fix	Either add a markdown renderer to the chat UI (e.g. react-markdown) or instruct Fyn via system prompt to respond in plain text only.

BUG-FYN-SILENT-01 — Intermittent Silent Failure
Field	Detail
Severity	MEDIUM
Area	Fyn AI Chatbot
Description	On one occasion, a question was sent and no response was returned. No error, no loading state — just silence.
Impact	Users don't know if the system is working. May repeatedly click send or abandon the chat.
Reproduction	Intermittent — occurred once during 25-question test session.
Fix	Add timeout handling and retry logic. Show error message if no response within reasonable time. Add loading indicator.

8.4 Low Severity

BUG-DOUBLE-POUND-01 — Double Currency Symbol
Field	Detail
Severity	LOW
Area	Fyn AI Chatbot
Description	One pension-related response displayed "£**£**32,253" — a double pound sign caused by markdown bold formatting interacting with the currency symbol.
Impact	Minor visual glitch. Does not affect data accuracy.
Reproduction	Ask Fyn about pension projections → may see double £ in formatted amounts.
Fix	Related to BUG-MARKDOWN-01. Fixing markdown rendering will resolve this.
 
9. Recommendations
Based on the testing findings, the following actions are recommended in priority order:

9.1 Must Fix Before Release
•	Fix data persistence bugs (LIABILITY-01, PROPERTY-COSTS-01, SPENDING-CATEGORIES-01, ESTATE-01): Audit every onboarding save handler and verify data reaches the database. This is the single most important fix — users are silently losing data.
•	Fix blank section pages (BLANK-PAGES-01): Without working section pages, users cannot manage their finances after onboarding. The app is essentially non-functional post-onboarding.
•	Remove Grok disclaimer (GROK-DISCLAIMER-01): Replace all third-party AI references with Fynla branding. This is a reputational and potentially legal issue.
•	Suppress debug data (DEBUG-01): Internal function calls and data structures must never appear in user-facing responses.

9.2 Should Fix Before Release
•	Fix dividend tax rate (DIVIDEND-RATE-01): Update from 35.75% to 33.75% for 2026/27. Tax accuracy is core to the platform's value proposition.
•	Fix onboarding blank spaces (BLANK-SPACE-01): Poor first impression during the most critical user journey.
•	Add markdown rendering (MARKDOWN-01/02/03): Either render markdown in the chat UI or switch Fyn to plain text output.

9.3 Nice to Fix
•	Add Fyn AI error handling (FYN-SILENT-01): Show loading states, timeouts, and retry prompts.
•	Fix double pound sign (DOUBLE-POUND-01): Will likely resolve when markdown rendering is fixed.

9.4 Systemic Recommendations
•	Add data validation/confirmation: After onboarding, show users a summary of all saved data and ask them to confirm. This would catch data loss bugs immediately.
•	Add save confirmation UI: Show toast notifications or success messages when data is saved. Silent failures are the worst UX pattern in financial software.
•	End-to-end testing pipeline: Automated tests should verify that data entered during onboarding appears correctly on section pages and in Fyn AI responses.
•	Fyn AI response sanitisation: Add a post-processing layer that strips debug data, third-party disclaimers, and raw markdown before displaying to users.
 
10. Conclusion
Fynla v1.0 shows strong foundations: the onboarding flow is logically structured, Fyn AI demonstrates impressive financial knowledge (80% pass rate on 25 diverse questions), and the 2026/27 UK tax thresholds are almost entirely correct. The platform's ambition to make financial planning accessible through AI-powered advice is clearly viable.
However, the current build has critical data persistence and rendering issues that make it unsuitable for release. Users would silently lose financial data during onboarding, find blank section pages when trying to manage their finances, and encounter raw technical debug data in AI responses. These issues fundamentally undermine the trust required of a financial planning platform.
The recommended path forward is to focus on the four critical bugs first (data persistence, blank pages, Grok branding, debug exposure), then address the high-severity items, and re-test the complete flow before any public release.

End of Report
