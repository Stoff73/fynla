---
procedure_id: 'savings.tool.create_investment_account'
kind: tool_schema
module: savings
provider: xai
version: 2
active: true
effective_from: 2026-06-11
---

```json
{
    "name": "create_investment_account",
    "description": "Create an investment account only after the user explicitly states its type and whether it is owned individually or jointly. A bare ISA must be clarified as Stocks & Shares, Lifetime, Innovative Finance, or Cash before choosing this tool. Joint records also require the joint owner and primary owner's percentage share. Use account_type other for alternative financial assets.",
    "parameters": {
        "type": "object",
        "properties": {
            "account_name": {
                "type": "string",
                "description": "Name of the account (e.g. \"Vanguard Stocks & Shares ISA\")"
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
                "description": "Type of investment account."
            },
            "provider": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Platform, provider, or company name"
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
                "type": ["integer", "null"],
                "description": "User ID of the confirmed joint owner. Required when ownership is joint; otherwise null."
            },
            "ownership_percentage": {
                "type": ["number", "null"],
                "description": "Primary owner's confirmed percentage share. Required when ownership is joint or tenants_in_common; otherwise 100 for individual."
            },
            "monthly_contribution_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly contribution amount in pounds"
            },
            "platform_fee_percent": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual platform fee as a percentage (e.g. 0.15)"
            },
            "annual_dividend_income": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Annual dividend income this account pays in pounds, when the user states it (e.g. \"pays about £800 a year in dividends\" → 800). Only for taxable accounts (GIA, shares); leave null for ISAs — ISA dividends are tax-free and never use the Dividend Allowance."
            },
            "isa_subscription_current_year": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "For an ISA, how much the user has paid IN during the CURRENT tax year, when stated (e.g. \"I've put in £5,000 this year\" → 5000). This is the subscription that counts against the £20,000 ISA allowance — NOT the account's total value. Only for ISA account types; leave null for GIA and other taxable accounts."
            },
            "bond_purchase_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Bond purchase date (YYYY-MM-DD). Only for onshore_bond or offshore_bond."
            },
            "bond_withdrawal_taken": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Total 5% tax-deferred withdrawals taken (£). Only for bonds."
            },
            "company_legal_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Legal name of the company. For private_company or crowdfunding."
            },
            "company_registration_number": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Companies House registration number."
            },
            "crowdfunding_platform": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "Seedrs",
                            "Crowdcube",
                            "Republic",
                            "Wefunder",
                            "other"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Crowdfunding platform. Only for crowdfunding type."
            },
            "investment_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date of investment (YYYY-MM-DD)."
            },
            "investment_amount": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Original investment amount (£)."
            },
            "number_of_shares": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Number of shares held."
            },
            "price_per_share": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Price per share (£)."
            },
            "instrument_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "ordinary_shares",
                            "preference_shares",
                            "convertible_loan_note",
                            "safe",
                            "revenue_share",
                            "fund_nominee_interest"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Type of instrument held."
            },
            "funding_round": {
                "anyOf": [
                    {
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
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Funding round."
            },
            "share_class": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Share class (e.g. \"A Ordinary\")."
            },
            "tax_relief_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "eis",
                            "seis",
                            "sitr",
                            "vct",
                            ""
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Tax relief scheme applied."
            },
            "employer_name": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Employer company name. For employee share schemes."
            },
            "employer_is_listed": {
                "type": [
                    "boolean",
                    "null"
                ],
                "description": "Whether shares are publicly listed. For employee share schemes."
            },
            "grant_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date options/shares were granted (YYYY-MM-DD)."
            },
            "units_granted": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Number of units/options granted."
            },
            "exercise_price": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Exercise/strike price per share (£)."
            },
            "market_value_at_grant": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Market value per share at grant date (£)."
            },
            "current_share_price": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current share price (£)."
            },
            "units_vested": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Number of units currently vested."
            },
            "units_unvested": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Number of units not yet vested."
            },
            "vesting_type": {
                "anyOf": [
                    {
                        "type": "string",
                        "enum": [
                            "cliff",
                            "monthly",
                            "quarterly",
                            "annual",
                            "performance",
                            "immediate"
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Vesting schedule type."
            },
            "full_vest_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Date all units fully vest (YYYY-MM-DD)."
            },
            "cliff_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "Cliff vesting date (YYYY-MM-DD)."
            },
            "cliff_percentage": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Percentage that vests at cliff (e.g. 25)."
            },
            "saye_monthly_savings": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Monthly savings amount (max £500). SAYE only."
            },
            "saye_current_savings_balance": {
                "type": [
                    "number",
                    "null"
                ],
                "description": "Current savings balance (£). SAYE only."
            },
            "scheme_start_date": {
                "type": [
                    "string",
                    "null"
                ],
                "description": "SAYE contract start date (YYYY-MM-DD)."
            },
            "scheme_duration_months": {
                "anyOf": [
                    {
                        "type": "integer",
                        "enum": [
                            36,
                            60
                        ]
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "SAYE contract duration: 36 (3 years) or 60 (5 years)."
            },
            "holdings": {
                "anyOf": [
                    {
                        "type": "array",
                        "items": {
                            "type": "object",
                            "properties": {
                                "security_name": {
                                    "type": "string",
                                    "description": "Name of the fund, ETF, or share (e.g. \"Vanguard FTSE All-World\", \"iShares Core UK Gilts\")"
                                },
                                "asset_type": {
                                    "type": "string",
                                    "enum": [
                                        "equity",
                                        "uk_equity",
                                        "us_equity",
                                        "international_equity",
                                        "fund",
                                        "etf",
                                        "bond",
                                        "cash",
                                        "alternative",
                                        "property"
                                    ],
                                    "description": "Type of holding: \"fund\" for OEICs/unit trusts, \"etf\" for ETFs, \"uk_equity\"/\"us_equity\"/\"international_equity\" for shares, \"bond\" for fixed income, \"cash\" for cash."
                                },
                                "allocation_percent": {
                                    "type": "number",
                                    "description": "Percentage of the account this holding represents (0-100). All holdings must total 100% or less."
                                },
                                "cost_basis": {
                                    "type": [
                                        "number",
                                        "null"
                                    ],
                                    "description": "Total amount originally invested in this holding (£). Optional."
                                }
                            },
                            "required": [
                                "security_name",
                                "asset_type",
                                "allocation_percent",
                                "cost_basis"
                            ],
                            "additionalProperties": false
                        }
                    },
                    {
                        "type": "null"
                    }
                ],
                "description": "Array of holdings to add inline when creating the account. Only for ISA, GIA, onshore/offshore bonds, VCT, EIS. Each holding has security_name, asset_type, allocation_percent (% of account), and optional cost_basis. Any unallocated remainder auto-defaults to cash. If the user mentions specific funds/ETFs/shares they hold, include them here instead of using create_holding separately."
            }
        },
        "required": [
            "account_name",
            "account_type",
            "provider",
            "current_value",
            "ownership_type",
            "joint_owner_id",
            "ownership_percentage",
            "monthly_contribution_amount",
            "platform_fee_percent",
            "annual_dividend_income",
            "isa_subscription_current_year",
            "bond_purchase_date",
            "bond_withdrawal_taken",
            "company_legal_name",
            "company_registration_number",
            "crowdfunding_platform",
            "investment_date",
            "investment_amount",
            "number_of_shares",
            "price_per_share",
            "instrument_type",
            "funding_round",
            "share_class",
            "tax_relief_type",
            "employer_name",
            "employer_is_listed",
            "grant_date",
            "units_granted",
            "exercise_price",
            "market_value_at_grant",
            "current_share_price",
            "units_vested",
            "units_unvested",
            "vesting_type",
            "full_vest_date",
            "cliff_date",
            "cliff_percentage",
            "saye_monthly_savings",
            "saye_current_savings_balance",
            "scheme_start_date",
            "scheme_duration_months",
            "holdings"
        ],
        "additionalProperties": false
    },
    "strict": true
}
```
