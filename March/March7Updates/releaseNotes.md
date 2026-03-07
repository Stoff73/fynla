# Fynla Release Notes — March 7, 2026

**Version:** v0.9.0 | **Theme:** Smarter Planning, Less Friction

---

## What's New

### Faster Onboarding

Getting started with Fynla is now much quicker. New users can choose between a **Quick Setup** (3 steps) or the existing **Full Setup** (11 steps).

- **Quick Setup** asks only for your personal details, planning focus areas, and a quick summary of your assets — you're on your dashboard in under 2 minutes
- **Profile Completion Cards** appear on your dashboard after Quick Setup, guiding you to add detail to each module at your own pace
- You can still choose Full Setup if you prefer to enter everything upfront

### Tax Strategies

A new **Tax Strategies** section on the UK Taxes page provides personalised recommendations to help you make the most of your tax allowances.

- **ISA planning** — Guidance on which ISA type to prioritise based on your portfolio and time horizon
- **Pension relief** — Recommendations on contribution levels, including carry forward of unused Annual Allowance from previous years
- **Capital Gains Tax planning** — Suggestions for gain and loss timing, annual exemption usage, and bed-and-ISA opportunities
- **Spousal transfers** — Recommendations for transferring assets between spouses to optimise Personal Allowance, Capital Gains Tax allowance, and Dividend Allowance usage

### Household Planning (for Couples)

Married and partnered users with data sharing enabled now see a **Household** section on their dashboard.

- **Combined Net Worth** — See your total household wealth broken down by asset type, with each partner's contribution shown
- **Spousal Optimisations** — Actionable recommendations for tax-efficient asset allocation between partners
- **"What If" Scenario** — Model the financial impact if either partner were to pass away, including Inheritance Tax implications, income changes, and pension effects

### Retirement Drawdown Strategies

The retirement module now includes a **Decumulation Strategy** card showing how your pension savings might be drawn down in retirement.

- Compare drawdown approaches (level income, front-loaded, inflation-linked)
- See how care costs affect your sustainable withdrawal rate
- View projected fund values over your retirement timeline

### Smarter Recommendations

Recommendations across all modules are now **personalised** to your specific situation.

- Recommendations consider your family circumstances, estate planning position, and investment portfolio — not just the module they originate from
- Cross-module insights highlight connections between your financial decisions (e.g., "Building your emergency fund before increasing pension contributions protects against needing to access pension early")
- Urgency and impact estimates are tailored to your actual financial data

### Letter to Spouse Validation

If you maintain a Letter to Spouse, Fynla now **cross-checks** it against your other financial records and highlights inconsistencies.

- Warns if your named executor isn't recorded in your will
- Flags if your life insurance may not cover your projected Inheritance Tax liability
- Alerts if assets mentioned in your letter don't match your recorded net worth

### Risk Profile Improvements

Your investment risk assessment is now more comprehensive and responsive.

- **9 factors** (up from 7) now inform your risk capacity, including your age and income stability
- **Life event awareness** — Your risk profile automatically flags for review when significant life events occur (marriage, retirement, children)
- **Mismatch warning** — If your stated risk preference differs significantly from your calculated risk capacity, you'll see an alert suggesting a review

### Configurable Assumptions

New options in Settings let you personalise the assumptions used in your financial projections.

- **Life expectancy** — Override the default with your own estimate (60-110), or leave it on the actuarial default based on your age and sex
- **Care costs** — Add an expected annual care cost and the age you expect it to start, which feeds into your retirement sustainability projections
- **Will review tracking** — Record when you last reviewed your will; Fynla will remind you if it's been more than 3 years

### Goal Dependencies

Goals can now be linked to show which ones depend on others. For example, "Pay off mortgage" might need to complete before "Retire early" becomes achievable. Fynla will highlight when a goal is blocked by an incomplete prerequisite.

---

## Improvements

### Cleaner Forms

Several form fields that weren't contributing to your financial analysis have been removed to reduce clutter:

- **Investment accounts** — Removed company sector, voting rights, and dividend rights from private investment forms (these weren't used in any analysis)
- **Personal information** — National Insurance number removed from the profile form (sensitive data that wasn't needed for financial planning calculations)
- **Family members** — Education status removed from the family member card view (still available in the edit form where it's used for dependent age calculations)

### Estate Planning

- Will planning now includes a "Last Reviewed" date field
- A reminder appears if your will hasn't been reviewed in over 3 years
- Inheritance Tax planning benefits from better cross-module data (letter to spouse, household coordination)

---

## Technical Details

- 120 files changed across backend and frontend
- 5 new database migrations (non-destructive — adds columns and one new table)
- 8 new API endpoints
- 13 new Vue components, 7 new PHP services, 1 new agent
- All existing functionality preserved — no breaking changes
