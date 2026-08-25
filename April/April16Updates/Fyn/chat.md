User
09 Apr 2026 12:19
Do I have a will? When was it last updated and who is my executor?
Fyn
09 Apr 2026 12:19
No will is recorded in your estate planning data yet.

- **Last updated**: Not applicable (none on file)
- **Executor**: Not specified

Having a will in place is a smart step for protecting Sarah and Emily — it could be worth exploring the will builder in the app. Shall I navigate you to the estate planning section to get started, or discuss options?


- get_module_analysis: module: estate, analysis: [9 items], missing_data: [4 items]

User
09 Apr 2026 12:25
What is my estimated inheritance tax liability? What is the nil rate band and residence nil rate band for 2026/27?
Fyn
09 Apr 2026 12:26
Your estimated Inheritance Tax liability is **£7,200**, based on your current net worth of **£803,000** (including **£425,000** property share, **£285,000** pensions, **£68,000** investments, and **£25,000** savings). This assumes passing to non-exempt beneficiaries after spousal exemption.

For 2026/27 (rules frozen until 2028):
- **Nil Rate Band**: **£325,000** (transferable from spouse, potentially **£650,000** combined)
- **Residence Nil Rate Band**: **£175,000** (for main residence to direct descendants like Emily; transferable to **£350,000** combined; tapers above **£2 million** estates)

Tax rules depend on individual circumstances (e.g. wills, trusts, gifts, growth) and may change — notably, pensions will be IHT-liable from April 2027. It could be worth exploring gifting or trusts with a specialist solicitor.

Your estate module is 58% complete — adding will details or gifts would refine this. Shall I navigate to estate planning or help add data?

_Disclaimer: Grok is not a financial adviser; please consult one. Don't share information that can identify you._

User
09 Apr 2026 12:26
How much interest will I earn on my HSBC savings account this year? Is it within my Personal Savings Allowance?
Fyn
09 Apr 2026 12:27
Your HSBC easy access savings account (£**25,000** balance, **3.5%** interest rate) could earn approximately **£875** gross interest over a full tax year (assuming the rate stays constant and no withdrawals/additions).

As a higher rate taxpayer, your **Personal Savings Allowance** is **£500** tax-free. The excess **£375** would be taxable at your **40%** marginal rate (£**150** tax), leaving **£725** net interest.

Tax rules are based on current UK legislation for the 2026/27 tax year — treatment depends on your individual circumstances and may change. Interest rates can also fluctuate.

This contributes well to your **11.57 months** emergency fund cover. Would you like a projection with different rates, to check FSCS protection (£85,000 limit per institution), or navigate to savings details?


- list_records: entity_type: savings_account
- get_module_analysis: module: savings
- get_tax_information: savings_config, income_tax

User
09 Apr 2026 12:29
How much pension annual allowance do I have remaining for 2026/27?
Fyn
09 Apr 2026 12:29
Your **Annual Allowance** for pension contributions in 2026/27 is **£60,000** (the maximum that can receive tax relief). Tax rules are based on current UK legislation for the 2026/27 tax year — treatment depends on your individual circumstances and may change.

- **Projected use** (Aviva Master Trust): **£12,350** total (£4,750 employee at 5% + £7,600 employer at 8% on your **£95,000** salary).
- **Remaining**: Approximately **£47,650** (plenty of headroom for additional contributions, up to your relevant UK earnings of **£95,000**).

No contributions from other pensions are recorded, and your adjusted net income (**£92,750**) means no taper applies. You may want to consider maximising this via salary sacrifice for National Insurance savings (as flagged in your top recommendations).

Shall I run a what-if for higher contributions, or check carry forward?


- get_tax_information: pension_allowances, annual_allowance:60000
- list_records: dc_pension, count:1 (Aviva Master Trust)
- get_tax_information: income_definitions, relevant earnings:95000, adjusted_net_income:92750

User
09 Apr 2026 12:37
If I increased my pension contribution to 10% employee, what would my take-home pay change to?
Fyn
09 Apr 2026 12:38
Increasing your Aviva Master Trust employee contribution from **5%** (£**4,750**/year) to **10%** (£**9,500**/year, extra **£4,750**) under a net pay arrangement would reduce your taxable income and National Insurance earnings by that amount.

### Take-home pay impact (2026/27 tax year)
- **Tax saving**: **£1,900** (**40%** relief)
- **National Insurance saving**: **£95** (**2%** marginal rate)
- **Net reduction**: **£2,755**/year (**£230**/month)
- **New annual net income**: **£89,995** (**£7,500**/month from current **£7,729**)

Your projected pension grows by the extra **£4,750** + employer **£7,600**, within **£60,000** Annual Allowance (£**47,650** remaining). Fits your **£2,741** surplus. Tax and National Insurance rules depend on individual circumstances and may change.

You may want to consider salary sacrifice (top recommendation) for ~**£380** extra National Insurance saving/year. It could be worth discussing with your employer or a regulated adviser.

1. Confirm via payslip if net pay or salary sacrifice.
2. Update your pension record here if implemented.
3. Review at tax year-end.

Shall I update your contribution %, run a retirement projection, or navigate to pensions?


- get_tax_information: income_tax, national_insurance, income_definitions, pension_allowances
- list_records: dc_pension
- get_module_analysis: retirement