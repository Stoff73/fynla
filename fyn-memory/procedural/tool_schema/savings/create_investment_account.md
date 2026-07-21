---
procedure_id: 'savings.tool.create_investment_account'
kind: tool_schema
module: savings
version: 2
active: true
effective_from: 2026-06-11
---

```json
{
    "name": "create_investment_account",
    "description": "Create an investment account only after the user explicitly states its type and whether it is owned individually or jointly. A bare ISA must be clarified as Stocks & Shares, Lifetime, Innovative Finance, or Cash before choosing this tool. Joint records also require the joint owner and primary owner's percentage share.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name of the account (e.g., \"Vanguard Stocks & Shares ISA\", \"Hargreaves Lansdown GIA\", \"Octopus VCT\")"
            },
            "account_type": {
                "type": "string",
                "enum": [
                    "stocks_shares_isa",
                    "lifetime_isa",
                    "innovative_finance_isa",
                    "personal_investment_account",
                    "onshore_bond",
                    "offshore_bond",
                    "vct",
                    "eis",
                    "private_company",
                    "crowdfunding",
                    "saye",
                    "csop",
                    "emi",
                    "unapproved_options",
                    "rsu",
                    "other"
                ],
                "description": "Investment type explicitly stated by the user. Use innovative_finance_isa for an Innovative Finance ISA. Never default a missing type."
            },
            "provider": {
                "type": "string",
                "description": "Platform, provider, or company name (e.g., \"Vanguard\", \"Hargreaves Lansdown\", \"Octopus Investments\")"
            },
            "current_value": {
                "type": "number",
                "description": "Current value in pounds"
            },
            "ownership_type": {
                "type": "string",
                "enum": ["individual", "joint"],
                "description": "Ownership explicitly confirmed by the user. Investment accounts support individual or joint ownership; ISAs must be individual."
            },
            "joint_owner_id": {
                "type": "integer",
                "description": "User ID of the confirmed joint owner. Required for joint ownership."
            },
            "ownership_percentage": {
                "type": "number",
                "description": "Primary owner's confirmed percentage share. Required for joint ownership."
            },
            "monthly_contribution_amount": {
                "type": "number",
                "description": "Monthly contribution amount in pounds, if any"
            },
            "platform_fee_percent": {
                "type": "number",
                "description": "Annual platform fee as a percentage (e.g., 0.15 for 0.15%)"
            },
            "annual_dividend_income": {
                "type": "number",
                "description": "Annual dividend income this account pays in pounds, when the user states it (e.g. \"pays about £800 a year in dividends\" → 800). Only for taxable accounts (GIA, shares); omit for ISAs — ISA dividends are tax-free and never use the Dividend Allowance."
            },
            "isa_subscription_current_year": {
                "type": "number",
                "description": "For an ISA, how much the user has paid IN during the CURRENT tax year, when stated (e.g. \"I've put in £5,000 this year\" → 5000). This is the subscription that counts against the £20,000 ISA allowance — NOT the account's total value. Only for ISA account types; omit for GIA and other taxable accounts."
            },
            "bond_purchase_date": {
                "type": "string",
                "description": "Bond purchase date in YYYY-MM-DD format. Only for onshore_bond or offshore_bond."
            },
            "bond_withdrawal_taken": {
                "type": "number",
                "description": "Total 5% tax-deferred withdrawals taken to date in pounds. Only for onshore_bond or offshore_bond."
            },
            "company_legal_name": {
                "type": "string",
                "description": "Legal name of the company. For private_company or crowdfunding types."
            },
            "company_registration_number": {
                "type": "string",
                "description": "Companies House registration number. For private_company or crowdfunding types."
            },
            "crowdfunding_platform": {
                "type": "string",
                "enum": [
                    "Seedrs",
                    "Crowdcube",
                    "Republic",
                    "Wefunder",
                    "other"
                ],
                "description": "Crowdfunding platform name. Only for crowdfunding type."
            },
            "investment_date": {
                "type": "string",
                "description": "Date of investment in YYYY-MM-DD format. For private_company, crowdfunding, vct, eis."
            },
            "investment_amount": {
                "type": "number",
                "description": "Original investment amount in pounds. For private_company, crowdfunding, vct, eis."
            },
            "number_of_shares": {
                "type": "number",
                "description": "Number of shares held. For private_company, crowdfunding, vct, eis."
            },
            "price_per_share": {
                "type": "number",
                "description": "Price per share in pounds. For private_company, crowdfunding, vct, eis."
            },
            "instrument_type": {
                "type": "string",
                "enum": [
                    "ordinary_shares",
                    "preference_shares",
                    "convertible_loan_note",
                    "safe",
                    "revenue_share",
                    "fund_nominee_interest"
                ],
                "description": "Type of instrument held. For private_company or crowdfunding."
            },
            "funding_round": {
                "type": "string",
                "enum": [
                    "pre_seed",
                    "seed",
                    "series_a",
                    "series_b",
                    "series_c",
                    "bridge",
                    "safe",
                    "other"
                ],
                "description": "Funding round. For private_company or crowdfunding."
            },
            "share_class": {
                "type": "string",
                "description": "Share class (e.g., \"A Ordinary\", \"B Preference\"). For private_company or crowdfunding."
            },
            "tax_relief_type": {
                "type": "string",
                "enum": [
                    "eis",
                    "seis",
                    "sitr",
                    "vct",
                    ""
                ],
                "description": "Tax relief scheme applied. For private_company, crowdfunding, vct, eis."
            },
            "employer_name": {
                "type": "string",
                "description": "Employer company name. For employee share schemes (saye, csop, emi, unapproved_options, rsu)."
            },
            "employer_is_listed": {
                "type": "boolean",
                "description": "Whether shares are publicly listed/traded. For employee share schemes."
            },
            "grant_date": {
                "type": "string",
                "description": "Date options/shares were granted in YYYY-MM-DD format. For employee share schemes."
            },
            "units_granted": {
                "type": "number",
                "description": "Number of units/options granted. For employee share schemes."
            },
            "exercise_price": {
                "type": "number",
                "description": "Exercise/strike price per share in pounds. For saye, csop, emi, unapproved_options."
            },
            "market_value_at_grant": {
                "type": "number",
                "description": "Market value per share at grant date in pounds. For employee share schemes."
            },
            "current_share_price": {
                "type": "number",
                "description": "Current share price in pounds. For employee share schemes."
            },
            "units_vested": {
                "type": "number",
                "description": "Number of units currently vested. For employee share schemes."
            },
            "units_unvested": {
                "type": "number",
                "description": "Number of units not yet vested. For employee share schemes."
            },
            "vesting_type": {
                "type": "string",
                "enum": [
                    "cliff",
                    "monthly",
                    "quarterly",
                    "annual",
                    "performance",
                    "immediate"
                ],
                "description": "Vesting schedule type. For employee share schemes."
            },
            "full_vest_date": {
                "type": "string",
                "description": "Date all units fully vest in YYYY-MM-DD format. For employee share schemes."
            },
            "cliff_date": {
                "type": "string",
                "description": "Cliff vesting date in YYYY-MM-DD format. For employee share schemes with cliff vesting."
            },
            "cliff_percentage": {
                "type": "number",
                "description": "Percentage that vests at cliff (e.g., 25). For employee share schemes with cliff vesting."
            },
            "saye_monthly_savings": {
                "type": "number",
                "description": "Monthly savings amount (max £500). Only for saye type."
            },
            "saye_current_savings_balance": {
                "type": "number",
                "description": "Current savings balance in pounds. Only for saye type."
            },
            "scheme_start_date": {
                "type": "string",
                "description": "SAYE contract start date in YYYY-MM-DD format. Only for saye type."
            },
            "scheme_duration_months": {
                "type": "number",
                "enum": [
                    36,
                    60
                ],
                "description": "SAYE contract duration: 36 (3 years) or 60 (5 years). Only for saye type."
            }
        },
        "required": [
            "account_name",
            "current_value",
            "ownership_type"
        ],
        "additionalProperties": false
    }
}
```
