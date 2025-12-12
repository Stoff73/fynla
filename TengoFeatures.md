# Fynla Financial Planning Application - Complete Feature Documentation

This document provides a comprehensive description of all features currently present and working in the Fynla financial planning application. All information has been verified by examining the actual codebase. Features are described in plain language without technical terms.

---

## Table of Contents

1. [Overview](#overview)
2. [Getting Started - Registration and Login](#getting-started---registration-and-login)
3. [Initial Setup Wizard (Onboarding)](#initial-setup-wizard-onboarding)
4. [Main Dashboard](#main-dashboard)
5. [User Profile](#user-profile)
6. [Protection Planning](#protection-planning)
7. [Cash and Savings](#cash-and-savings)
8. [Investments](#investments)
9. [Retirement Planning](#retirement-planning)
10. [Estate Planning](#estate-planning)
11. [Net Worth Overview](#net-worth-overview)
12. [Properties and Mortgages](#properties-and-mortgages)
13. [Spouse and Family Management](#spouse-and-family-management)
14. [Document Upload](#document-upload)
15. [Administrator Features](#administrator-features)
16. [Features Not Yet Available](#features-not-yet-available)

---

## Overview

Fynla is a comprehensive financial planning application designed specifically for individuals and families in the United Kingdom. The application helps users organise, track, and plan their finances across five main areas:

- **Protection**: Insurance policies that protect you and your family
- **Savings**: Cash savings and emergency funds
- **Investments**: Investment accounts and portfolios
- **Retirement**: Pension planning and retirement income
- **Estate**: Planning for what happens to your wealth when you pass away

The application uses current United Kingdom tax rules and allowances (2025/26 tax year) to provide accurate calculations and recommendations.

---

## Getting Started - Registration and Login

### Creating an Account

When you first visit Fynla, you can create a new account by providing:
- Your full name
- Email address
- A password of your choice

After registration, you will receive a confirmation and can immediately begin using the application.

### Logging In

If you already have an account, simply enter your email address and password to access your personal financial dashboard.

### Demo Access

A demonstration account is available to explore the application without creating your own data:
- Demo email: demo@fps.com
- Demo password: password

---

## Initial Setup Wizard (Onboarding)

When you first log in, Fynla guides you through a step-by-step setup process to gather your financial information. You can complete all steps or skip any that are not relevant to you.

### Welcome Screen

The onboarding process begins with a welcome screen that greets you by name and explains what the application can do. The screen lists the main features available:

- Onboarding and fact-find process
- Spouse account linking for jointly owned assets and liabilities
- Dashboard view
- Net Worth dashboard
- Estate Planning dashboard
- Protection dashboard
- User profile section
- Balance sheet for single and linked accounts
- Inheritance Tax calculator and liability indicator
- Comprehensive Estate Planning

The welcome screen also includes a note about security measures in place (encrypted database, secure login, account separation).

There is also a personal note from the developer thanking users for testing the application and inviting feedback.

Click the "Continue to Onboarding" button to begin entering your information.

### Step 1: Personal Information

Enter your basic personal details:
- Full name
- Date of birth
- Gender
- Marital status (single, married, divorced, or widowed)
- National Insurance number (optional)
- Contact telephone number
- Home address (street, city, postcode)

### Step 2: Family and Beneficiaries

Add details of your family members and who you want to benefit from your estate:
- Spouse or partner (can link their Fynla account)
- Children (with dates of birth)
- Parents
- Other dependants

This information helps calculate available tax reliefs such as spouse exemption and residence nil rate band.

### Step 3: Where You Live (Domicile)

Confirm your residence status for tax purposes:
- Country of birth
- Whether you are domiciled in the United Kingdom
- If you came from another country, when you arrived in the UK
- Years of UK residence (calculated automatically)

This information is important for tax calculations, particularly inheritance tax. Non-UK domiciled individuals have different rules.

### Step 4: Your Assets

Add information about things you own:
- Properties (main home, second homes, rental properties)
- Savings accounts
- Investment accounts
- Pensions
- Business interests
- Valuable items (chattels)

For each property, you can also add mortgage details if applicable.

### Step 5: Your Debts and Liabilities

Add information about money you owe:
- Mortgages (if not already added with properties)
- Personal loans
- Credit card balances
- Car finance
- Student loans
- Overdrafts
- Any other debts

Liabilities reduce your taxable estate for inheritance tax purposes.

### Step 6: Protection Policies

Add any insurance policies you currently have:
- Life insurance policies
- Critical illness cover
- Income protection insurance
- Disability insurance
- Sickness and illness policies

For each policy, you can record the provider, amount of cover, monthly cost, and policy dates.

If you do not have any protection policies, you can tick a box to confirm this. Protection policies can provide money for your estate to pay inheritance tax bills.

### Step 7: Income Details

Record all your sources of income:
- Employment status (employed, self-employed, part-time, retired, etc.)
- Employer or business name
- Industry sector
- Employment income (salary or wages from your job)
- Self-employment income (if you run your own business)
- Dividend income (from shares or investments)
- Any other regular income

### Step 8: Monthly Spending

Record how much you typically spend each month across various categories:
- Food and groceries
- Transport and fuel
- Healthcare and medical expenses
- Insurance premiums
- Mobile phones
- Internet and television subscriptions
- Other subscriptions and memberships
- Clothing and personal care
- Entertainment and dining out
- Holidays and travel
- Pet expenses
- Childcare costs
- School fees, school lunches, and extras
- Children's activities and clubs
- Any other expenses

The system calculates your total monthly and annual expenditure automatically.

### Step 9: Will Information

Indicate whether you have a will:
- Yes, I have a will (with date last updated and executor name)
- No, I do not have a will
- Prefer not to say

Will status is important for understanding how your estate would be distributed and identifying gaps in your estate plan.

### Step 10: Trust Information

Record any trusts you have established or are beneficiaries of. Trusts can affect your inheritance tax calculation due to transfers made during your lifetime.

### Completion

At the end of the wizard, you are taken to your personalised dashboard with all your information ready to use.

**Skip Option**: At any step, you can choose to skip and return later. The system explains why each piece of information is important and records which steps have been completed or skipped.

---

## Main Dashboard

After logging in, you arrive at the main dashboard, which provides an overview of your entire financial picture.

### What You See

The dashboard displays:

- **Net Worth Summary**: The total value of everything you own minus everything you owe
- **Quick Access Cards**: Shortcuts to each of the five planning modules (Protection, Savings, Investment, Retirement, Estate)
- **Profile Completeness**: A progress indicator showing how much of your financial information has been entered

### Navigation

From the dashboard, you can access:
- Your user profile
- Each of the five financial planning modules
- Net worth detailed breakdown
- Your properties
- Settings and preferences

---

## User Profile

Your profile page contains ten sections where you can view and update your personal and financial information.

### Section 1: Personal Information

View and edit your:
- Full name
- Email address
- Date of birth
- Gender
- Marital status
- National Insurance number
- Telephone number
- Full address (street, city, county, postcode)

### Section 2: Domicile Status

Your tax residence information:
- Whether you are a United Kingdom resident
- Your domicile status (where you are legally considered to "belong")
- If relevant, details about when you arrived in the UK and deemed domicile dates

### Section 3: Health Information

Your health and lifestyle details (used for protection planning):
- Current health status
- Smoking status

### Section 4: Family Members

A list of your family members:
- Spouse or partner
- Children
- Parents
- Other dependants

You can add, edit, or remove family members. For each person, you record their name, date of birth, and relationship to you.

### Section 5: Letter to Spouse

A private section where you can write guidance for your spouse or partner to follow if something happens to you. This might include:
- Important contacts (solicitor, accountant, financial adviser)
- Location of important documents
- Account access information
- Your wishes for various matters
- Financial priorities and instructions

This letter is only visible to you (and your spouse if you share access).

### Section 6: Income and Occupation

Your employment and income details:
- Employment status (employed, self-employed, part-time, retired, etc.)
- Current employer or business name
- Annual salary or business income
- Other income sources (rental, dividends, interest, etc.)

### Section 7: Expenditure

Your monthly spending breakdown across all categories, with totals calculated automatically:
- Essential expenses (housing, food, transport)
- Lifestyle expenses (entertainment, holidays)
- Family expenses (childcare, education)
- Financial expenses (savings, debt payments)

### Section 8: Assets

A summary of everything you own:
- Properties
- Savings accounts
- Investment accounts
- Pension values
- Other valuable items

### Section 9: Liabilities

A summary of everything you owe:
- Mortgages
- Loans
- Credit cards
- Other debts

### Section 10: Financial Statements

Personal accounting statements generated from your data:
- **Profit and Loss Statement**: Your income minus your expenses
- **Cash Flow Statement**: Money coming in and going out
- **Balance Sheet**: Your assets minus your liabilities

---

## Protection Planning

The Protection module helps you understand whether you and your family have adequate insurance cover. It has three tabs: Policy Overview, Gap Analysis, and Strategy.

### Policy Overview Tab

This shows all your existing protection policies:

**Life Insurance Policies**
- Policy type (term life, whole of life, decreasing term, level term, family income benefit)
- Provider name
- Amount of cover (sum assured)
- Monthly premium cost
- Premium payment frequency
- Policy start and end dates
- Policy reference number

**Critical Illness Policies**
- Policy type (standalone, accelerated, additional)
- Provider name
- Amount of cover
- Monthly premium cost
- Policy dates

**Income Protection Policies**
- Provider name
- Monthly benefit amount
- Waiting period (how long before payments begin)
- Whether the benefit increases with inflation
- Monthly premium cost
- Policy dates

**Disability Policies**
- Coverage type (accident only, or accident and sickness)
- Provider name
- Benefit amount
- Payment frequency
- Policy dates

**Sickness and Illness Policies**
- Provider name
- Benefit amount
- Payment frequency
- Policy dates

### Policy Management

For each policy type, you can:
- **Add**: Create a new policy record
- **Edit**: Update details of an existing policy
- **Delete**: Remove a policy record (with confirmation)
- **Upload Document**: Upload a policy document for automatic data extraction

### Coverage Summary

The system displays summary information:
- Total life insurance cover
- Total critical illness cover
- Total income protection (annual benefit)
- Total number of policies
- Combined monthly premium cost

### Risk Exposure Analysis

The application calculates:
- **Human Capital**: The present value of your future earnings until retirement
- **Total Debt**: All your outstanding liabilities
- **Total Coverage**: The sum of all your protection policies
- **Coverage Ratio**: What percentage of your exposure is covered by insurance

### Charts and Visualisations

- **Premium Breakdown Chart**: Shows how your total premium is split across policy types
- **Coverage Timeline Chart**: Shows when each policy starts and ends

### Gap Analysis Tab

The system analyses your protection needs and identifies any gaps:

**Life Insurance Gap**
- Calculates how much cover you need based on:
  - Outstanding mortgage
  - Other debts
  - Replacement income for dependants
  - Education costs for children
- Compares this to your existing cover
- Shows the shortfall (if any)

**Income Protection Gap**
- Calculates how much of your income should be protected
- Compares to your existing income protection policies
- Shows the monthly shortfall (if any)

**Critical Illness Gap**
- Calculates recommended critical illness cover
- Compares to existing policies
- Shows any shortfall

### Strategy Tab

Based on your analysis, the system provides specific recommendations and strategies:
- Types of additional cover to consider
- Suggested cover amounts
- Priority order for addressing gaps
- Buying new policies
- Increasing existing cover
- Writing policies in trust
- Family income benefit alternatives

### No Protection Notice

If you have no policies recorded, the system displays:
- An explanation of why protection is important
- Key reasons to consider protection
- A button to view the gap analysis
- An option to confirm you intentionally have no protection

---

## Cash and Savings

The Savings module helps you track your cash savings and work towards savings goals. It has four tabs: Cash Overview, Emergency Fund, Savings Goals, and Strategy.

### Cash Overview Tab

This shows all your savings accounts:

**Account Details**
For each savings account, you can record:
- Bank or building society name
- Account type (easy access, notice account, fixed term, cash ISA)
- Current balance
- Interest rate being paid
- Access type and notice period
- Whether this is an emergency fund
- Whether this is a tax-free ISA
- Ownership (individual, joint, or trust)

**Summary Cards**
- Total savings across all accounts
- Emergency fund runway (how many months of expenses you could cover)
- Number of accounts

### Account Management

You can:
- **Add Account**: Create a new savings account record
- **View Details**: Click any account to see full details
- **Edit**: Update account information
- **Delete**: Remove an account (with confirmation)
- **Upload Statement**: Upload a bank statement for automatic data extraction

### ISA Allowance Tracking

The system tracks your Individual Savings Account (ISA) usage:
- Current tax year ISA allowance: £20,000
- Amount used across all ISAs (Cash ISAs from savings, Stocks and Shares ISAs from investments)
- Remaining allowance
- Warning if you are approaching or exceeding the limit

The ISA year runs from 6th April to 5th April.

### Emergency Fund Analysis

The system calculates:
- Your recommended emergency fund (typically 3-6 months of expenses)
- Your current emergency fund total (from accounts marked as emergency fund)
- The shortfall or surplus
- How many months of expenses you could cover

### Emergency Fund Runway

Displayed as both:
- A number of months
- A colour-coded indicator:
  - Green: 6 or more months
  - Amber: 3-6 months
  - Red: Less than 3 months

### Savings Goals Tab

Create and track savings goals:
- Goal name (e.g., "Holiday fund", "House deposit")
- Target amount
- Target date
- Current progress
- Percentage complete
- Whether the goal is linked to specific accounts

### Strategy Tab

The system analyses your savings and generates recommendations:
- Interest rate comparison across your accounts
- Tax efficiency of your savings
- Recommendations for better rates
- Suggestions for emergency fund levels
- Recommended monthly savings amount
- How to allocate savings across goals
- Timeline to achieve each goal
- ISA usage strategy

---

## Investments

The Investment module helps you track investment portfolios. It has nine tabs, though only the first two are currently fully functional. The remaining tabs display "Coming Soon" notices.

### Portfolio Overview Tab (Working)

Shows all your investment accounts and summary information:

**Account Types Supported**
- Stocks and Shares ISA
- General Investment Account
- National Savings and Investments (NS&I)
- Onshore Investment Bond
- Offshore Investment Bond
- Venture Capital Trust (VCT)
- Enterprise Investment Scheme (EIS)
- Other investment accounts

**Account Details**
For each account:
- Provider or platform name
- Account type
- Current total value
- Cost basis (what you originally invested)
- Gain or loss
- Ownership type

**Summary Information**
- Total investment portfolio value
- Total original cost
- Overall gain or loss
- Number of accounts
- Number of individual holdings
- Asset allocation chart

**Account Management**
- **Add Account**: Create a new investment account
- **View Account Details**: Click any account to see full details and holdings
- **Edit Account**: Update account details
- **Delete Account**: Remove an account (with confirmation)

### Holdings Tab (Working)

View and manage all holdings across your investment accounts:

**Holding Details**
- Investment name
- Asset type (UK shares, overseas shares, funds, ETFs, bonds, cash, alternatives)
- Quantity held
- Current price per unit
- Current total value
- Original cost
- Gain or loss (both amount and percentage)
- Annual fees (if applicable)

**Holdings Management**
- **Add Holding**: Add a new investment to an account
- **Edit Holding**: Update holding details
- **Delete Holding**: Remove a holding

### Performance Tab (Coming Soon)

This tab will show:
- Value changes over time
- Returns for different periods
- Performance compared to benchmarks
- Performance attribution analysis
- Benchmark comparison

### Portfolio Optimisation Tab (Coming Soon)

This tab will provide:
- Asset allocation analysis
- Efficient frontier calculations
- Risk-return optimisation
- Recommended portfolio adjustments

### Rebalancing Tab (Coming Soon)

This tab will offer:
- Rebalancing calculator
- Threshold-based rebalancing alerts
- Trade recommendations to restore target allocation

### Goals Tab (Coming Soon)

This tab will allow:
- Creating investment goals
- Tracking progress towards goals
- Projection modelling

### Tax Efficiency Tab (Coming Soon)

This tab will show:
- ISA allowance usage
- Tax-efficient wrapper recommendations
- Asset location optimisation

### Fees Tab (Coming Soon)

This tab will display:
- Fee breakdown by account and holding
- Fee impact analysis
- Lower-cost alternatives

### Strategy Tab (Coming Soon)

This tab will provide:
- Investment recommendations
- Suggested actions
- Priority improvements

---

## Retirement Planning

The Retirement module helps you plan for a comfortable retirement. It has six tabs: Overview, Contributions, Projections, Portfolio Analysis, Strategies, and Decumulation.

### Overview Tab (Retirement Readiness)

Shows all your pension arrangements:

**Defined Contribution Pensions**
These are pensions where you build up a pot of money:
- Scheme name
- Provider
- Current fund value
- Your contribution amount or percentage
- Employer contribution (if workplace pension)
- Expected retirement age
- Investment holdings within the pension

**Types of Defined Contribution Pension**
- Workplace (occupational) pension
- Personal pension
- Self-Invested Personal Pension (SIPP)
- Stakeholder pension

**Defined Benefit Pensions**
These are pensions that pay a guaranteed income:
- Scheme name
- Annual pension amount promised
- Payment start age
- Inflation increase type (CPI, RPI, fixed, none)
- Lump sum entitlement
- Spouse's pension percentage

**Types of Defined Benefit Pension**
- Final salary scheme
- Career average (CARE) scheme
- Public sector pension

**State Pension**
Your government pension:
- Projected annual amount
- Number of National Insurance years completed (out of 35)
- State pension age
- Weekly or annual forecast amount

### Pension Wealth Summary

Totals displayed:
- Total defined contribution values
- Total defined benefit annual income
- State pension forecast
- Number of each pension type
- Projected Income (Coming Soon - not yet available)

### Pension Management

You can:
- **Add Pension**: Record a new pension (choose DC, DB, or State)
- **Edit Pension**: Update pension details
- **Delete Pension**: Remove a pension record
- **Upload Statement**: Upload a pension statement for data extraction
- **Click to View Details**: See full information about any pension

### Contributions Tab

**Contribution Tracking**
- Your monthly contributions
- Employer contributions (for workplace pensions)
- Annual total contributions
- Lump sum contributions

**Annual Allowance Monitoring**
- Current annual allowance: £60,000
- Your contributions this tax year
- Remaining allowance
- Carry forward from previous years (up to 3 years)

**Tapered Annual Allowance**
If you are a high earner (adjusted income over £260,000):
- Your reduced annual allowance (can be as low as £10,000)
- Explanation of the taper calculation

**Money Purchase Annual Allowance**
If you have accessed pension benefits flexibly:
- Reduced annual allowance of £10,000 applies
- Tracking of this allowance

### Projections Tab

**Growth Projections**
The system projects your pension values to retirement:
- Conservative scenario (lower growth assumption)
- Moderate scenario (middle assumption)
- Optimistic scenario (higher growth assumption)

**What You Could Have at Retirement**
- Projected fund values for DC pensions
- Projected annual income for DB pensions
- Combined total

**Interactive Adjustments**
Change assumptions to see impact:
- Different retirement ages
- Different contribution levels
- Different growth rates

### Portfolio Analysis Tab (for DC Pensions)

If your DC pensions have recorded holdings:
- Asset allocation breakdown
- Risk assessment
- Diversification analysis
- Fee analysis

### Strategies Tab

Recommendations for improving your retirement position:
- Contribution increase suggestions
- Tax relief optimisation
- Consolidation recommendations
- Pension type recommendations

### Decumulation Planning Tab

Planning for how to take your retirement income:

**Options Explained**
- Take 25% tax-free lump sum
- Buy an annuity (guaranteed income for life)
- Drawdown (keep invested and withdraw as needed)
- Combination approaches

**Sustainable Withdrawal Rate**
- How much you can safely withdraw each year
- Impact of different withdrawal rates on pot longevity

### Years to Retirement

- Your current age
- Your target retirement age
- Number of years remaining

---

## Estate Planning

The Estate module helps you plan what happens to your wealth when you pass away, with particular focus on Inheritance Tax (IHT). It has five tabs: IHT Planning, Will, Gifting Strategy, Life Policy Strategy, and Trust Strategy.

### IHT Planning Tab

**Estate Value Summary**
For married users, the system shows:
- Combined estate value if both were to die today
- Projected combined estate at life expectancy
- Current IHT liability
- Projected IHT liability

For single users:
- Current taxable estate value
- Projected taxable estate value
- Total tax-free allowances
- Current IHT liability
- Projected IHT liability

**Tax-Free Allowances Explained**
- Nil Rate Band (NRB): £325,000 (everyone gets this)
- Residence Nil Rate Band (RNRB): £175,000 (if leaving home to direct descendants)
- Spouse Exemption: Unlimited transfers between married couples
- Transferable NRB: Unused allowance from deceased spouse

**IHT Calculation Breakdown**
A detailed table showing:
- All your assets (property, investments, savings, pensions)
- All your liabilities (mortgages, loans, debts)
- Net estate value
- Available allowances
- Taxable amount
- IHT liability at 40%

For married users, this shows both current and projected calculations for both spouses.

**Strategy Cards**
Quick access to:
- Will planning
- Gifting strategy
- Life insurance strategy

### Will Tab

**Do You Have a Will?**
- Yes, I have a will
- No, I do not have a will
- Not sure / prefer not to say

**If You Have a Will**
Record:
- Date the will was last updated
- Name of your executor (the person who administers your estate)
- Death scenario for planning (your death only, or both spouses dying)
- Percentage left to spouse (affects IHT calculation)
- Executor notes and instructions

**If You Do Not Have a Will**
The system explains intestacy rules - what happens to your estate without a will:
- If married with children
- If married without children
- If single with children
- If single without children

Shows how your estate would be distributed under these rules.

**Specific Bequests**
Record gifts to specific people:
- Beneficiary name
- Type (percentage of estate, specific amount, specific asset, or remainder)
- Amount or percentage
- Any conditions attached

### Gifting Strategy Tab

**Personalised Gifting Strategy**
Based on your actual assets, the system generates recommendations:

**Liquidity Analysis**
- Total estate value
- Immediately giftable amount (cash and liquid assets)
- Giftable with planning (investments that can be sold)
- Not giftable (main residence, illiquid assets)

**Gift Types Explained**
- **Annual Exemption**: £3,000 per year that can be given tax-free
- **Small Gifts**: £250 per recipient per year
- **Gifts from Income**: Regular gifts from surplus income
- **Wedding Gifts**: Up to £5,000 to children, £2,500 to grandchildren
- **Potentially Exempt Transfers (PETs)**: Larger gifts that become exempt after 7 years
- **Chargeable Lifetime Transfers (CLTs)**: Gifts into most trusts

**Recommended Strategies**
For each strategy:
- What it involves
- Amount that could be gifted
- IHT that could be saved
- Risk level
- Implementation steps
- Tax considerations

**Gifting Timeline**
A schedule showing when to make gifts for maximum tax efficiency.

### Life Policy Strategy Tab

**Cover to Pay IHT**
Recommendations for life insurance to cover the IHT bill:
- Amount of cover needed
- Policy type recommended (whole of life)
- Importance of writing policy in trust
- Why this helps beneficiaries

### Trust Strategy Tab

**Planned Trust Strategy**
Information about trusts and when they might be useful:
- Types of trusts (bare, discretionary, interest in possession, etc.)
- IHT implications of different trust types
- When trusts make sense

**Your Trusts**
If you have established trusts, record:
- Trust name
- Trust type
- Creation date
- Initial value
- Current value
- Beneficiaries
- Trustees

Trust types available:
- Bare trust
- Interest in possession
- Discretionary trust
- Accumulation and maintenance
- Life insurance trust
- Discounted gift trust
- Loan trust
- Mixed trust
- Settlor-interested trust

**Trust Recommendations**
Based on your estate value and IHT liability, suggestions for trusts that might help.

---

## Net Worth Overview

The Net Worth module brings together all your financial information in one place. It has eight tabs: Overview, Retirement, Property, Investments, Cash, Business Interests, Chattels, and Joint History.

### Overview Tab

**Summary Cards**
- Total Assets: Everything you own
- Total Liabilities: Everything you owe
- Net Worth: Assets minus liabilities

**Charts**
- **Wealth Summary Bar Chart**: Side-by-side comparison of you and your spouse (if linked)
- **Asset Allocation Doughnut**: Breakdown of assets by type
- **Net Worth Trend Chart**: How your net worth has changed over time

### Property Tab

Access to your property portfolio (see Properties and Mortgages section).

### Retirement Tab

View your retirement assets within the net worth context.

### Investments Tab

View your investment assets within the net worth context.

### Cash Tab

View your savings within the net worth context.

### Business Interests Tab

**Note**: This feature displays a "Coming Soon" notice. The user interface is prepared but the functionality is not yet active.

Planned features:
- Track business ownership stakes
- Record different business structures (sole trader, partnership, limited company, LLP)
- Include business value in net worth
- Plan for business succession

### Chattels Tab

**Note**: This feature displays a "Coming Soon" notice. The user interface is prepared but the functionality is not yet active.

Planned features:
- Track vehicles, art, antiques, jewellery, collectibles
- Record valuations and purchase history
- Include in net worth calculations
- Plan for inheritance implications

### Joint History Tab

If you have a linked spouse account:
- View all changes to joint accounts
- See who made each change
- Track modification history
- Audit trail for shared assets

---

## Properties and Mortgages

### Property List

All your properties are displayed as cards showing:
- Property address
- Property type (main residence, second home, buy-to-let)
- Current value
- Purchase price
- Ownership type (individual, joint, tenants in common, trust)
- Outstanding mortgage (if any)
- Equity (value minus mortgage)

### Adding a Property

When adding a property, you record:

**Property Details**
- Full address
- Property type
- Purchase price
- Purchase date
- Current market value
- Valuation date
- Ownership type
- If joint, owner percentages

**Mortgage Details** (optional)
- Lender name
- Original loan amount
- Current balance
- Interest rate
- Rate type (fixed, variable, tracker, discount)
- Mortgage type (repayment, interest-only, mixed)
- Term (years)
- Monthly payment
- Start date
- End date

### Property Detail View

Click any property to see:

**Summary Panel**
- Address and type
- Value and equity
- Purchase information
- Ownership details

**Mortgage Panel** (if applicable)
- Loan details
- Remaining balance
- Monthly payment
- Interest rate and type
- Remaining term

**Amortisation Schedule**
A month-by-month breakdown showing:
- Monthly payment
- How much goes to interest
- How much goes to reducing the loan
- Remaining balance

**Tax Calculations**

**Stamp Duty Calculator**
- Calculate Stamp Duty Land Tax (SDLT) on purchase
- Shows amount for first-time buyers vs additional properties
- Calculates additional property surcharge if applicable

**Capital Gains Tax Calculator**
- Estimate CGT if you were to sell
- Takes into account main residence relief
- Shows allowable costs and reliefs
- Calculates tax at appropriate rate

**Rental Income Tax** (for buy-to-let properties)
- Record rental income
- Calculate tax on rental profit
- Show allowable expenses

### Editing and Deleting Properties

You can:
- Edit any property details
- Edit mortgage details
- Delete a property (with confirmation)

### Joint Properties

When a property is owned jointly:
- Both owners see the property in their account
- Each sees their share of the value
- Changes sync between both accounts
- An audit trail records who made changes

---

## Spouse and Family Management

### Linking Spouse Accounts

If your spouse also uses Fynla, you can link your accounts:

**Linking Process**
1. Enter your spouse's email address
2. If they have an account, a link request is sent
3. Your spouse accepts or declines the request
4. Once accepted, accounts are linked

**If Spouse Has No Account**
The system can:
- Create an account for your spouse automatically
- Send them an invitation email
- Link the accounts once they log in

### Data Sharing Permissions

When accounts are linked, you can control what is shared:
- View permissions (can your spouse see your data?)
- Edit permissions (can your spouse change your data?)

Permissions can be:
- Granted
- Revoked at any time
- Set separately for different areas

### Joint Assets

For assets owned together:
- Create once, appears in both accounts
- Each person sees their percentage share
- Changes sync automatically
- Full audit trail maintained

### Family Members

Add and manage family members:

**Information Recorded**
- Full name
- Relationship (spouse, child, parent, etc.)
- Date of birth (required)
- Whether they are a dependant

**Why This Matters**
- Dependants affect protection needs calculations
- Children's ages affect education cost planning
- Family structure affects estate planning
- Spouse details enable account linking

---

## Document Upload

### Upload Feature

Throughout the application, you can upload documents for automatic data extraction:

**Supported Document Types**
- Insurance policy documents
- Pension statements
- Bank and savings statements
- Investment statements

**Supported File Formats**
- PDF documents
- Images (PNG, JPG)
- Excel spreadsheets

### How It Works

1. Click "Upload Document" in the relevant section
2. Select or drag your document
3. The system analyses the document
4. Extracted data is displayed for your review
5. Confirm to save the data, or make corrections first

### What Gets Extracted

**From Insurance Policies**
- Provider name
- Policy type
- Sum assured
- Premium amount
- Policy dates

**From Pension Statements**
- Provider name
- Scheme type
- Current value
- Contribution amounts
- Retirement age

**From Bank Statements**
- Bank name
- Account type
- Balance
- Interest rate

**From Investment Statements**
- Provider name
- Account type
- Holdings and values
- Performance data

### Manual Entry Option

If extraction is not successful, you can always enter data manually through the standard forms.

---

## Administrator Features

Administrator accounts have access to additional functionality. The Admin Panel has four tabs: Dashboard, User Management, Database Backups, and Tax Settings.

### Admin Dashboard Tab

Overview statistics:
- Total number of users
- Records per module
- System health indicators

### User Management Tab

Administrators can:
- View all user accounts
- Create new users
- Edit user details
- Delete user accounts
- Grant or revoke administrator access

### Database Backups Tab

**Creating Backups**
- Create manual database backup
- View backup creation date and size
- Download backup files

**Restoring Backups**
- View available backups
- Restore from a selected backup (with warnings about data loss)
- Delete old backups

### Tax Settings Tab

Manage UK tax rates and allowances:

**Income Tax Settings**
- Personal allowance amount
- Tax band thresholds and rates (basic, higher, additional)

**National Insurance Settings**
- Thresholds and rates

**Capital Gains Tax Settings**
- Annual exemption amount
- Rates for basic and higher rate taxpayers

**Dividend Tax Settings**
- Dividend allowance
- Tax rates

**ISA Allowances**
- Annual ISA limit
- Type-specific limits

**Pension Allowances**
- Annual allowance
- Lifetime allowance (if applicable)
- Money purchase annual allowance
- Taper rules for high earners

**Inheritance Tax Settings**
- Nil rate band
- Residence nil rate band
- Tax rate
- Gifting rules and exemptions

**Stamp Duty Settings**
- SDLT bands and rates
- Additional property surcharge

**Tax Year Management**
- View configurations for different tax years
- Create new tax year configuration
- Copy settings from previous year
- Set active tax year

---

## Features Not Yet Available

The following features are shown in the user interface but are not yet functional:

### Business Interests

The Net Worth module shows a "Business Interests" tab with a "Coming Soon" watermark. The planned functionality includes:
- Recording business ownership stakes and valuations
- Tracking different business structures
- Including business assets in net worth
- Planning for business succession

**Current Status**: User interface exists but data entry and storage are not implemented.

### Chattels and Valuables

The Net Worth module shows a "Chattels & Valuables" tab with a "Coming Soon" watermark. The planned functionality includes:
- Recording vehicles, art, antiques, jewellery, collectibles
- Tracking valuations and purchase history
- Including items in net worth calculations
- Planning for inheritance implications

**Current Status**: User interface exists but data entry and storage are not implemented.

### Investment Module Advanced Features

Most tabs in the Investment module display "Coming Soon" notices:
- **Performance Tab**: Performance tracking, attribution analysis, benchmark comparisons
- **Portfolio Optimisation Tab**: Efficient frontier calculations, risk-return optimisation
- **Rebalancing Tab**: Rebalancing calculator, trade recommendations
- **Goals Tab**: Investment goals, progress tracking, projections
- **Tax Efficiency Tab**: Asset location optimisation, tax-efficient wrapper recommendations
- **Fees Tab**: Fee breakdown, fee impact analysis
- **Strategy Tab**: Investment recommendations and suggested actions

**Current Status**: User interfaces exist with coming soon banners. Only Portfolio Overview and Holdings tabs are fully functional.

### Trust Strategy Card

On the IHT Planning tab, there is a "Trust" strategy card that shows "Coming Soon". While basic trust recording is available in the Trust Strategy tab, advanced trust planning tools and recommendations are not yet available.

### Projected Income in Retirement

In the Retirement module, the "Projected Income" card displays "Coming Soon - Not available in this version". The system can project pension values but does not yet calculate projected monthly income in retirement.

### Bequest Editing

While bequests can be added and deleted in the Will section, the "Edit" button for existing bequests does not currently have an active form behind it.

### Holistic Financial Plan

A cross-module "Holistic Plan" feature is referenced in the codebase but is not accessible through the main navigation. This would combine analysis from all five modules into a single comprehensive plan.

### Actions Dashboard

A unified "Actions" or "Recommendations" dashboard that aggregates recommendations from all modules exists in the codebase but is not prominently accessible. Individual module recommendations work correctly.

### Learning Centre and Calculators

The public landing page mentions a "Learning Centre" and "Calculators" section, but these are minimal placeholder pages rather than fully developed features.

---

## Summary

Fynla is a comprehensive financial planning application with full functionality across its five core modules:

**Fully Working Features**
- User registration, login, and profile management
- Step-by-step onboarding wizard
- Protection planning with all five policy types
- Savings tracking with ISA allowance monitoring
- Investment portfolio overview and holdings management
- Retirement planning with all pension types
- Estate planning with IHT calculations, wills, gifts, and trusts
- Net worth overview with property management
- Spouse account linking and joint asset tracking
- Document upload with data extraction
- Administrator controls for users, backups, and tax settings

**Features Coming Soon**
- Investment performance tracking, optimisation, rebalancing, and goals
- Business interests tracking
- Chattels and valuables tracking
- Advanced trust planning tools
- Projected retirement income calculations
- Holistic cross-module planning dashboard
- Educational learning centre
- Public financial calculators

The application uses current United Kingdom tax rules (2025/26 tax year) and provides accurate calculations based on the financial information you enter.

---

*Document generated from Fynla application version 0.2.16*
*Last updated: December 2025*
