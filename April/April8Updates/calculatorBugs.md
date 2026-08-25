A1. Executive Summary
Tested 15 calculators across 4 categories. 11 are publicly accessible; 4 require login. Of the 11 testable calculators, 9 passed, 1 had a minor issue, and 2 had significant calculation bugs.

Calculator	Category	Status	Key Finding
Income Tax	Planning Your Future	FAIL	NI rate wrong (12% vs 8%) and PA taper not applied above £100K.
Student Loan Repayment	Starting Out	PASS	All 4 plan types correct. Plan 5 threshold and write-off verified.
Savings Goal	Starting Out	PASS	Compound interest calculation correct. Growth chart included.
Emergency Fund	Starting Out	PASS	Correct arithmetic. Target months dropdown is a nice touch.
Mortgage Repayment	Building Foundations	PASS	PMT formula verified. Amortisation chart included.
Mortgage Affordability	Building Foundations	WARN	10% deposit row shows borrowing amount, not property price.
Stamp Duty (SDLT)	Building Foundations	FAIL	Uses expired 2024 temporary bands. Understates by 50% at £300K.
Personal Loan	Building Foundations	PASS	PMT formula verified. APR disclaimer included.
Compound Interest	Building Foundations	PASS	Correct calculation with contributions overlay chart.
Pension Growth	Planning Your Future	PASS	Projection consistent with ~5% growth. Clear layout.
Pension Tax Relief	Planning Your Future	PASS	Relief at Source vs Net Pay distinction — excellent.
Salary Sacrifice	Planning Your Future	N/A	Login-gated. Silent failure — no redirect or message.
Retirement Budget Planner	Planning Your Future	N/A	Login-gated. Same silent failure issue.
Life Insurance Needs	Protecting and Growing	N/A	Login-gated. Same silent failure issue.
Income Protection	Protecting and Growing	N/A	Login-gated. Same silent failure issue.
 
A2. Critical Bugs
A2.1 Income Tax Calculator — NI Rate Wrong (12% instead of 8%)
The National Insurance rate used in the calculator is 12%, which was the pre-April 2024 rate. The employee NI rate was reduced to 10% in January 2024 and further to 8% in April 2024, where it remains for 2026/27.
Impact at £50,000 salary: Calculator shows £4,492 NI. Correct figure is £2,994. Overstated by £1,498/year.
Impact at £130,000 salary: Calculator shows £6,119 NI. Correct figure is £4,611. Overstated by £1,508/year.
A2.2 Income Tax Calculator — Personal Allowance Taper Not Applied
For income over £100,000, the Personal Allowance should reduce by £1 for every £2 above £100,000, fully eliminated at £125,140. The calculator uses the full £12,570 PA regardless of income level.
Impact at £130,000 salary: Calculator shows income tax of £39,675. Correct figure is £44,703 (PA = £0). Understated by £5,028.
A2.3 Stamp Duty Calculator — Outdated SDLT Bands
The calculator uses the temporary SDLT bands that expired 1 April 2025 (nil-rate band at £250,000). The correct permanent thresholds are: 0% up to £125,000, 2% on £125,001-£250,000, 5% on £250,001-£925,000, 10% on £925,001-£1,500,000, 12% above £1,500,000.
Impact at £300,000 home mover: Calculator shows £2,500 SDLT. Correct figure is £5,000. Understated by £2,500 (50%).
 
A3. Minor Issues
A3.1 Mortgage Affordability — 10% Deposit Display
The 10% deposit row shows the max borrowing amount (£192,900) rather than the property price. Should be £192,900 / 0.90 = £214,333. The 15% and 20% deposit figures are correct.
A3.2 Placeholder Values Not Read by Form
All calculators display pre-populated placeholder values (e.g. '50,000' in the income field) but these are HTML placeholders, not actual input values. Clicking Calculate with visible placeholders produces £0 results. Users must manually clear and re-type. This is a cross-calculator UX issue.
 
A4. UX Improvement Suggestions
Area	Suggestion
Income Tax Calculator	Add a net take-home pay summary line (the figure most users actually want). Show a tax band breakdown. Add monthly breakdown alongside annual. Clarify the ambiguous 'Taxable Income' label.
Login-Gated Calculators	Clicking Salary Sacrifice, Retirement Budget, Life Insurance, or Income Protection does nothing — silent failure. Show a login modal/overlay or redirect with return URL. The 'Free trial' badge is easily missed.
Default/Placeholder Values	Convert HTML placeholders to actual default values. Auto-calculate on page load using defaults so users see example results immediately.
Real-Time Calculation	Calculate results live as users type (debounced) rather than requiring a button click. Eliminates the placeholder problem and matches competitor UX.
Stamp Duty Calculator	Add a more prominent first-time buyer toggle. Show savings vs home mover. Add effective tax rate percentage.
Student Loan Calculator	Add comparison mode for multiple plans side-by-side. Add brief plan eligibility explanations. Consider voluntary overpayment option.
Pension Calculators	Pension Growth could show inflation-adjusted figure. Pension Tax Relief's distinction of Relief at Source vs Net Pay is excellent and rare.
General	Add 'Share results' / 'Download PDF' option. Add deep-linking via URL parameters. Add a free vs login comparison table on the /calculators page.

A5. QA Summary Scorecard
Metric	Result
Total Calculators	15
Publicly Accessible	11
Login Required	4
PASS	9
WARN	1
FAIL	2
N/A (login-gated)	4
Critical Bugs	3 (NI rate, PA taper, SDLT bands)
 
Part B: Enhancement Recommendations

B1. Competitive Analysis Summary
Fynla's 15 calculators were compared against leading UK competitors: MoneySavingExpert, MoneyHelper, TheSalaryCalculator, HMRC, Rightmove, and major bank/platform calculators. The analysis identified 33 specific enhancements and 5 new calculator recommendations.

Priority	Count	Key Items
HIGH	17	Pension inputs on income tax, SDLT buyer types, new pension calculator, login-gated UX fix
MEDIUM	11	Joint mortgage, overpayments, amortisation, IHT calculator, budget planner
LOW	5	Loan APR clarification, student loan projections, early repayment
 
B2. Top 5 Quick Wins (Low Effort, High Impact)
Enhancement	Effort	Priority	Why
Add Scottish income tax toggle	Low	HIGH	5.5M Scottish taxpayers currently get wrong results
Add buyer-type dropdown to SDLT calc	Medium	HIGH	Only accurate for 1 of 4 buyer categories
Show login prompt for gated calculators	Low	HIGH	Four calculators silently fail — poor first impression
Add student loan plan to Income Tax calc	Low	HIGH	Single view of all deductions is the competitor standard
Add joint applicant to Mortgage Affordability	Low	MEDIUM	Most home purchases involve two earners
 
B3. Detailed Enhancement Recommendations
Income Tax Calculator
Priority: HIGH
Enhancement	Detail & Rationale	Competitors	Effort
Pension contribution inputs	Add fields for personal pension contributions (relief at source) and salary sacrifice. Every major competitor includes these. Omitting them overstates the tax bill for anyone contributing to a pension.	TheSalaryCalculator, UKTaxCal, ukcalculator.com, iCalculator	Medium
Salary sacrifice options (childcare, cycle-to-work, EV)	Allow non-pension salary sacrifice amounts. Reduces gross pay before tax and NI. EV salary sacrifice schemes have surged in popularity.	TheSalaryCalculator, Zelt, Legal & General	Medium
Student loan repayment selection	Include student loan plan selection (Plan 1/2/4/5/Postgrad) as a deduction line in the tax calculator. Competitors show take-home pay after all deductions in a single view.	TheSalaryCalculator, HMRC, ukcalculator.com	Low
Scottish income tax rates toggle	Scotland has different bands (19% Starter to 48% Top). A simple toggle would cover ~5.5M Scottish taxpayers accurately.	TheSalaryCalculator, HMRC, ukcalculator.com, iCalculator	Low
Pay period selector (annual/monthly/weekly/hourly)	Let users input salary in their natural unit and see results broken down across all periods.	TheSalaryCalculator, ukcalculator.com, iCalculator	Low
Dividend and self-employment income	Optional dividend income input with £500 allowance and dividend tax rates. Self-employment toggle for Class 2/4 NI.	UKTaxCalculators.co.uk, iCalculator, HMRC	High
Year-on-year comparison / tax year selector	Compare tax position across years. Useful when rates freeze or NI changes.	iCalculator, UKTaxCalculators.co.uk	Medium

Stamp Duty Calculator
Priority: HIGH
Enhancement	Detail & Rationale	Competitors	Effort
Buyer type selector (home mover / FTB / additional / non-UK)	Current calc only handles home movers. FTB relief (0% to £300K), additional property surcharge (5%), and non-UK resident surcharge (2%) all need separate handling.	MoneyHelper, Rightmove, MSE, Zoopla	Medium
Wales (LTT) and Scotland (LBTT) support	Both nations have different bands and rates. A country toggle makes the calc UK-wide accurate.	MoneyHelper, Rightmove, Pine	Medium
Band-by-band results breakdown	Show a table of how much tax applies at each band. Builds trust and helps users understand progressive taxation.	MoneyHelper, stampdutycalc.co.uk, Rightmove	Low

Mortgage Affordability Calculator
Priority: MEDIUM
Enhancement	Detail & Rationale	Competitors	Effort
Joint applicant support	Second income field for joint applications. Most home purchases involve two earners.	Barclays, MoneyHelper, MSE, NatWest	Low
Existing debt/commitment inputs	Fields for monthly outgoings. Lenders stress-test against commitments.	Barclays, MoneyHelper, MortgagePulse	Low
Stress test / interest rate sensitivity	Show payments if rates rose 2-3%. FCA requires lenders to stress-test at SVR (~7.5%).	Barclays, MoneyHelper, MortgagePulse	Low
LTV calculator with deposit breakdown	Show how different deposit sizes affect payments and total interest.	MSE, Rightmove, L&C	Low

Mortgage Repayment Calculator
Priority: MEDIUM
Enhancement	Detail & Rationale	Competitors	Effort
Overpayment calculator	Show how overpayments reduce total interest and shorten the term. One of the most popular mortgage calc features.	MSE, MoneyHelper, Halifax, Nationwide	Medium
Interest-only vs repayment comparison	Toggle to compare monthly cost and total interest between the two types.	MSE, MoneyHelper, Barclays	Low
Amortisation schedule	Year-by-year table showing how balance decreases, split into principal and interest.	ukcalculator.com, calculator.net, Nationwide	Medium

Personal Loan Calculator
Priority: LOW
Enhancement	Detail & Rationale	Competitors	Effort
APR vs flat rate clarification	Clarify rate type. Show APR, total cost of credit, and side-by-side scenario comparison.	MSE, MoneyHelper, Barclays	Low
Early repayment calculator	Show savings from early repayment including typical early repayment charges.	MSE, MoneyHelper	Low

Student Loan Calculator
Priority: LOW
Enhancement	Detail & Rationale	Competitors	Effort
Total repayment and write-off projection	Show total repaid before write-off (25/30/40 years). Many graduates never fully repay.	MSE, Student Finance Calculator	Medium
Salary growth projection	Model salary increases over time. Simple annual growth % makes projections realistic.	MSE Student Loan Calculator	Medium

Login-Gated Calculators
Priority: HIGH
Enhancement	Detail & Rationale	Competitors	Effort
Add feedback when login required	Show a tooltip or modal explaining login is needed. Currently silent failure on all four calculators.	N/A (UX fix)	Low
Offer simplified public versions	Most competitors offer basic versions publicly with premium features behind registration. Drives sign-ups while providing value.	Legal & General, Aviva, HL	Medium

 
B4. New Calculator Recommendations
Based on competitor analysis and Fynla's positioning as a financial planning platform:
Calculator	Priority	Rationale	Competitors
1. Pension Pot Projection	HIGH	Biggest gap vs all competitors. Core financial planning tool. Inputs: age, pot size, contributions, growth rate.	MoneyHelper, Aviva, HL, Fidelity, PensionBee, Standard Life
2. Pension Tax Relief	HIGH	Simple but high-value. Shows how tax relief boosts contributions. Low effort to build.	ukcalculator.com, PensionBee, TheSalaryCalc
3. IHT Estimator	MEDIUM	Natural fit for Fynla's planning focus. NRB, RNRB, spouse transfers. Increasingly relevant with frozen thresholds.	MoneyHelper, HMRC, Standard Life, HL
4. Pension Drawdown	MEDIUM	How long will a pot last? Could integrate with existing login-gated Retirement Budget Planner.	MoneyHelper, HL, Fidelity, MeetWarren
5. Budget Planner	MEDIUM	Fynla already tracks expenditure for personas. A public version would drive sign-ups.	MoneyHelper, MSE BillBuster, NatWest
 
B5. Competitor Feature Comparison Matrix
Feature	Fynla	MSE	Salary Calc	HMRC	MoneyHelper
Income Tax (PAYE + NI)	Yes	Yes	Yes	Yes	Yes
Pension contribution inputs	No	Yes	Yes	Yes	Yes
Salary sacrifice calculator	Gated	Yes	Yes	No	No
Scottish tax rates	No	Yes	Yes	Yes	Yes
Student loan (standalone)	Yes	Yes	Yes	Yes	No
Student loan in tax calc	No	Yes	Yes	Yes	No
Dividend / self-employment	No	No	Yes	Yes	No
Stamp duty (standard)	Yes*	Yes	Yes	Yes	Yes
SDLT buyer types (FTB/additional)	No	Yes	Yes	Yes	Yes
Scotland LBTT / Wales LTT	No	Yes	No	No	Yes
Mortgage repayment	Yes	Yes	Yes	Yes	Yes
Mortgage affordability	Yes	Yes	Yes	Yes	Yes
Mortgage overpayment	No	Yes	No	Yes	Yes
Pension pot projection	No	No	No	Yes	Yes
Pension drawdown	No	No	No	No	Yes
IHT estimator	No	No	No	No	Yes
Budget planner	No	Yes	No	No	Yes
Personal loan	Yes	Yes	No	No	No
Savings / compound interest	Yes	No	No	No	No
* Fynla's SDLT calculator uses expired 2024 temporary bands — see Part A, section A2.3.
 
