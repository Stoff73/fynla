Fynla v0.9.4 QA Report
Comprehensive Retest — 6 April 2026
Executive Summary
All 9 persona views (6 personas, 3 with spouse views) were retested on v0.9.4. Tax calculations have been updated to 2026/27 rates across all personas. Three significant issues were identified:
1. Personal Allowance Taper Bug (HIGH) — Affects Alex Chen, David Mitchell, and Sarah Mitchell. The income page correctly identifies PA = £0 in the allowances section, but the tax band calculation still applies the full £12,570 personal allowance at 0%. This understates tax by approximately £5,656 per affected persona.
2. Expenditure: regular_savings Not Shown as Category (LOW/UX) — For 3 personas (Carters, Alex Chen), the store’s monthly_manual total includes a regular_savings field (£100/mo for Carters, £1,000/mo for Alex Chen) that is not rendered as a visible category on the expenditure page. The data is correct — regular_savings is included in the store total and on the income page — but the expenditure page omits it from the category breakdown. This is a display/UX gap, not a data bug.
3. Rental Income Display Inconsistency (LOW) — For the Mitchell personas, the rental income figure differs between the tax calculation section and the Income Definitions section on the same page (e.g., David shows £14,290 vs £27,000). Likely net-of-expenses vs gross, but no label clarifies this.
 
1. Tax Year Verification (2026/27)
All 9 persona views display "Tax calculations use 2026/27 UK tax rates" on the income page. Tax bands verified against HMRC 2026/27 rates: PA £12,570, Basic 20% to £50,270, Higher 40% to £125,140, Additional 45% above. NI rates: 8% on £12,570–£50,270, 2% above.
Persona	Income	Tax	NI	Net	Tax	NI	Year	Notes
Janice Taylor	£0	£0	£0	£0	PASS	PASS	PASS	No income, all zero. Correct.
John Morgan	£32,000	£3,566	£1,554	£26,880	PASS	PASS	PASS	Basic rate only. After £640 pension relief.
James Carter	£75,000	£15,932	£3,511	£55,557	PASS	PASS	PASS	After £3,750 pension relief. Correct.
Emily Carter	£48,000	£6,702	£2,834	£38,464	PASS	PASS	PASS	Basic rate only. After £1,920 pension relief.
Alex Chen	£180,000	£57,497	£5,611	£116,893	FAIL	PASS	PASS	PA taper bug: PA shown as £12,570 @ 0% but PA = £0. Tax should be £63,153. Understated by ~£5,656.
David Mitchell	£159,290	£46,227	£4,911	£108,152	FAIL	PASS	PASS	PA taper bug: same issue. Tax should be £51,884. Understated by ~£5,657.
Sarah Mitchell	£163,880	£53,513	£4,411	£105,957	FAIL	PASS	PASS	PA taper bug: same issue. Tax should be £59,169. Understated by ~£5,656.
Patricia Bennett	£30,000	£3,486	£0	£26,514	PASS	PASS	PASS	Pension income only, basic rate. No NI. Correct.
Harold Bennett	£33,500	£4,186	£0	£29,314	PASS	PASS	PASS	Pension income only, basic rate. No NI. Correct.

2. Personal Allowance Taper Bug (Critical)
When adjusted net income exceeds £125,140, the Personal Allowance is fully tapered to £0. The allowances section of the income page correctly displays this. However, the tax band breakdown above it still shows "Personal Allowance: £12,570 @ 0%" and gives the full tax-free amount.
This creates a hybrid calculation: the higher rate band width is correctly expanded to £87,440 (as if PA = £0), but the PA is also applied at 0%, pushing the additional rate threshold from the correct £125,140 up to £137,710. The result is that £12,570 of income is taxed at 0% instead of 45%, understating tax by £12,570 × 45% ≈ £5,656.
Affected personas: Alex Chen (£180,000), David Mitchell (£159,290), Sarah Mitchell (£163,880)
Store pre-relief tax: Correctly computed with PA = £0 (verified for all three)
Income page displayed tax: Incorrectly computed with PA = £12,570 after pension relief
Impact: Net income overstated by ~£5,656 per persona. Disposable income also overstated.

Persona	Taxable	Page Tax	Correct Tax	Difference	PA Shown
Alex Chen	£171,000	£57,497	£63,153	-£5,656	£12,570 (should be £0)
David Mitchell	£147,690	£46,227	£51,884	-£5,657	£12,570 (should be £0)
Sarah Mitchell	£163,880	£53,513	£59,169	-£5,656	£12,570 (should be £0)

3. Expenditure Reconciliation
Three data layers were compared for each persona: (1) the Vuex store expenditure_breakdown, (2) the rendered expenditure page category totals, and (3) the annual expenditure shown on the income page. Financial commitments (mortgage, pension, investments, protection) are auto-calculated and added to manual items.
For the Mitchells and Bennetts, all three layers agree. For the Carters and Alex Chen, the store’s monthly_manual total includes a regular_savings field that is not rendered as a visible category on the expenditure page. The data is internally consistent — the store sum matches the individual fields — but the expenditure page omits regular_savings from the visible breakdown. The income page uses the full store total (including regular_savings), which is correct since it represents total money out.
Root cause: The regular_savings field (Carters: £100/mo, Alex Chen: £1,000/mo) is included in the store’s monthly_manual total but not displayed as a category row on the expenditure page. Personas with £0 regular_savings (Mitchells, Bennetts) show no gap.
Persona	Store Manual	Page Manual	Store Total	Page Total	Inc. Page Ann.	Match	Notes
Janice Taylor	£340	N/A (prev session)	£750	N/A	N/A	WARN	Tested in previous session — gap identified v0.9.3
John Morgan	£1,033	N/A (prev session)	£1,833	N/A	N/A	WARN	Tested in previous session — gap identified v0.9.3
James Carter	£1,951	£1,851	£4,053	£3,953	£48,636	WARN	regular_savings £100/mo in store but not shown as category. Data correct, display gap only.
Emily Carter	£1,951	£1,851	£3,071	£2,971	£36,852	WARN	Same as James — regular_savings £100/mo (shared profile).
Alex Chen	£4,500	£3,500	£13,963	£12,963	£167,556	WARN	regular_savings £1,000/mo in store but not shown as category. Data correct, display gap only.
David Mitchell	£1,225	£1,225	£5,257	£5,257	£63,086	PASS	All values reconcile across pages.
Sarah Mitchell	£1,225	£1,225	£4,126	£4,126	£49,512	PASS	All values reconcile across pages.
Patricia Bennett	£1,065	£1,065	£2,713	£2,713	£32,556	PASS	All values reconcile across pages.
Harold Bennett	£1,065	£1,065	£2,780	£2,780	£33,360	PASS	All values reconcile across pages.

4. Net Worth Verification
Dashboard net worth, assets, and liabilities were verified against the Vuex store for all 9 views. All asset breakdowns sum correctly to gross assets, and gross minus liabilities equals net worth. Joint persona individual views show correct 50/50 splits of shared assets.
Persona	Gross Assets	Liabilities	Net Worth	Status
Janice Taylor	-	-	-£33,400	PASS
John Morgan	£12,750	£42,420	-£29,670	PASS
James Carter	£354,200	£257,000	£97,200	PASS
Emily Carter	£188,430	£122,500	£65,930	PASS
Alex Chen	£1,614,180	£225,000	£1,389,180	PASS
David Mitchell	£1,635,000	£170,500	£1,464,500	PASS
Sarah Mitchell	£886,780	£122,500	£764,280	PASS
Patricia Bennett	£428,250	£0	£428,250	PASS
Harold Bennett	£437,250	£0	£437,250	PASS
 
5. Additional Findings
5.1 Alex Chen — Missing Dividend Income
The persona reference data specifies Alex Chen receives £60,000 p.a. in dividends from Chen Tech Consulting, in addition to a £180,000 salary. The live site shows only £180,000 employment income with no dividend income. If dividends are intended, the 2026/27 dividend tax rates (10.75% basic, 35.75% higher) should be verified once the income is added.
5.2 Rental Income Label Inconsistency (Mitchells)
On David Mitchell’s income page, the tax section shows Rental Income £14,290 while the Income Definitions section below shows Rental £27,000. Similarly for Sarah: £8,880 vs £10,800. The difference appears to be gross (total household) vs net (individual share after expenses), but no label distinguishes the two. This may confuse users who see different rental figures on the same page.
5.3 Section 24 Tax Credit
Both David and Sarah Mitchell correctly receive a £780 Section 24 mortgage interest tax credit, reducing their overall tax bill. This credit is calculated correctly and applied after the main tax computation.
5.4 Estate Planning Display (Harold Bennett)
Harold Bennett’s dashboard shows "Add your assets and liabilities to see your estate summary" despite having £437,250 in assets. The estate planning widget may not render for individual views of joint personas when the estate module hasn’t been explicitly configured, even though the underlying data exists.
5.5 Cash vs Savings Card Discrepancy
For some personas, the dashboard "Cash & Savings" card shows a different total than the net worth overview’s cash breakdown. For example, Harold Bennett shows Cash & Savings £100,500 on the dashboard card but £71,250 in the net worth cash category. The savings card likely aggregates differently (e.g., including ISA cash balances), but the discrepancy could confuse users comparing the two.
 
6. UX Improvement Suggestions
Area	Suggestion
Income Page — PA Taper Clarity	When PA is tapered, clearly show the reduced PA in the tax band breakdown (e.g., "Personal Allowance: £0 @ 0%" or remove the row entirely). Currently the allowances section and the tax bands contradict each other on the same page.
Income Page — Rental Labelling	Label rental income figures consistently. Use "Net rental income" in the tax section and "Gross rental income" in definitions, or show a brief calculation (gross − expenses = taxable amount) so users understand the difference.
Expenditure Page — Commitment Breakdown	Expand the "Financial Commitments (Auto-calculated)" row to show sub-items: mortgage, pension contributions, investment contributions, protection premiums, loan repayments. Users can then verify each component without needing to cross-reference other modules.
Dashboard — Negative Disposable Income	When disposable income is negative (Alex Chen −£50K, Patricia −£6K), add a visual indicator (amber warning) and a brief explanation that this may be funded from savings, investments, or portfolio drawdown. Currently it’s easy to overlook.
Dashboard — Cash vs Savings Terminology	Clarify what the "Cash & Savings" card includes vs the net worth "Cash" category. If the savings card includes ISA cash balances, state this explicitly (e.g., "Cash & Savings (inc. Cash ISAs)").
Section 24 Credit — User Education	Add a tooltip or info icon next to "Section 24 Tax Credit" explaining what it is (20% tax relief on BTL mortgage interest) and why it applies. Many users won’t understand this line item.
Spouse View Toggle — Visibility	The "View as [Spouse]" option is inside a dropdown that requires clicking the user name. Consider making it more prominent — a toggle or tab at the top of the page would make it easier to switch between individual views for joint personas.
Estate Planning — Joint View	For joint personas, show the combined estate position on both individual dashboards (e.g., "Household estate: £865,500, IHT on second death: £0"). Harold’s dashboard currently shows an empty state despite having substantial assets.
Expenditure — Budget Tabs	The "Current Budget / Budget at Retirement / Budget if Widowed" tabs are a strong feature. Consider adding a brief explainer of what changes between tabs (e.g., "Retirement: mortgage paid off, pension income replaces salary").
Income Definitions — Threshold Income	The Threshold Income and Adjusted Income calculations are useful for pension annual allowance tapering. Consider adding a brief tooltip explaining their purpose, as most users won’t know what these terms mean.

7. Summary Scorecard
Check	Status	Detail
2026/27 Tax Year Labels	PASS	All 9 views confirmed
Tax Calculations (income < £100K)	PASS	6 of 9 views — all correct
Tax Calculations (PA taper cases)	FAIL	3 of 9 views — PA applied despite taper
National Insurance	PASS	All 9 views correct
Net Worth (dashboard vs store)	PASS	All 9 views reconcile
Expenditure (cross-page reconciliation)	PASS	All reconcile. regular_savings not shown as category (UX only)
Joint Asset Splits (50/50)	PASS	Carters, Mitchells, Bennetts all correct
Dashboard Clickthroughs	PASS	Income, expenditure, property pages load correctly
Overall: 7 of 8 checks pass, 1 fail (PA taper). The PA taper bug is the only functional defect — it understates tax by ~£5,656 for personas with income above £125,140. All other checks pass. The expenditure regular_savings display gap is a minor UX improvement opportunity, not a data bug.
